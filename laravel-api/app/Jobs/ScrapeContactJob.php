<?php

namespace App\Jobs;

use App\Models\ActivityLog;
use App\Models\ContactTypeModel;
use App\Models\Directory;
use App\Models\Influenceur;
use App\Models\Setting;
use App\Services\BlockedDomainService;
use App\Services\DirectoryScraperService;
use App\Services\WebScraperService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ScrapeContactJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120; // 2 min (up to 12 pages × 2s delay + processing)
    public int $tries = 1; // Don't retry failed scrapes

    public function __construct(
        private int $influenceurId,
    ) {
        $this->onQueue('scraper');
    }

    public function handle(WebScraperService $scraper): void
    {
        $influenceur = Influenceur::find($this->influenceurId);
        if (!$influenceur) {
            Log::warning('ScrapeContactJob: influenceur not found', ['id' => $this->influenceurId]);
            return;
        }

        // Double-check global toggle (may have changed since dispatch)
        if (!Setting::getBool('scraper_enabled')) {
            $this->markStatus($influenceur, 'skipped');
            Log::debug('ScrapeContactJob: global scraper disabled, skipping', ['id' => $influenceur->id]);
            return;
        }

        // Double-check per-type toggle
        $contactType = $influenceur->contact_type instanceof \App\Enums\ContactType
            ? $influenceur->contact_type->value
            : $influenceur->contact_type;

        $typeModel = ContactTypeModel::where('value', $contactType)->first();
        if ($typeModel && !$typeModel->scraper_enabled) {
            $this->markStatus($influenceur, 'skipped');
            Log::debug('ScrapeContactJob: type scraper disabled', [
                'id'   => $influenceur->id,
                'type' => $contactType,
            ]);
            return;
        }

        // Determine which URL to scrape
        $url = $influenceur->website_url ?: $influenceur->profile_url;

        // Detect directory/aggregator URLs using centralized service
        $isDirectory = BlockedDomainService::isDirectoryUrl($url);

        // === DIRECTORY EXPLOITATION MODE ===
        // Instead of just skipping directory URLs, we exploit them as data sources.
        // Known directories (AEFE, MLF, etc.) are scraped to extract individual contacts.
        if ($isDirectory && !empty($url) && DirectoryScraperService::isExploitableDirectory($url)) {
            $this->handleDirectoryScraping($influenceur, $url, $contactType);
            // Also try to discover the real website for THIS contact
            if (!empty($influenceur->name)) {
                $discoveredUrl = $scraper->discoverWebsiteUrl(
                    $influenceur->name,
                    $influenceur->country
                );
                if ($discoveredUrl) {
                    $url = $discoveredUrl;
                    $influenceur->update(['website_url' => $discoveredUrl]);
                    Log::info('ScrapeContactJob: discovered real website after directory scraping', [
                        'id'  => $influenceur->id,
                        'url' => $discoveredUrl,
                    ]);
                } else {
                    // Directory was exploited, but no own website found — we're done
                    return;
                }
            } else {
                return;
            }
        }

        // If no URL or directory URL, discover the real website via DuckDuckGo
        if ((empty($url) || $isDirectory) && !empty($influenceur->name)) {
            $discoveredUrl = $scraper->discoverWebsiteUrl(
                $influenceur->name,
                $influenceur->country
            );
            if ($discoveredUrl) {
                $url = $discoveredUrl;
                $influenceur->update(['website_url' => $discoveredUrl]);
                Log::info('ScrapeContactJob: discovered real website URL via DuckDuckGo', [
                    'id'       => $influenceur->id,
                    'url'      => $discoveredUrl,
                    'replaced' => $isDirectory ? 'directory URL' : 'empty',
                ]);
            }
        }

        if (empty($url)) {
            $this->markStatus($influenceur, 'skipped');
            return;
        }

        // Ensure URL has a scheme
        if (!preg_match('/^https?:\/\//i', $url)) {
            $url = 'https://' . $url;
        }

        Log::info('ScrapeContactJob: starting', ['id' => $influenceur->id, 'url' => $url]);

        try {
            $result = $scraper->scrape($url);

            // Status = completed if we found ANY useful data, even if some extraction had errors
            $hasData = !empty($result['emails']) || !empty($result['phones']) || !empty($result['social_links']) || !empty($result['addresses']);
            $status = ($result['success'] || $hasData) ? 'completed' : 'failed';

            // Merge social links + linked contacts into scraped_social
            $socialData = $result['social_links'] ?: [];
            if (!empty($result['linked_contacts'])) {
                $socialData['_linked_contacts'] = $result['linked_contacts'];
            }
            if (!empty($result['contact_persons'])) {
                $socialData['_contact_persons'] = $result['contact_persons'];
            }
            // Store suggested emails in social data (displayed separately in frontend)
            if (!empty($result['suggested_emails'])) {
                $socialData['_suggested_emails'] = $result['suggested_emails'];
            }

            // Store detected language in social data for frontend display
            if (!empty($result['detected_language'])) {
                $socialData['_detected_language'] = $result['detected_language'];
            }

            // Store contact form URL if found
            if (!empty($result['contact_form_url'])) {
                $socialData['_contact_form_url'] = $result['contact_form_url'];
            }

            // Update the influenceur with scraped data
            $updateData = [
                'scraped_at'        => now(),
                'scraper_status'    => $status,
                'scraped_emails'    => $result['emails'] ?: null,
                'scraped_phones'    => $result['phones'] ?: null,
                'scraped_social'    => !empty($socialData) ? $socialData : null,
                'scraped_addresses' => $result['addresses'] ?: null,
            ];

            // Update language if detected and different from current
            // This helps identify non-francophone contacts imported by mistake
            if (!empty($result['detected_language'])) {
                $detectedLang = $result['detected_language'];
                $currentLang = $influenceur->language;

                // Only update if: no language set, or detected language differs
                if (empty($currentLang) || $currentLang !== $detectedLang) {
                    $updateData['language'] = $detectedLang;
                    Log::info('ScrapeContactJob: language updated from site detection', [
                        'id'       => $influenceur->id,
                        'name'     => $influenceur->name,
                        'previous' => $currentLang,
                        'detected' => $detectedLang,
                    ]);
                }
            }

            // Safety: if too many emails found (>10), this is probably an aggregator page
            // Keep the data but don't auto-fill the primary email
            $isSuspiciousAggregator = count($result['emails']) > 10;

            if ($isSuspiciousAggregator) {
                Log::warning('ScrapeContactJob: suspicious aggregator page', [
                    'id' => $influenceur->id,
                    'url' => $url,
                    'emails_found' => count($result['emails']),
                ]);
                // Cap stored emails to 10 max
                $updateData['scraped_emails'] = array_slice($result['emails'], 0, 10);
            }

            // Smart email filling: fill empty OR replace if mismatch detected
            if (!$isSuspiciousAggregator && !empty($result['emails'])) {
                $siteDomain = parse_url($url, PHP_URL_HOST);
                if ($siteDomain) $siteDomain = preg_replace('/^www\./', '', strtolower($siteDomain));

                if (empty($influenceur->email)) {
                    // No email yet — pick the best one matching the site domain
                    $bestEmail = $this->findBestEmailForDomain($result['emails'], $siteDomain);
                    $updateData['email'] = $bestEmail ?? $result['emails'][0];
                } else {
                    // Has email — check if it matches the site domain
                    $currentDomain = strtolower(substr($influenceur->email, strpos($influenceur->email, '@') + 1));
                    $isGeneric = in_array($currentDomain, ['gmail.com','yahoo.com','yahoo.fr','hotmail.com','hotmail.fr','outlook.com','outlook.fr','live.com','free.fr','orange.fr','sfr.fr','laposte.net','icloud.com','protonmail.com']);

                    if (!$isGeneric && $siteDomain && !str_contains($siteDomain, $currentDomain) && !str_contains($currentDomain, $siteDomain)) {
                        // MISMATCH: email domain doesn't match site — try to find better one
                        $betterEmail = $this->findBestEmailForDomain($result['emails'], $siteDomain);
                        if ($betterEmail) {
                            Log::info('Scraper: replacing mismatched email', [
                                'id' => $influenceur->id, 'old' => $influenceur->email, 'new' => $betterEmail,
                            ]);
                            $updateData['email'] = $betterEmail;
                            $updateData['email_verified_status'] = 'unverified';
                        }
                    }
                }
            }

            if (empty($influenceur->phone) && !empty($result['phones'])) {
                $updateData['phone'] = $result['phones'][0];
            }

            $influenceur->update($updateData);

            // Log the activity
            $details = [
                'url'              => $url,
                'pages_scraped'    => count($result['scraped_pages']),
                'emails_found'     => count($result['emails']),
                'suggested_emails' => count($result['suggested_emails'] ?? []),
                'phones_found'     => count($result['phones']),
                'social_found'     => count($result['social_links']),
                'addresses_found'  => count($result['addresses'] ?? []),
                'success'          => $result['success'],
            ];

            if ($result['error']) {
                $details['error'] = $result['error'];
            }

            // Track what was actually filled in
            if (empty($influenceur->getOriginal('email')) && !empty($result['emails'])) {
                $details['email_filled'] = $result['emails'][0];
            }
            if (empty($influenceur->getOriginal('phone')) && !empty($result['phones'])) {
                $details['phone_filled'] = $result['phones'][0];
            }

            ActivityLog::create([
                'user_id'        => $influenceur->created_by, // Use contact's creator as fallback
                'influenceur_id' => $influenceur->id,
                'action'         => 'scraper_completed',
                'details'        => $details,
                'contact_type'   => $contactType,
            ]);

            Log::info('ScrapeContactJob: completed', [
                'id'            => $influenceur->id,
                'emails_found'  => count($result['emails']),
                'phones_found'  => count($result['phones']),
                'success'       => $result['success'],
            ]);

        } catch (\Throwable $e) {
            $this->markStatus($influenceur, 'failed');

            ActivityLog::create([
                'user_id'        => $influenceur->created_by,
                'influenceur_id' => $influenceur->id,
                'action'         => 'scraper_failed',
                'details'        => [
                    'url'   => $url,
                    'error' => substr($e->getMessage(), 0, 500),
                ],
                'contact_type' => $contactType,
            ]);

            Log::error('ScrapeContactJob: exception', [
                'id'    => $influenceur->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Exploit a directory URL (AEFE, MLF, etc.) to extract individual contacts.
     * Creates new Influenceur records for each contact found in the listing.
     */
    private function handleDirectoryScraping(Influenceur $influenceur, string $url, string $contactType): void
    {
        Log::info('ScrapeContactJob: exploiting directory as data source', [
            'id'  => $influenceur->id,
            'url' => $url,
        ]);

        try {
            // Auto-register in directories table if not already present
            $domain = Directory::extractDomain($url);
            $existingDir = Directory::where('domain', $domain)
                ->where('category', $contactType)
                ->first();

            if (!$existingDir) {
                Directory::create([
                    'name'       => 'Auto: ' . $domain,
                    'url'        => $url,
                    'domain'     => $domain,
                    'category'   => $contactType,
                    'country'    => $influenceur->country,
                    'language'   => $influenceur->language,
                    'status'     => 'scraping',
                    'notes'      => 'Auto-détecté depuis scraping contact #' . $influenceur->id,
                    'created_by' => $influenceur->created_by,
                ]);
            }

            $directoryScraper = app(DirectoryScraperService::class);
            $result = $directoryScraper->scrapeDirectory($url, $contactType, $influenceur->country);

            $created = 0;
            $skipped = 0;

            if ($result['success'] && !empty($result['contacts'])) {
                foreach ($result['contacts'] as $contactData) {
                    // Skip contacts with the same name as the original (avoid self-duplication)
                    if (strtolower(trim($contactData['name'])) === strtolower(trim($influenceur->name))) {
                        continue;
                    }

                    // Check if contact already exists (by name + country + type)
                    $exists = Influenceur::where('name', $contactData['name'])
                        ->where('contact_type', $contactType)
                        ->where('country', $influenceur->country)
                        ->exists();

                    if ($exists) {
                        $skipped++;
                        continue;
                    }

                    // Also check by website URL if available
                    if (!empty($contactData['website_url'])) {
                        $existsByUrl = Influenceur::where('website_url', $contactData['website_url'])
                            ->where('contact_type', $contactType)
                            ->exists();

                        if ($existsByUrl) {
                            $skipped++;
                            continue;
                        }
                    }

                    // Create new contact from directory data
                    Influenceur::create([
                        'name'         => $contactData['name'],
                        'contact_type' => $contactType,
                        'country'      => $contactData['country'] ?? $influenceur->country,
                        'language'     => $influenceur->language ?? 'fr',
                        'email'        => $contactData['email'] ?? null,
                        'phone'        => $contactData['phone'] ?? null,
                        'website_url'  => $contactData['website_url'] ?? null,
                        'source'       => 'directory:' . parse_url($url, PHP_URL_HOST),
                        'status'       => 'prospect',
                        'created_by'   => $influenceur->created_by,
                        'notes'        => 'Auto-extracted from directory: ' . $url,
                    ]);

                    $created++;
                }
            }

            // Update directory record with results
            $dirRecord = Directory::where('domain', $domain)
                ->where('category', $contactType)
                ->first();
            if ($dirRecord) {
                $dirRecord->update([
                    'status'             => $result['success'] ? 'completed' : 'failed',
                    'contacts_extracted' => count($result['contacts']),
                    'contacts_created'   => $dirRecord->contacts_created + $created,
                    'pages_scraped'      => $result['pages_scraped'],
                    'last_scraped_at'    => now(),
                    'cooldown_until'     => now()->addHours(24),
                ]);
            }

            // Mark original contact as directory-scraped
            $influenceur->update([
                'scraped_at'     => now(),
                'scraper_status' => 'directory_scraped',
                'scraped_social' => [
                    '_directory_extraction' => [
                        'source_url'      => $url,
                        'contacts_found'  => count($result['contacts']),
                        'contacts_created' => $created,
                        'contacts_skipped' => $skipped,
                        'pages_scraped'   => $result['pages_scraped'],
                    ],
                ],
            ]);

            ActivityLog::create([
                'user_id'        => $influenceur->created_by,
                'influenceur_id' => $influenceur->id,
                'action'         => 'directory_scraped',
                'details'        => [
                    'url'              => $url,
                    'contacts_found'   => count($result['contacts']),
                    'contacts_created' => $created,
                    'contacts_skipped' => $skipped,
                    'pages_scraped'    => $result['pages_scraped'],
                    'error'            => $result['error'],
                ],
                'contact_type' => $contactType,
            ]);

            Log::info('ScrapeContactJob: directory extraction complete', [
                'id'               => $influenceur->id,
                'url'              => $url,
                'contacts_found'   => count($result['contacts']),
                'contacts_created' => $created,
                'contacts_skipped' => $skipped,
            ]);

        } catch (\Throwable $e) {
            $this->markStatus($influenceur, 'failed');

            ActivityLog::create([
                'user_id'        => $influenceur->created_by,
                'influenceur_id' => $influenceur->id,
                'action'         => 'directory_scrape_failed',
                'details'        => [
                    'url'   => $url,
                    'error' => substr($e->getMessage(), 0, 500),
                ],
                'contact_type' => $contactType,
            ]);

            Log::error('ScrapeContactJob: directory scraping exception', [
                'id'    => $influenceur->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Update scraper status and timestamp without overwriting other fields.
     */
    private function markStatus(Influenceur $influenceur, string $status): void
    {
        $influenceur->update([
            'scraped_at'     => now(),
            'scraper_status' => $status,
        ]);
    }

    /**
     * Find the best email matching a given site domain from a list of scraped emails.
     */
    private function findBestEmailForDomain(array $emails, ?string $siteDomain): ?string
    {
        if (!$siteDomain || empty($emails)) return null;

        $siteDomain = strtolower($siteDomain);

        // Junk patterns to skip
        $junk = ['noreply', 'no-reply', 'donotreply', 'postmaster', 'webmaster', 'dpo@', 'abuse@',
            'flywheel.local', 'localhost', 'example.com', 'sentry.io', 'wixpress.com'];

        foreach ($emails as $email) {
            $email = strtolower(trim($email));
            $domain = substr($email, strpos($email, '@') + 1);

            // Skip junk
            $isJunk = false;
            foreach ($junk as $j) {
                if (str_contains($email, $j)) { $isJunk = true; break; }
            }
            if ($isJunk) continue;

            // Check domain match (exact or subdomain)
            $rootSite = $this->extractRootDomain($siteDomain);
            $rootEmail = $this->extractRootDomain($domain);

            if ($rootSite === $rootEmail) {
                return $email;
            }
        }

        return null;
    }

    private function extractRootDomain(string $domain): string
    {
        $parts = explode('.', $domain);
        $count = count($parts);
        if ($count <= 2) return $domain;

        $twoPartTlds = ['co.uk', 'co.jp', 'co.kr', 'co.th', 'ac.th', 'ac.uk',
            'com.au', 'com.br', 'com.mx', 'org.uk', 'co.za', 'co.in',
            'com.sg', 'com.my', 'com.ph', 'co.nz', 'or.jp', 'asso.fr'];
        $lastTwo = $parts[$count - 2] . '.' . $parts[$count - 1];
        if (in_array($lastTwo, $twoPartTlds) && $count > 2) {
            return $parts[$count - 3] . '.' . $lastTwo;
        }
        return $parts[$count - 2] . '.' . $parts[$count - 1];
    }
}
