<?php

namespace App\Services;

use App\Http\Controllers\InfluenceurController;

/**
 * Parses AI-structured text into contact arrays.
 * Now supports reliability scoring from Claude's analysis.
 */
class ResultParserService
{
    /**
     * Parse raw AI text into array of contact data.
     */
    public function parse(string $text, string $contactType, string $country): array
    {
        $results = [];
        $blocks = preg_split('/\n{2,}/', $text);

        foreach ($blocks as $block) {
            $contact = $this->parseBlock($block, $contactType, $country);
            if ($contact) {
                $results[] = $contact;
            }
        }

        return $results;
    }

    /**
     * Parse multiple AI responses and merge + deduplicate results.
     */
    public function parseAndMerge(array $responses, string $contactType, string $country): array
    {
        $all = [];

        foreach ($responses as $text) {
            if (empty($text)) continue;
            $parsed = $this->parse($text, $contactType, $country);
            $all = array_merge($all, $parsed);
        }

        return $this->deduplicateResults($all);
    }

    /**
     * Parse a single text block into a contact array.
     */
    private function parseBlock(string $block, string $contactType, string $country): ?array
    {
        $name = $this->extractField($block, ['NOM', 'Nom', 'nom', 'Name', 'NAME']);
        if (!$name) return null;

        // Filter garbage names (Perplexity meta-text parsed as contact names)
        if ($this->isGarbageName($name)) return null;

        $email = $this->extractField($block, ['EMAIL', 'Email', 'email', 'E-mail', 'e-mail']);
        $phone = $this->extractField($block, ['TEL', 'Tél', 'Tel', 'Téléphone', 'tel', 'téléphone', 'TELEPHONE', 'Phone', 'PHONE']);
        $url = $this->extractUrl($block);
        $platform = $this->extractField($block, ['PLATEFORME', 'Plateforme', 'plateforme', 'Platform', 'PLATFORM']);
        $followers = $this->extractField($block, ['ABONNES', 'Abonnés', 'abonnés', 'MEMBRES', 'Membres', 'Followers', 'FOLLOWERS']);
        $source = $this->extractField($block, ['SOURCE', 'Source']);
        $reliability = $this->extractField($block, ['FIABILITE', 'Fiabilité', 'FIABILITÉ', 'Reliability']);
        $reliabilityReason = $this->extractField($block, ['RAISON_FIABILITE', 'Raison_fiabilité', 'RAISON_FIABILITÉ', 'Raison fiabilité']);

        // Clean up extracted values
        $email = $this->cleanEmail($email);
        $phone = $this->cleanPhone($phone);
        $reliabilityScore = $this->parseReliabilityScore($reliability);

        // Auto-calculate reliability if not provided by Claude
        if ($reliabilityScore === 0) {
            $reliabilityScore = $this->calculateReliability($email, $phone, $url, $source);
            $reliabilityReason = $this->generateReliabilityReason($email, $phone, $url, $source);
        }

        // Build note from remaining text
        $note = $this->extractNote($block);

        return [
            'name'               => trim($name),
            'email'              => $email,
            'phone'              => $phone,
            'profile_url'        => $url,
            'country'            => $country,
            'contact_type'       => $contactType,
            'platforms'          => $platform ? [$this->normalizePlatform($platform)] : [],
            'followers'          => $this->parseFollowerCount($followers),
            'notes'              => $note,
            'source'             => 'ai_research',
            'web_source'         => $this->cleanWebSource($source),
            'reliability_score'  => $reliabilityScore,
            'reliability_reason' => $reliabilityReason,
            'has_email'          => !empty($email),
            'has_phone'          => !empty($phone),
            'has_url'            => !empty($url),
        ];
    }

    /**
     * Calculate reliability score based on available data.
     * 1-5 scale where 5 = fully verified.
     */
    private function calculateReliability(?string $email, ?string $phone, ?string $url, ?string $source): int
    {
        $score = 1;

        if (!empty($url)) $score++;           // Has a URL
        if (!empty($email)) $score++;          // Has an email
        if (!empty($phone)) $score++;          // Has a phone
        if (!empty($source)) $score++;         // Has a web source

        return min($score, 5);
    }

    /**
     * Generate human-readable reliability explanation.
     */
    private function generateReliabilityReason(?string $email, ?string $phone, ?string $url, ?string $source): string
    {
        $parts = [];

        if (!empty($url)) $parts[] = 'URL trouvée';
        else $parts[] = 'Pas d\'URL';

        if (!empty($email)) $parts[] = 'email trouvé';
        else $parts[] = 'email manquant';

        if (!empty($phone)) $parts[] = 'téléphone trouvé';

        if (!empty($source)) $parts[] = 'source web citée';
        else $parts[] = 'pas de source vérifiable';

        return implode(', ', $parts);
    }

    private function parseReliabilityScore(?string $text): int
    {
        if (!$text) return 0;
        if (preg_match('/(\d)/', $text, $m)) {
            $score = (int) $m[1];
            return ($score >= 1 && $score <= 5) ? $score : 0;
        }
        return 0;
    }

    private function extractField(string $block, array $labels): ?string
    {
        foreach ($labels as $label) {
            // Support plain text AND Markdown bold: "NOM:", "**NOM**:", "**NOM:**"
            $escaped = preg_quote($label, '/');
            $pattern = '/(?:^|\n)\s*\*{0,2}' . $escaped . '\*{0,2}\s*:?\s*:?\s*(.+)/mi';
            if (preg_match($pattern, $block, $match)) {
                $value = trim($match[1]);
                // Remove trailing markdown bold markers
                $value = trim($value, '* ');
                // Filter out "not found" markers
                if ($value && !preg_match('/^(N\/A|non disponible|inconnu|n\.a\.|—|-|aucun|not available|unknown|non trouvé|not found|non\s*trouvé|pas trouvé|indisponible)$/i', $value)) {
                    return $value;
                }
            }
        }
        return null;
    }

    private function extractUrl(string $block): ?string
    {
        $url = null;
        // Support both plain and Markdown bold: "URL:", "**URL**:", "**URL:**"
        if (preg_match('/\*{0,2}(?:URL|url|Site|site|SITE|Site web|LIEN|Lien)\*{0,2}\s*:?\s*:?\s*(https?:\/\/[^\s*]+)/mi', $block, $match)) {
            $url = rtrim(trim($match[1]), '.,;)');
        } elseif (preg_match('/(https?:\/\/[^\s]+)/mi', $block, $match)) {
            $url = rtrim(trim($match[1]), '.,;)');
        }

        if (!$url) return null;

        // Remove Perplexity citation markers like [1], [5], [8] from URLs
        $url = preg_replace('/\[\d+\]$/', '', $url);

        return $url;
    }

    private function extractNote(string $block): ?string
    {
        $cleaned = preg_replace('/^.*?:\s*.+$/mi', '', $block);
        $cleaned = trim(preg_replace('/\n{2,}/', "\n", $cleaned));
        return $cleaned ? mb_substr($cleaned, 0, 500) : null;
    }

    private function cleanEmail(?string $email): ?string
    {
        if (!$email) return null;
        $email = trim($email);
        if (preg_match('/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/', $email, $match)) {
            $cleaned = strtolower($match[1]);

            // Reject technical/junk emails
            $blacklistedDomains = [
                'sentry.io', 'example.com', 'example.org', 'test.com',
                'domain.com', 'email.com', 'monsite.fr', 'yoursite.com',
                'wixpress.com', 'wix.com', 'squarespace.com',
                'flywheel.local', 'localhost', 'wordpress.com',
                'mailinator.com', 'guerrillamail.com', 'tempmail.com',
                'yopmail.com', 'sharklasers.com', 'guerrillamailblock.com',
            ];
            $blacklistedPrefixes = [
                'noreply', 'no-reply', 'donotreply', 'mailer-daemon',
                'postmaster', 'webmaster', 'admin@localhost',
                'signalement', 'dpo@', 'abuse@', 'spam@',
            ];

            foreach ($blacklistedDomains as $domain) {
                if (str_contains($cleaned, '@' . $domain) || str_ends_with($cleaned, '.' . $domain)) {
                    return null;
                }
            }
            foreach ($blacklistedPrefixes as $prefix) {
                if (str_starts_with($cleaned, $prefix)) {
                    return null;
                }
            }

            // Reject placeholder emails
            if ($cleaned === 'user@domain.com' || $cleaned === 'email@example.com') {
                return null;
            }

            return $cleaned;
        }
        return null;
    }

    private function cleanPhone(?string $phone): ?string
    {
        if (!$phone) return null;
        $phone = trim($phone);
        $cleaned = preg_replace('/[^0-9+\-\s().\/]/', '', $phone);
        return strlen($cleaned) >= 6 ? $cleaned : null;
    }

    private function cleanWebSource(?string $source): ?string
    {
        if (!$source) return null;
        $source = trim($source);
        if (preg_match('/(https?:\/\/[^\s]+)/', $source, $match)) {
            $url = rtrim($match[1], '.,;)');
            // Remove Perplexity citation markers [1], [5], etc.
            $url = preg_replace('/\[\d+\]$/', '', $url);
            return $url;
        }
        return $source;
    }

    private function normalizePlatform(?string $platform): string
    {
        if (!$platform) return 'website';
        $p = strtolower(trim($platform));
        return match (true) {
            str_contains($p, 'tiktok') => 'tiktok',
            str_contains($p, 'youtube') => 'youtube',
            str_contains($p, 'instagram') => 'instagram',
            str_contains($p, 'facebook') => 'facebook',
            str_contains($p, 'linkedin') => 'linkedin',
            str_contains($p, 'twitter') || str_contains($p, 'x.com') => 'x',
            str_contains($p, 'blog') => 'blog',
            str_contains($p, 'podcast') => 'podcast',
            default => 'website',
        };
    }

    /**
     * Detect garbage names from Perplexity meta-text parsed as contact names.
     * E.g. "bre de contacts extraits: 1", "Nombre de contacts identifiés : 0"
     */
    private function isGarbageName(string $name): bool
    {
        $lower = mb_strtolower($name);
        $garbagePatterns = [
            'bre de contacts',
            'nombre de contacts',
            'contacts extraits',
            'contacts exploitables',
            'contacts identifiés',
            'contacts trouvés',
            'aucun contact',
            'résumé',
            'note :',
            'remarque',
            'total :',
            'résultats',
        ];
        foreach ($garbagePatterns as $pattern) {
            if (str_contains($lower, $pattern)) return true;
        }
        // Reject names shorter than 3 chars or that are just numbers
        if (mb_strlen(trim($name)) < 3 || preg_match('/^\d+$/', trim($name))) return true;

        return false;
    }

    private function parseFollowerCount(?string $text): ?int
    {
        if (!$text) return null;
        $text = str_replace([' ', '.', ','], ['', '', ''], strtolower($text));
        if (preg_match('/([\d]+)\s*[kK]/', $text, $m)) return (int) $m[1] * 1000;
        if (preg_match('/([\d]+)\s*[mM]/', $text, $m)) return (int) $m[1] * 1000000;
        if (preg_match('/(\d+)/', $text, $m)) return (int) $m[1];
        return null;
    }

    /**
     * Deduplicate parsed results by profile URL domain and email.
     */
    private function deduplicateResults(array $results): array
    {
        $seen = [];
        $unique = [];

        foreach ($results as $r) {
            $domain = null;
            if (!empty($r['profile_url'])) {
                $domain = InfluenceurController::normalizeProfileUrl($r['profile_url']);
            }
            $email = $r['email'] ?? null;

            $domainKey = $domain ? 'url:' . $domain : null;
            $emailKey = $email ? 'email:' . strtolower($email) : null;

            if ($domainKey && isset($seen[$domainKey])) continue;
            if ($emailKey && isset($seen[$emailKey])) continue;

            if ($domainKey) $seen[$domainKey] = true;
            if ($emailKey) $seen[$emailKey] = true;

            $unique[] = $r;
        }

        return $unique;
    }

    /**
     * Check which parsed contacts already exist in the database.
     */
    public function checkDuplicates(array $parsedContacts): array
    {
        $new = [];
        $duplicates = [];

        foreach ($parsedContacts as $contact) {
            $isDuplicate = false;

            if (!empty($contact['profile_url'])) {
                $domain = InfluenceurController::normalizeProfileUrl($contact['profile_url']);
                if ($domain && \App\Models\Influenceur::where('profile_url_domain', $domain)->exists()) {
                    $isDuplicate = true;
                }
            }

            if (!$isDuplicate && !empty($contact['email'])) {
                if (\App\Models\Influenceur::where('email', strtolower($contact['email']))->exists()) {
                    $isDuplicate = true;
                }
            }

            $isDuplicate ? $duplicates[] = $contact : $new[] = $contact;
        }

        return ['new' => $new, 'duplicates' => $duplicates];
    }
}
