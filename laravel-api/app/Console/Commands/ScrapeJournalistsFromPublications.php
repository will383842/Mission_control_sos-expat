<?php

namespace App\Console\Commands;

use App\Models\PressContact;
use App\Models\PressPublication;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Scrape individual journalists from publications with email_pattern.
 * Multi-strategy: RSS feeds, author sitemaps, HTML pages, article bylines.
 *
 * Usage:
 *   php artisan press:scrape-journalists                  # All publications with email_pattern
 *   php artisan press:scrape-journalists --limit=10       # First 10 only
 *   php artisan press:scrape-journalists --publication=5  # Specific publication ID
 *   php artisan press:scrape-journalists --dry-run        # Preview without saving
 *   php artisan press:scrape-journalists --infer-existing # Only infer emails for existing contacts
 */
class ScrapeJournalistsFromPublications extends Command
{
    protected $signature = 'press:scrape-journalists
        {--limit=0 : Max publications to process (0 = all)}
        {--publication= : Specific publication ID}
        {--dry-run : Preview only, do not save}
        {--infer-existing : Only infer emails for existing contacts without email}';

    protected $description = 'Scrape journalists from publications and infer emails from patterns (RSS + sitemap + HTML)';

    private const TIMEOUT = 15;
    private const USER_AGENTS = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.2 Safari/605.1.15',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:133.0) Gecko/20100101 Firefox/133.0',
    ];

    private int $totalNew = 0;
    private int $totalSkipped = 0;
    private int $totalInferred = 0;
    private int $totalErrors = 0;

    public function handle(): int
    {
        // Phase 0: Infer emails for existing contacts first
        $this->inferExistingContacts();

        if ($this->option('infer-existing')) {
            return Command::SUCCESS;
        }

        // Phase 1: Scrape new journalists from all publications with email_pattern
        $this->scrapeAllPublications();

        $this->newLine();
        $this->info('=======================================');
        $this->info("DONE");
        $this->info("  New contacts:    {$this->totalNew}");
        $this->info("  Emails inferred: {$this->totalInferred}");
        $this->info("  Skipped (dupes): {$this->totalSkipped}");
        $this->info("  Errors:          {$this->totalErrors}");
        $this->info("  Total contacts:  " . PressContact::count());
        $this->info("  With email:      " . PressContact::whereNotNull('email')->count());
        $this->info('=======================================');

        return Command::SUCCESS;
    }

    /**
     * Infer emails for existing press_contacts that have a name but no email,
     * and whose publication has an email_pattern.
     */
    private function inferExistingContacts(): void
    {
        $this->info('--- Phase 0: Infer emails for existing contacts ---');

        $pubs = PressPublication::whereNotNull('email_pattern')
            ->where('email_pattern', '!=', '')
            ->pluck('email_pattern', 'id');

        $count = 0;
        $isDryRun = $this->option('dry-run');

        PressContact::whereNull('email')
            ->whereIn('publication_id', $pubs->keys())
            ->whereNotNull('first_name')
            ->whereNotNull('last_name')
            ->chunk(50, function ($contacts) use ($pubs, &$count, $isDryRun) {
                foreach ($contacts as $contact) {
                    $pattern = $pubs[$contact->publication_id] ?? null;
                    if (!$pattern) continue;

                    $email = $this->inferEmail($contact->full_name, $pattern);
                    if (!$email) continue;

                    if ($isDryRun) {
                        $this->line("  [DRY] {$contact->full_name} → {$email}");
                    } else {
                        $contact->update([
                            'email' => $email,
                            'email_source' => 'inferred',
                        ]);
                        // Small delay for webhook (Observer fires on update if email changed)
                        usleep(300000);
                    }
                    $count++;
                    $this->totalInferred++;
                }
            });

        $this->info("  Inferred {$count} emails for existing contacts");
        $this->newLine();
    }

    /**
     * Main scraping loop: for each publication with email_pattern, try multiple strategies.
     */
    private function scrapeAllPublications(): void
    {
        $query = PressPublication::query()
            ->whereNotNull('email_pattern')
            ->where('email_pattern', '!=', '')
            ->orderBy('name');

        if ($pubId = $this->option('publication')) {
            $query->where('id', $pubId);
        }

        $publications = $query->get();

        if ($limit = (int) $this->option('limit')) {
            $publications = $publications->take($limit);
        }

        $this->info("--- Phase 1: Scraping {$publications->count()} publications ---");
        $this->newLine();

        $isDryRun = $this->option('dry-run');

        foreach ($publications as $i => $pub) {
            $num = $i + 1;
            $this->info("[{$num}/{$publications->count()}] {$pub->name} ({$pub->email_domain})");

            try {
                $authors = $this->scrapePublication($pub);

                if (empty($authors)) {
                    $this->warn("  No authors found");
                    continue;
                }

                // Infer emails for authors without one
                $withEmails = 0;
                foreach ($authors as &$author) {
                    if (!empty($author['email'])) {
                        $withEmails++;
                        continue;
                    }
                    if ($pub->email_pattern) {
                        $inferred = $this->inferEmail($author['full_name'], $pub->email_pattern);
                        if ($inferred) {
                            $author['email'] = $inferred;
                            $author['email_source'] = 'inferred';
                            $withEmails++;
                        }
                    }
                }
                unset($author);

                $this->info("  Found " . count($authors) . " authors, {$withEmails} with email");

                if ($isDryRun) {
                    foreach (array_slice($authors, 0, 5) as $a) {
                        $this->line("    - {$a['full_name']} → " . ($a['email'] ?? 'NO EMAIL'));
                    }
                    if (count($authors) > 5) {
                        $this->line("    ... +" . (count($authors) - 5) . " more");
                    }
                    continue;
                }

                $saved = $this->saveAuthors($pub, $authors);
                $this->info("  Saved: {$saved} new contacts");
                $this->totalNew += $saved;

            } catch (\Throwable $e) {
                $this->error("  Error: " . substr($e->getMessage(), 0, 200));
                $this->totalErrors++;
            }

            // Polite delay between publications
            if ($i < $publications->count() - 1) {
                usleep(random_int(1500000, 3000000));
            }
        }
    }

    /**
     * Try multiple strategies to extract journalist names from a publication.
     * Returns array of ['full_name' => ..., 'email' => ..., 'source_url' => ...]
     */
    private function scrapePublication(PressPublication $pub): array
    {
        $base = rtrim($pub->base_url, '/');
        $authors = [];

        // Strategy 1: RSS feed (most reliable for WordPress sites)
        $rssAuthors = $this->extractFromRss($base);
        if (!empty($rssAuthors)) {
            $this->line("  [RSS] " . count($rssAuthors) . " authors");
            $authors = array_merge($authors, $rssAuthors);
        }

        // Strategy 2: Author sitemap (WordPress sites)
        $sitemapAuthors = $this->extractFromAuthorSitemap($base);
        if (!empty($sitemapAuthors)) {
            $this->line("  [SITEMAP] " . count($sitemapAuthors) . " authors");
            $authors = array_merge($authors, $sitemapAuthors);
        }

        // Strategy 3: Authors URL (HTML scraping)
        if ($pub->authors_url) {
            $htmlAuthors = $this->extractFromHtmlPage($pub->authors_url, $base);
            if (!empty($htmlAuthors)) {
                $this->line("  [HTML] " . count($htmlAuthors) . " authors");
                $authors = array_merge($authors, $htmlAuthors);
            }
        }

        // Strategy 4: Article pages (scrape homepage for article links, visit them for bylines)
        if (count($authors) < 5) {
            $articleAuthors = $this->extractFromArticles($base, $pub->articles_url);
            if (!empty($articleAuthors)) {
                $this->line("  [ARTICLES] " . count($articleAuthors) . " authors");
                $authors = array_merge($authors, $articleAuthors);
            }
        }

        // Deduplicate by normalized name
        return $this->deduplicateAuthors($authors);
    }

    /**
     * Strategy 1: Extract author names from RSS feed.
     */
    private function extractFromRss(string $baseUrl): array
    {
        $rssPaths = ['/feed/', '/rss/', '/rss.xml', '/feeds/rss-une.xml', '/flux-rss/'];
        $authors = [];

        foreach ($rssPaths as $path) {
            $html = $this->fetch($baseUrl . $path);
            if (!$html || (!str_contains($html, '<rss') && !str_contains($html, '<feed'))) continue;

            // dc:creator with CDATA
            preg_match_all('/<dc:creator><!\[CDATA\[(.+?)\]\]><\/dc:creator>/', $html, $m1);
            // dc:creator without CDATA
            preg_match_all('/<dc:creator>([^<]+)<\/dc:creator>/', $html, $m2);
            // <author><name>
            preg_match_all('/<author>\s*<name>([^<]+)<\/name>/i', $html, $m3);

            $names = array_unique(array_merge($m1[1] ?? [], $m2[1] ?? [], $m3[1] ?? []));

            foreach ($names as $name) {
                $name = html_entity_decode(trim($name), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if ($this->isValidName($name)) {
                    $authors[] = ['full_name' => $name, 'email' => null, 'source_url' => $baseUrl . $path];
                }
            }

            if (!empty($authors)) break; // Got results, no need to try other paths
        }

        return $authors;
    }

    /**
     * Strategy 2: Extract author names from WordPress author sitemap.
     */
    private function extractFromAuthorSitemap(string $baseUrl): array
    {
        $sitemapPaths = ['/author-sitemap.xml', '/sitemap-authors.xml', '/sitemap_authors.xml'];
        $authors = [];

        foreach ($sitemapPaths as $path) {
            $xml = $this->fetch($baseUrl . $path);
            if (!$xml || !str_contains($xml, '<urlset')) continue;

            preg_match_all('/<loc>([^<]+)<\/loc>/', $xml, $m);
            foreach ($m[1] ?? [] as $url) {
                // Extract name from URL slug: /auteur/jean-dupont/ → Jean Dupont
                if (preg_match('/\/(?:auteur|author|profil)\/([^\/]+)\/?$/i', $url, $sm)) {
                    $slug = $sm[1];
                    $name = $this->slugToName($slug);
                    if ($this->isValidName($name)) {
                        $authors[] = ['full_name' => $name, 'email' => null, 'source_url' => $url, 'profile_url' => $url];
                    }
                }
            }

            if (!empty($authors)) break;
        }

        return $authors;
    }

    /**
     * Strategy 3: Extract author names from HTML page (authors_url, team page, etc.)
     */
    private function extractFromHtmlPage(string $url, string $baseUrl): array
    {
        $html = $this->fetch($url);
        if (!$html || strlen($html) < 2000) return [];

        $authors = [];
        $domain = $this->extractDomain($baseUrl);

        // Method A: Links containing /auteur/, /author/, /journaliste/, /profil/, /bio/
        $linkPatterns = ['/auteur/', '/author/', '/journaliste/', '/reporters/', '/profil/', '/bio/', '/redacteur/'];
        foreach ($linkPatterns as $pattern) {
            preg_match_all('/<a[^>]+href=["\']([^"\']*' . preg_quote($pattern, '/') . '[^"\']*)["\'][^>]*>([^<]{3,60})<\/a>/i', $html, $m);
            foreach (($m[2] ?? []) as $i => $text) {
                $name = html_entity_decode(trim(strip_tags($text)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if ($this->isValidName($name)) {
                    $href = $m[1][$i] ?? '';
                    if (!str_starts_with($href, 'http')) {
                        $href = rtrim($baseUrl, '/') . '/' . ltrim($href, '/');
                    }
                    $authors[] = ['full_name' => $name, 'email' => null, 'source_url' => $url, 'profile_url' => $href];
                }
            }
        }

        // Method B: JSON-LD Person objects
        preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $html, $ld);
        foreach ($ld[1] ?? [] as $json) {
            $data = @json_decode($json, true);
            if (!$data) continue;
            $items = isset($data['@graph']) ? $data['@graph'] : [$data];
            foreach ($items as $item) {
                $type = is_array($item['@type'] ?? '') ? ($item['@type'][0] ?? '') : ($item['@type'] ?? '');
                if (strtolower($type) !== 'person') continue;
                $name = $item['name'] ?? '';
                if ($this->isValidName($name)) {
                    $email = isset($item['email']) ? str_replace('mailto:', '', $item['email']) : null;
                    $authors[] = ['full_name' => $name, 'email' => $email, 'source_url' => $url, 'profile_url' => $item['url'] ?? null];
                }
            }
        }

        // Method C: Generic class-based extraction
        preg_match_all(
            '/<(?:h[1-6]|p|span|div|a)[^>]*class="[^"]*(?:author|journaliste|reporter|redacteur|chroniqueur|equipe-member|team-member)[^"]*"[^>]*>([^<]{4,60})</i',
            $html, $m
        );
        foreach (($m[1] ?? []) as $name) {
            $name = html_entity_decode(trim($name), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($this->isValidName($name)) {
                $authors[] = ['full_name' => $name, 'email' => null, 'source_url' => $url];
            }
        }

        // Method D: mailto: links
        preg_match_all('/<a[^>]+href="mailto:([^"@\s]+@[^"]+)"[^>]*>([^<]{3,60})<\/a>/i', $html, $m);
        foreach (($m[1] ?? []) as $i => $email) {
            $name = html_entity_decode(trim(strip_tags($m[2][$i] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($this->isValidName($name)) {
                $authors[] = ['full_name' => $name, 'email' => strtolower($email), 'source_url' => $url];
            }
        }

        return $authors;
    }

    /**
     * Strategy 4: Scrape article pages from sitemap to extract author bylines.
     */
    private function extractFromArticles(string $baseUrl, ?string $articlesUrl): array
    {
        $authors = [];

        // Get article URLs from sitemap
        $articleUrls = $this->getArticleUrlsFromSitemap($baseUrl);

        // If no sitemap, try homepage
        if (empty($articleUrls) && $articlesUrl) {
            $html = $this->fetch($articlesUrl);
            if ($html) {
                preg_match_all('/<a[^>]+href=["\'](' . preg_quote(rtrim($baseUrl, '/'), '/') . '\/[^"\']{30,200})["\']/', $html, $m);
                $articleUrls = array_unique(array_slice($m[1] ?? [], 0, 10));
            }
        }

        // Visit up to 8 articles and extract author names
        foreach (array_slice($articleUrls, 0, 8) as $artUrl) {
            $html = $this->fetch($artUrl);
            if (!$html) continue;

            // JSON-LD author
            preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $html, $ld);
            foreach ($ld[1] ?? [] as $json) {
                $data = @json_decode($json, true);
                if (!$data) continue;
                $items = isset($data['@graph']) ? $data['@graph'] : [$data];
                foreach ($items as $item) {
                    if (!isset($item['author'])) continue;
                    $authorList = isset($item['author']['name']) ? [$item['author']] : ($item['author'] ?? []);
                    foreach ($authorList as $a) {
                        $n = is_string($a) ? $a : ($a['name'] ?? '');
                        if ($this->isValidName($n)) {
                            $authors[] = ['full_name' => $n, 'email' => null, 'source_url' => $artUrl];
                        }
                    }
                }
            }

            // Meta author
            preg_match_all('/<meta[^>]+name=["\']author["\'][^>]+content=["\']([^"\']{4,60})["\']/', $html, $m);
            foreach ($m[1] ?? [] as $name) {
                $name = html_entity_decode(trim($name), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if ($this->isValidName($name)) {
                    $authors[] = ['full_name' => $name, 'email' => null, 'source_url' => $artUrl];
                }
            }

            // rel="author" links
            preg_match_all('/<a[^>]+rel=["\']author["\'][^>]*>([^<]{3,60})<\/a>/i', $html, $m);
            foreach ($m[1] ?? [] as $name) {
                $name = html_entity_decode(trim($name), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if ($this->isValidName($name)) {
                    $authors[] = ['full_name' => $name, 'email' => null, 'source_url' => $artUrl];
                }
            }

            usleep(random_int(800000, 1500000));
        }

        return $authors;
    }

    /**
     * Get article URLs from sitemap (first 20).
     */
    private function getArticleUrlsFromSitemap(string $baseUrl): array
    {
        $sitemapUrl = $baseUrl . '/sitemap.xml';
        $xml = $this->fetch($sitemapUrl);
        if (!$xml) return [];

        $urls = [];

        // Check if it's a sitemap index
        if (str_contains($xml, '<sitemapindex')) {
            preg_match_all('/<loc>([^<]+)<\/loc>/', $xml, $m);
            // Find the most recent post/article sitemap
            foreach ($m[1] ?? [] as $subUrl) {
                if (preg_match('/post|article|news|actu/i', $subUrl)) {
                    $subXml = $this->fetch($subUrl);
                    if ($subXml) {
                        preg_match_all('/<loc>([^<]+)<\/loc>/', $subXml, $sm);
                        $urls = array_slice($sm[1] ?? [], 0, 20);
                    }
                    break;
                }
            }
            // If no post sitemap found, try first sub-sitemap
            if (empty($urls) && !empty($m[1])) {
                $subXml = $this->fetch($m[1][0]);
                if ($subXml) {
                    preg_match_all('/<loc>([^<]+)<\/loc>/', $subXml, $sm);
                    $urls = array_slice($sm[1] ?? [], 0, 20);
                }
            }
        } else {
            preg_match_all('/<loc>([^<]+)<\/loc>/', $xml, $m);
            $urls = array_slice($m[1] ?? [], 0, 20);
        }

        return $urls;
    }

    // ═══════════════════════════════════════════════════════════════════
    // Email inference
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Infer email from name + pattern.
     * Tokens: {first}, {last}, {f} (initial), {fl} (initial+last)
     */
    private function inferEmail(string $fullName, string $pattern): ?string
    {
        $parts = $this->splitName($fullName);
        if (!$parts) return null;

        $first = $this->normalizeForEmail($parts['first']);
        $last  = $this->normalizeForEmail($parts['last']);

        if (!$first || !$last || strlen($first) < 1 || strlen($last) < 2) return null;

        $email = $pattern;
        $email = str_replace('{first}', $first, $email);
        $email = str_replace('{last}', $last, $email);
        $email = str_replace('{f}', substr($first, 0, 1), $email);
        $email = str_replace('{fl}', substr($first, 0, 1) . $last, $email);

        $email = strtolower($email);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return null;

        return $email;
    }

    private function splitName(string $fullName): ?array
    {
        $name = trim($fullName);
        $words = preg_split('/\s+/', $name);
        if (count($words) < 2) return null;

        return [
            'first' => $words[0],
            'last'  => implode(' ', array_slice($words, 1)),
        ];
    }

    private function normalizeForEmail(string $name): string
    {
        // Transliterate accents
        $name = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $name) ?? strtolower($name);
        // Remove spaces (compound names: de la fontaine → delafontaine)
        $name = str_replace(' ', '', $name);
        // Keep hyphens, remove everything else except alphanumeric
        $name = preg_replace('/[^a-z0-9\-]/', '', $name);
        return $name;
    }

    // ═══════════════════════════════════════════════════════════════════
    // Persistence
    // ═══════════════════════════════════════════════════════════════════

    private function saveAuthors(PressPublication $pub, array $authors): int
    {
        $saved = 0;

        foreach ($authors as $author) {
            if (empty($author['full_name'])) continue;

            $fullName = trim($author['full_name']);

            // Check uniqueness
            $existsQuery = PressContact::where('publication_id', $pub->id);
            if (!empty($author['email'])) {
                $existsQuery->where(function ($q) use ($author, $fullName) {
                    $q->where('email', $author['email'])->orWhere('full_name', $fullName);
                });
            } else {
                $existsQuery->where('full_name', $fullName);
            }

            if ($existsQuery->exists()) {
                $this->totalSkipped++;
                continue;
            }

            $parts = $this->splitName($fullName);

            PressContact::create([
                'publication_id' => $pub->id,
                'publication'    => $pub->name,
                'full_name'      => $fullName,
                'first_name'     => $parts['first'] ?? null,
                'last_name'      => $parts['last'] ?? $fullName,
                'email'          => $author['email'] ?? null,
                'email_source'   => ($author['email_source'] ?? null) ?: ($author['email'] ? 'scraped' : null),
                'media_type'     => $pub->media_type,
                'source_url'     => $author['source_url'] ?? $pub->authors_url ?? $pub->base_url,
                'profile_url'    => $author['profile_url'] ?? null,
                'country'        => $pub->country,
                'language'       => $pub->language,
                'topics'         => $pub->topics,
                'contact_status' => 'new',
                'scraped_from'   => $pub->slug ?? Str::slug($pub->name),
                'scraped_at'     => now(),
            ]);
            $saved++;

            if ($author['email'] ?? null) {
                $this->totalInferred++;
                usleep(250000); // 250ms for webhook rate limit
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

    // ═══════════════════════════════════════════════════════════════════
    // Helpers
    // ═══════════════════════════════════════════════════════════════════

    private function deduplicateAuthors(array $authors): array
    {
        $unique = [];
        foreach ($authors as $a) {
            $key = strtolower(Str::slug($a['full_name'] ?? ''));
            if (!$key || isset($unique[$key])) continue;
            // Prefer entries with email
            if (isset($unique[$key]) && empty($unique[$key]['email']) && !empty($a['email'])) {
                $unique[$key] = $a;
            } elseif (!isset($unique[$key])) {
                $unique[$key] = $a;
            }
        }
        return array_values($unique);
    }

    private function isValidName(string $name): bool
    {
        $name = trim($name);
        if (strlen($name) < 5 || strlen($name) > 65) return false;
        $words = array_filter(explode(' ', $name));
        if (count($words) < 2) return false;
        if (!preg_match('/^[\p{Lu}]/u', $name)) return false;
        if (preg_match('/[@#<>{}|\\\\\/\d{4}]/', $name)) return false;

        $blacklist = [
            'La Rédaction', 'La rédaction', 'Rédaction', 'Par AFP', 'AFP',
            'Reuters', 'Associated Press', 'Le service', 'Nos équipes',
            'Notre équipe', 'Par notre', 'Par AP', 'Avec AFP',
            'REDACTION', 'Partners Voice', 'LA REDACTION', 'Rédaction Web',
        ];
        foreach ($blacklist as $b) {
            if (stripos($name, $b) !== false) return false;
        }

        // Reject initials-only like "F.B." or "L.G."
        if (preg_match('/^[A-Z]\.[A-Z]\./', $name)) return false;

        return true;
    }

    private function slugToName(string $slug): string
    {
        // jean-dupont → Jean Dupont, jean_dupont → Jean Dupont
        $name = str_replace(['_', '-'], ' ', $slug);
        $name = ucwords(trim($name));
        return $name;
    }

    private function extractDomain(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST) ?? '';
        return preg_replace('/^www\./', '', $host) ?? '';
    }

    private function fetch(string $url): ?string
    {
        try {
            $ua = self::USER_AGENTS[array_rand(self::USER_AGENTS)];
            $response = Http::timeout(self::TIMEOUT)
                ->withHeaders([
                    'User-Agent'      => $ua,
                    'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'fr-FR,fr;q=0.9,en;q=0.5',
                ])
                ->get($url);

            if ($response->successful()) return $response->body();
        } catch (\Throwable $e) {
            // Silently fail
        }
        return null;
    }
}
