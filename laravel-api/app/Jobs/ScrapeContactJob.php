<?php

namespace App\Jobs;

use App\Models\ActivityLog;
use App\Models\ContactTypeModel;
use App\Models\Influenceur;
use App\Models\Setting;
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

    public int $timeout = 60;
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

        // Detect directory/aggregator URLs that are NOT the actual website
        // These happen when AI research returns aefe.fr, lepetitjournal.com, etc.
        $directoryDomains = [
            'aefe.fr', 'aefe.gouv.fr', 'mlfmonde.org',
            'lepetitjournal.com', 'thailandee.com', 'vivre-en-',
            'odyssey.education', 'education.gouv.fr', 'wikipedia.org',
        ];
        $isDirectory = false;
        if (!empty($url)) {
            $urlLower = strtolower($url);
            foreach ($directoryDomains as $dd) {
                if (str_contains($urlLower, $dd)) {
                    $isDirectory = true;
                    break;
                }
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

            // Only fill NULL/empty fields — NEVER overwrite existing data
            if (empty($influenceur->email) && !empty($result['emails']) && !$isSuspiciousAggregator) {
                $updateData['email'] = $result['emails'][0];
            }

            // Suggested emails are stored for display only — NEVER auto-fill
            // to avoid sending to invalid addresses and getting our domain blacklisted

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
     * Update scraper status and timestamp without overwriting other fields.
     */
    private function markStatus(Influenceur $influenceur, string $status): void
    {
        $influenceur->update([
            'scraped_at'     => now(),
            'scraper_status' => $status,
        ]);
    }
}
