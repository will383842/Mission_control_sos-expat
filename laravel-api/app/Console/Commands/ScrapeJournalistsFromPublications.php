<?php

namespace App\Console\Commands;

use App\Models\PressContact;
use App\Models\PressPublication;
use App\Services\PublicationBylinesScraperService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Scrape individual journalists from publications that have an authors_url AND email_pattern.
 * For each journalist name found, infer the email using the publication's email pattern.
 *
 * Usage:
 *   php artisan press:scrape-journalists                  # All 73 publications
 *   php artisan press:scrape-journalists --limit=10       # First 10 only
 *   php artisan press:scrape-journalists --publication=5  # Specific publication ID
 *   php artisan press:scrape-journalists --dry-run        # Preview without saving
 */
class ScrapeJournalistsFromPublications extends Command
{
    protected $signature = 'press:scrape-journalists
        {--limit=0 : Max publications to process (0 = all)}
        {--publication= : Specific publication ID}
        {--dry-run : Preview only, do not save}
        {--skip-existing : Skip publications already scraped}';

    protected $description = 'Scrape journalists from publications with authors_url + email_pattern and infer emails';

    private int $totalNew = 0;
    private int $totalSkipped = 0;
    private int $totalErrors = 0;

    public function handle(): int
    {
        $scraper = new PublicationBylinesScraperService();

        // Get publications with both authors_url and email_pattern
        $query = PressPublication::query()
            ->whereNotNull('authors_url')
            ->where('authors_url', '!=', '')
            ->whereNotNull('email_pattern')
            ->where('email_pattern', '!=', '')
            ->orderByRaw("CASE WHEN email_domain IN ('lemonde.fr','lefigaro.fr','liberation.fr','20minutes.fr','leparisien.fr','bfmtv.com','france24.com','rfi.fr','radiofrance.fr','lesechos.fr') THEN 0 ELSE 1 END")
            ->orderBy('name');

        if ($pubId = $this->option('publication')) {
            $query->where('id', $pubId);
        }

        if ($this->option('skip-existing')) {
            $query->where(function ($q) {
                $q->whereNull('last_scraped_at')
                  ->orWhere('authors_discovered', 0);
            });
        }

        $publications = $query->get();

        if ($limit = (int) $this->option('limit')) {
            $publications = $publications->take($limit);
        }

        $this->info("Found {$publications->count()} publications with authors_url + email_pattern");
        $this->newLine();

        $isDryRun = $this->option('dry-run');
        if ($isDryRun) {
            $this->warn('DRY RUN — no data will be saved');
            $this->newLine();
        }

        foreach ($publications as $i => $pub) {
            $num = $i + 1;
            $this->info("[{$num}/{$publications->count()}] {$pub->name} ({$pub->email_domain})");
            $this->line("  Authors URL: {$pub->authors_url}");
            $this->line("  Pattern: {$pub->email_pattern}");

            try {
                // Phase 1: Scrape author index
                $result = $scraper->scrapeAuthorIndex($pub);

                if (!empty($result['error'])) {
                    $this->error("  Error: {$result['error']}");
                    $pub->update(['last_error' => $result['error'], 'status' => 'error']);
                    $this->totalErrors++;
                    continue;
                }

                $authors = $result['authors'];
                $this->line("  Found {$result['pages_scraped']} page(s), " . count($authors) . " author(s)");

                if (empty($authors)) {
                    // Phase 2: Try article bylines as fallback
                    $this->line("  Trying article bylines as fallback...");
                    $bylinesResult = $scraper->scrapeArticleBylines($pub, 3);
                    $authors = $bylinesResult['authors'];
                    $this->line("  Bylines: {$bylinesResult['articles_scraped']} page(s), " . count($authors) . " author(s)");
                }

                if (empty($authors)) {
                    $this->warn("  No authors found, skipping");
                    $pub->update(['status' => 'no_authors', 'last_scraped_at' => now()]);
                    continue;
                }

                // Phase 3: Infer emails using pattern
                $withEmails = 0;
                foreach ($authors as &$author) {
                    if (!empty($author['email'])) {
                        $withEmails++;
                        continue;
                    }

                    $inferred = $this->inferEmail($author['full_name'], $pub->email_pattern);
                    if ($inferred) {
                        $author['email'] = $inferred;
                        $author['email_source'] = 'inferred';
                        $withEmails++;
                    }
                }
                unset($author);

                $this->info("  Emails: {$withEmails}/" . count($authors) . " (inferred from pattern)");

                if ($isDryRun) {
                    foreach (array_slice($authors, 0, 5) as $a) {
                        $this->line("    - {$a['full_name']} → " . ($a['email'] ?? 'NO EMAIL'));
                    }
                    if (count($authors) > 5) {
                        $this->line("    ... and " . (count($authors) - 5) . " more");
                    }
                    $this->totalNew += count($authors);
                    continue;
                }

                // Phase 4: Save — the PressContactObserver will auto-sync to BL Engine
                $saved = $this->saveAuthorsWithInferredEmails($pub, $authors, $scraper);
                $this->info("  Saved: {$saved} new contacts");
                $this->totalNew += $saved;

            } catch (\Throwable $e) {
                $this->error("  Exception: {$e->getMessage()}");
                $pub->update(['last_error' => substr($e->getMessage(), 0, 500), 'status' => 'error']);
                $this->totalErrors++;
            }

            // Delay between publications to be polite
            if ($i < $publications->count() - 1) {
                usleep(random_int(2000000, 4000000));
            }
        }

        $this->newLine();
        $this->info("═══════════════════════════════════");
        $this->info("DONE — New: {$this->totalNew} | Skipped: {$this->totalSkipped} | Errors: {$this->totalErrors}");
        $this->info("Total press_contacts: " . PressContact::count());
        $this->info("With email: " . PressContact::whereNotNull('email')->count());
        $this->info("═══════════════════════════════════");

        return Command::SUCCESS;
    }

    /**
     * Infer an email from a full name and an email pattern.
     *
     * Supported tokens:
     *   {first}  → prénom normalisé (jean-pierre → jean-pierre)
     *   {last}   → nom normalisé (de la fontaine → delafontaine or de-la-fontaine)
     *   {f}      → initiale prénom (j)
     *   {fl}     → initiale prénom + nom (jdupont)
     */
    private function inferEmail(string $fullName, string $pattern): ?string
    {
        $parts = $this->splitNameForEmail($fullName);
        if (!$parts) return null;

        $first = $this->normalizeForEmail($parts['first']);
        $last  = $this->normalizeForEmail($parts['last']);

        if (!$first || !$last) return null;

        // Determine separator from pattern context
        // e.g. {first}.{last} → separator is "."
        // e.g. {first}-{last} → separator is "-"
        // e.g. {first}{last} → no separator
        $email = $pattern;
        $email = str_replace('{first}', $first, $email);
        $email = str_replace('{last}', $last, $email);
        $email = str_replace('{f}', mb_substr($first, 0, 1), $email);
        $email = str_replace('{fl}', mb_substr($first, 0, 1) . $last, $email);

        // Validate the generated email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return strtolower($email);
    }

    /**
     * Split a full name into first and last, handling French compound names.
     */
    private function splitNameForEmail(string $fullName): ?array
    {
        $name = trim($fullName);
        $words = preg_split('/\s+/', $name);

        if (count($words) < 2) return null;

        // Handle common French patterns:
        // "Jean-Pierre Dupont" → first=jean-pierre, last=dupont
        // "Marie de la Fontaine" → first=marie, last=delafontaine
        // "Pierre-André Le Goff" → first=pierre-andré, last=legoff

        $first = $words[0];

        // Check for compound last names with particles
        $particles = ['de', 'du', 'des', 'la', 'le', 'les', 'von', 'van', 'el', 'al', 'ben', 'di'];
        $lastParts = array_slice($words, 1);

        // Merge particles into last name
        $last = implode('', array_map(function ($w) use ($particles) {
            // Keep particles but join them (de la fontaine → delafontaine)
            return $w;
        }, $lastParts));

        // Alternative: keep natural spacing for last name, remove spaces
        $last = implode('', array_slice($words, 1));

        return ['first' => $first, 'last' => implode(' ', array_slice($words, 1))];
    }

    /**
     * Normalize a name part for email: remove accents, lowercase, handle hyphens.
     */
    private function normalizeForEmail(string $name): string
    {
        // Remove accents
        $name = $this->removeAccents($name);
        // Lowercase
        $name = strtolower($name);
        // Remove spaces (for compound names like "de la fontaine" → "delafontaine")
        $name = str_replace(' ', '', $name);
        // Keep hyphens (jean-pierre stays jean-pierre)
        // Remove any other special characters
        $name = preg_replace('/[^a-z0-9\-]/', '', $name);

        return $name;
    }

    /**
     * Remove French accents from a string.
     */
    private function removeAccents(string $str): string
    {
        $map = [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ï' => 'i', 'î' => 'i', 'í' => 'i', 'ì' => 'i',
            'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'ò' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u',
            'ç' => 'c', 'ñ' => 'n', 'œ' => 'oe', 'æ' => 'ae',
            'À' => 'a', 'Â' => 'a', 'Ä' => 'a', 'Á' => 'a', 'Ã' => 'a',
            'É' => 'e', 'È' => 'e', 'Ê' => 'e', 'Ë' => 'e',
            'Ï' => 'i', 'Î' => 'i', 'Í' => 'i', 'Ì' => 'i',
            'Ô' => 'o', 'Ö' => 'o', 'Ó' => 'o', 'Ò' => 'o',
            'Ù' => 'u', 'Û' => 'u', 'Ü' => 'u', 'Ú' => 'u',
            'Ç' => 'c', 'Ñ' => 'n', 'Œ' => 'oe', 'Æ' => 'ae',
        ];
        return strtr($str, $map);
    }

    /**
     * Save authors with inferred emails.
     * Uses the scraper's saveAuthors but first attaches email_source metadata.
     */
    private function saveAuthorsWithInferredEmails(PressPublication $pub, array $authors, PublicationBylinesScraperService $scraper): int
    {
        $saved = 0;

        foreach ($authors as $author) {
            if (empty($author['full_name'])) continue;

            $fullName = trim($author['full_name']);
            if (strlen($fullName) < 4 || strlen($fullName) > 70) continue;

            // Check uniqueness by name+publication or email
            $exists = PressContact::where('publication_id', $pub->id)
                ->where(function ($q) use ($author) {
                    if (!empty($author['email'])) {
                        $q->where('email', $author['email']);
                    } else {
                        $q->where('full_name', $author['full_name']);
                    }
                })->exists();

            if ($exists) {
                $this->totalSkipped++;
                continue;
            }

            $parts = $this->splitNameForEmail($fullName);
            $firstName = $parts ? $parts['first'] : null;
            $lastName = $parts ? $parts['last'] : $fullName;

            PressContact::create([
                'publication_id' => $pub->id,
                'publication'    => $pub->name,
                'full_name'      => $fullName,
                'first_name'     => $firstName,
                'last_name'      => $lastName,
                'email'          => $author['email'] ?? null,
                'email_source'   => isset($author['email_source']) ? $author['email_source'] : ($author['email'] ? 'scraped' : null),
                'phone'          => $author['phone'] ?? null,
                'role'           => $author['role'] ?? null,
                'beat'           => $author['beat'] ?? null,
                'media_type'     => $pub->media_type,
                'source_url'     => $author['source_url'] ?? $pub->authors_url,
                'profile_url'    => $author['profile_url'] ?? null,
                'twitter'        => $author['twitter'] ?? null,
                'linkedin'       => $author['linkedin'] ?? null,
                'country'        => $pub->country,
                'language'       => $pub->language,
                'topics'         => $pub->topics,
                'contact_status' => 'new',
                'scraped_from'   => $pub->slug ?? Str::slug($pub->name),
                'scraped_at'     => now(),
            ]);
            $saved++;

            // Small delay to respect webhook rate limit (Observer sends to BL Engine)
            if ($author['email'] ?? null) {
                usleep(200000); // 200ms between webhook calls
            }
        }

        // Update publication stats
        $total = PressContact::where('publication_id', $pub->id)->count();
        $withEmail = PressContact::where('publication_id', $pub->id)->whereNotNull('email')->count();
        $pub->update([
            'authors_discovered' => $total,
            'emails_inferred'    => $withEmail,
            'contacts_count'     => $total,
            'last_scraped_at'    => now(),
            'status'             => 'scraped',
            'last_error'         => null,
        ]);

        return $saved;
    }
}
