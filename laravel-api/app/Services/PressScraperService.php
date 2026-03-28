<?php

namespace App\Services;

use App\Models\PressContact;
use App\Models\PressPublication;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Scrapes journalist contacts from French press publication pages.
 *
 * Strategy per publication:
 *  1. Fetch the team/redaction page (if configured)
 *  2. Fallback to common paths: /equipe, /redaction, /qui-sommes-nous, /a-propos, /mentions-legales
 *  3. Extract emails via regex (including obfuscated like [at], (at), &#64;)
 *  4. Extract person cards / bylines (name + role + email)
 *  5. Infer email from domain pattern if name found but no email
 */
class PressScraperService
{
    private const TIMEOUT = 25;

    private const USER_AGENTS = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.2 Safari/605.1.15',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:133.0) Gecko/20100101 Firefox/133.0',
    ];

    /** Known editorial roles in French press */
    private const ROLES_FR = [
        'directeur de la publication', 'directrice de la publication',
        'rédacteur en chef', 'rédactrice en chef',
        'directeur de la rédaction', 'directrice de la rédaction',
        'rédacteur en chef adjoint', 'rédactrice en chef adjointe',
        'journaliste', 'correspondant', 'correspondante',
        'reporter', 'chroniqueur', 'chroniqueuse',
        'éditorialiste', 'grand reporter',
        'chef de rubrique', 'chargé de communication',
        'responsable éditorial', 'responsable éditoriale',
        'secrétaire de rédaction', 'secrétaire général de la rédaction',
        'producteur', 'productrice', 'présentateur', 'présentatrice',
        'animateur', 'animatrice',
    ];

    /** Fallback URL paths to try for team/contact pages */
    private const FALLBACK_PATHS = [
        '/equipe',
        '/equipe-redactionnelle',
        '/la-redaction',
        '/redaction',
        '/qui-sommes-nous',
        '/a-propos',
        '/nous-connaitre',
        '/nous-rejoindre',
        '/mentions-legales',
        '/contact',
    ];

    /**
     * Scrape a single publication for journalist contacts.
     *
     * @return array{found: int, contacts: array[], error: ?string}
     */
    public function scrapePublication(PressPublication $pub): array
    {
        $urlsToTry = array_filter(array_unique([
            $pub->team_url,
            $pub->contact_url,
        ]));

        // Add fallback paths if team_url not set
        if (!$pub->team_url) {
            foreach (self::FALLBACK_PATHS as $path) {
                $urlsToTry[] = rtrim($pub->base_url, '/') . $path;
            }
        }

        $allContacts = [];
        $tried       = 0;
        $lastError   = null;

        foreach ($urlsToTry as $url) {
            if ($tried >= 4) break; // Don't hammer a site
            $tried++;

            usleep(random_int(1500000, 3000000)); // 1.5–3s delay

            try {
                $html = $this->fetchPage($url);
                if (!$html) continue;

                $contacts = $this->extractContacts($html, $url, $pub);
                if (!empty($contacts)) {
                    $allContacts = array_merge($allContacts, $contacts);
                    // Got contacts from this page, try contact_url too if not done
                    if ($url === $pub->team_url && $pub->contact_url && $pub->contact_url !== $url) {
                        continue;
                    }
                    break;
                }
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                Log::warning("PressScraperService: error scraping {$url}", ['error' => $lastError]);
            }
        }

        // If nothing found, at minimum extract the directeur@ from mentions-legales
        if (empty($allContacts)) {
            try {
                $mlUrl = rtrim($pub->base_url, '/') . '/mentions-legales';
                $html  = $this->fetchPage($mlUrl);
                if ($html) {
                    $allContacts = $this->extractContacts($html, $mlUrl, $pub);
                }
            } catch (\Throwable $e) {
                // Silently ignore
            }
        }

        // Deduplicate by email
        $seen    = [];
        $unique  = [];
        foreach ($allContacts as $c) {
            $key = $c['email'] ?: $c['full_name'];
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[]   = $c;
            }
        }

        return [
            'found'    => count($unique),
            'contacts' => $unique,
            'error'    => $lastError,
        ];
    }

    /**
     * Persist scraped contacts into the press_contacts table.
     */
    public function saveContacts(PressPublication $pub, array $contacts): int
    {
        $saved = 0;
        foreach ($contacts as $data) {
            // Skip if email already exists for this publication
            $exists = PressContact::where('publication', $pub->name)
                ->where(function ($q) use ($data) {
                    if ($data['email']) {
                        $q->where('email', $data['email']);
                    } else {
                        $q->where('full_name', $data['full_name']);
                    }
                })->exists();

            if ($exists) continue;

            PressContact::create([
                'publication_id' => $pub->id,
                'publication'    => $pub->name,
                'first_name'     => $data['first_name'] ?? null,
                'last_name'      => $data['last_name'] ?? null,
                'full_name'      => $data['full_name'],
                'email'          => $data['email'] ?? null,
                'phone'          => $data['phone'] ?? null,
                'role'           => $data['role'] ?? null,
                'beat'           => $data['beat'] ?? null,
                'media_type'     => $pub->media_type,
                'source_url'     => $data['source_url'] ?? null,
                'profile_url'    => $data['profile_url'] ?? null,
                'twitter'        => $data['twitter'] ?? null,
                'linkedin'       => $data['linkedin'] ?? null,
                'country'        => $pub->country,
                'language'       => $pub->language,
                'topics'         => $pub->topics,
                'contact_status' => 'new',
                'scraped_from'   => $pub->slug,
                'scraped_at'     => now(),
            ]);
            $saved++;
        }

        $pub->update([
            'contacts_count'  => PressContact::where('publication_id', $pub->id)->count(),
            'last_scraped_at' => now(),
            'status'          => 'scraped',
            'last_error'      => null,
        ]);

        return $saved;
    }

    // ─── PRIVATE HELPERS ──────────────────────────────────────────────────

    private function fetchPage(string $url): ?string
    {
        try {
            $ua       = self::USER_AGENTS[array_rand(self::USER_AGENTS)];
            $response = Http::timeout(self::TIMEOUT)
                ->withHeaders([
                    'User-Agent'      => $ua,
                    'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'fr-FR,fr;q=0.9,en;q=0.5',
                    'Accept-Encoding' => 'gzip, deflate, br',
                    'Cache-Control'   => 'no-cache',
                ])
                ->get($url);

            if ($response->successful()) {
                return $response->body();
            }
        } catch (\Throwable $e) {
            // Silently ignore
        }
        return null;
    }

    /**
     * Extract journalist contacts from HTML.
     */
    private function extractContacts(string $html, string $sourceUrl, PressPublication $pub): array
    {
        $contacts = [];

        // 1. Extract all visible emails
        $emails = $this->extractEmails($html);

        // 2. Try structured DOM extraction (JSON-LD, person cards)
        $structured = $this->extractStructuredPersons($html, $sourceUrl, $pub);
        if (!empty($structured)) {
            // Match emails to persons if possible
            foreach ($structured as &$person) {
                if (!$person['email'] && !empty($emails)) {
                    $person['email'] = $this->matchEmailToName(
                        $person['full_name'],
                        $emails,
                        $pub->base_url
                    );
                }
            }
            $contacts = array_merge($contacts, $structured);
        }

        // 3. If few contacts found, try HTML pattern extraction
        if (count($contacts) < 2) {
            $htmlContacts = $this->extractFromHtmlPatterns($html, $sourceUrl, $pub);
            $contacts     = array_merge($contacts, $htmlContacts);
        }

        // 4. If still nothing but we found emails, create generic contacts
        if (empty($contacts) && !empty($emails)) {
            foreach ($emails as $email) {
                $domain = explode('@', $email)[1] ?? '';
                $pubDomain = $this->extractDomain($pub->base_url);
                if ($domain && str_contains($domain, $pubDomain)) {
                    $name = $this->guessNameFromEmail($email);
                    if ($name) {
                        $contacts[] = $this->buildContact($name, $email, null, $sourceUrl);
                    }
                }
            }
        }

        return $contacts;
    }

    /**
     * Extract emails from HTML, including obfuscated forms.
     *
     * @return string[]
     */
    private function extractEmails(string $html): array
    {
        // Remove scripts and styles to reduce noise
        $clean = preg_replace('/<script[^>]*>.*?<\/script>/si', '', $html);
        $clean = preg_replace('/<style[^>]*>.*?<\/style>/si', '', $clean ?? $html);

        $emails = [];

        // Standard email
        preg_match_all('/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/', $clean ?? $html, $m1);
        $emails = array_merge($emails, $m1[0] ?? []);

        // Obfuscated: [at], (at), _at_, (arobase), [arobase]
        preg_match_all(
            '/([a-zA-Z0-9._%+\-]+)\s*[\[\(]\s*(?:at|arobase|@)\s*[\]\)]\s*([a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})/',
            $clean ?? $html, $m2
        );
        foreach (($m2[0] ?? []) as $i => $match) {
            $emails[] = ($m2[1][$i] ?? '') . '@' . ($m2[2][$i] ?? '');
        }

        // HTML entities: &#64; = @
        $decoded = html_entity_decode($clean ?? $html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        preg_match_all('/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/', $decoded, $m3);
        $emails = array_merge($emails, $m3[0] ?? []);

        // Deduplicate and filter
        $emails = array_unique(array_filter(array_map('strtolower', $emails), function ($e) {
            // Exclude image/file extensions and common non-contact emails
            $blocked = ['noreply', 'no-reply', 'donotreply', 'wordpress', 'privacy'];
            foreach ($blocked as $b) {
                if (str_contains($e, $b)) return false;
            }
            // Exclude generic personal domains
            foreach (['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com'] as $d) {
                if (str_ends_with($e, $d)) return false;
            }
            return filter_var($e, FILTER_VALIDATE_EMAIL) !== false;
        }));

        return array_values($emails);
    }

    /**
     * Try JSON-LD Person schema or Open Graph structured data extraction.
     */
    private function extractStructuredPersons(string $html, string $sourceUrl, PressPublication $pub): array
    {
        $contacts = [];

        // JSON-LD @type:Person
        preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $html, $matches);
        foreach (($matches[1] ?? []) as $json) {
            try {
                $data = json_decode($json, true);
                if (!$data) continue;

                $items = isset($data['@graph']) ? $data['@graph'] : [$data];
                foreach ($items as $item) {
                    if (($item['@type'] ?? '') !== 'Person') continue;
                    $name = $item['name'] ?? '';
                    if (!$name) continue;
                    $contacts[] = $this->buildContact(
                        $name,
                        $item['email'] ?? null,
                        $item['jobTitle'] ?? null,
                        $sourceUrl,
                        $item['url'] ?? null,
                        $item['sameAs'] ?? null,
                    );
                }
            } catch (\Throwable $e) {
                // Ignore JSON parse errors
            }
        }

        return $contacts;
    }

    /**
     * Extract contacts from common HTML patterns:
     * - .team-member, .journalist, .author cards
     * - dl/dt patterns (mentions légales)
     * - Paragraphs with role keywords
     */
    private function extractFromHtmlPatterns(string $html, string $sourceUrl, PressPublication $pub): array
    {
        $contacts = [];
        $found    = [];

        // Strip tags for text analysis
        $text = strip_tags($html);

        // Pattern: "Firstname Lastname, Role" near emails
        // Extract lines with a role keyword
        $lines = preg_split('/[\r\n]+/', $text);
        $roleRegex = implode('|', array_map('preg_quote', self::ROLES_FR));
        $buffer = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (strlen($line) < 5 || strlen($line) > 200) continue;

            $lowerLine = mb_strtolower($line);

            // Detect role line
            if (preg_match('/(' . $roleRegex . ')/iu', $lowerLine)) {
                // Try to find name in previous non-empty line
                $name = end($buffer) ?: null;
                if ($name && $this->looksLikePersonName($name)) {
                    $contacts[] = $this->buildContact($name, null, $line, $sourceUrl);
                } elseif (preg_match('/^([A-ZÁÀÂÉÈÊËÏÎÔÙÛÜÇŒ][a-záàâéèêëïîôùûüçœ]+(?:\s+[A-ZÁÀÂÉÈÊËÏÎÔÙÛÜÇŒ][a-záàâéèêëïîôùûüçœ]+)+)\s*[,–-]\s*(.+)$/', $line, $m)) {
                    // "Jean Dupont, Rédacteur en chef"
                    $contacts[] = $this->buildContact(trim($m[1]), null, trim($m[2]), $sourceUrl);
                }
                $buffer = [];
            } else {
                $buffer[] = $line;
                if (count($buffer) > 5) array_shift($buffer);
            }
        }

        // Pattern for mentions légales: "Directeur de la publication : Jean Dupont"
        preg_match_all(
            '/(?:directeur|directrice|responsable|rédacteur|rédactrice)[^:]*:\s*([A-ZÁÀÂÉÈÊËÏÎÔÙÛÜÇŒ][a-záàâéèêëïîôùûüçœ\s\-]+)/iu',
            $text,
            $m
        );
        foreach (($m[1] ?? []) as $name) {
            $name = trim($name);
            if ($name && strlen($name) > 4 && strlen($name) < 60 && !isset($found[$name])) {
                $found[$name] = true;
                $contacts[]   = $this->buildContact($name, null, null, $sourceUrl);
            }
        }

        return $contacts;
    }

    /**
     * Build a normalized contact array.
     *
     * @param array|null $sameAs  JSON-LD sameAs (LinkedIn, Twitter URLs etc.)
     */
    private function buildContact(
        string  $fullName,
        ?string $email,
        ?string $role,
        string  $sourceUrl,
        ?string $profileUrl = null,
        mixed   $sameAs = null,
    ): array {
        $parts     = $this->splitName($fullName);
        $twitter   = null;
        $linkedin  = null;

        if ($sameAs) {
            $links = is_array($sameAs) ? $sameAs : [$sameAs];
            foreach ($links as $link) {
                if (str_contains((string)$link, 'twitter.com') || str_contains((string)$link, 'x.com')) {
                    $twitter = (string)$link;
                } elseif (str_contains((string)$link, 'linkedin.com')) {
                    $linkedin = (string)$link;
                }
            }
        }

        return [
            'full_name'   => $fullName,
            'first_name'  => $parts['first'] ?? null,
            'last_name'   => $parts['last'] ?? null,
            'email'       => $email ? strtolower(trim($email)) : null,
            'role'        => $role ? Str::limit(trim($role), 150) : null,
            'beat'        => null,
            'phone'       => null,
            'source_url'  => $sourceUrl,
            'profile_url' => $profileUrl,
            'twitter'     => $twitter,
            'linkedin'    => $linkedin,
        ];
    }

    /**
     * Split "Firstname Lastname" → ['first' => ..., 'last' => ...]
     */
    private function splitName(string $fullName): array
    {
        $parts = explode(' ', trim($fullName), 2);
        if (count($parts) === 2) {
            return ['first' => $parts[0], 'last' => $parts[1]];
        }
        return ['first' => null, 'last' => $fullName];
    }

    /**
     * Check whether a string looks like a person name (2–4 words, capitalized).
     */
    private function looksLikePersonName(string $s): bool
    {
        return (bool) preg_match('/^[A-ZÁÀÂÉÈÊËÏÎÔÙÛÜÇŒ][a-záàâéèêëïîôùûüçœ\-]+(\s+[A-ZÁÀÂÉÈÊËÏÎÔÙÛÜÇŒ][a-záàâéèêëïîôùûüçœ\-]+){1,3}$/', trim($s));
    }

    /**
     * Try to match one of the found emails to a name using domain/firstname heuristics.
     */
    private function matchEmailToName(string $name, array $emails, string $baseUrl): ?string
    {
        $domain   = $this->extractDomain($baseUrl);
        $parts    = $this->splitName($name);
        $first    = strtolower(Str::ascii($parts['first'] ?? ''));
        $last     = strtolower(Str::ascii($parts['last'] ?? ''));

        foreach ($emails as $email) {
            if (!str_contains($email, $domain)) continue;
            $local = explode('@', $email)[0];
            if ($first && str_contains($local, $first)) return $email;
            if ($last  && str_contains($local, $last))  return $email;
        }

        return null;
    }

    /**
     * Guess a display name from an email like jean.dupont@pub.fr → "Jean Dupont"
     */
    private function guessNameFromEmail(string $email): ?string
    {
        $local = explode('@', $email)[0] ?? '';
        // Skip generic roles
        if (preg_match('/^(contact|redaction|presse|info|admin|editorial|direction|secretariat|webmaster)/', $local)) {
            return null;
        }
        // jean.dupont → Jean Dupont
        $parts = preg_split('/[._\-]/', $local);
        if (count($parts) >= 2) {
            return implode(' ', array_map('ucfirst', $parts));
        }
        return null;
    }

    /**
     * Extract root domain from a URL: "https://www.capital.fr/..." → "capital.fr"
     */
    private function extractDomain(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST) ?? '';
        return preg_replace('/^www\./', '', $host) ?? '';
    }
}
