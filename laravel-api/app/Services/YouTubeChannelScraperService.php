<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Scrape les pages de chaînes YouTube pour en extraire l'email de contact,
 * le nom du créateur, le nombre d'abonnés et le site web lié.
 */
class YouTubeChannelScraperService
{
    private const TIMEOUT   = 15;
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36';

    /**
     * Tente de construire l'URL About à partir d'une URL YouTube quelconque.
     * Entrées acceptées : @handle, /channel/ID, /c/name, URL complète.
     */
    public function normalizeAboutUrl(string $input): ?string
    {
        $input = trim($input);

        // Déjà une URL complète
        if (str_starts_with($input, 'http')) {
            $parsed = parse_url($input);
            $path   = rtrim($parsed['path'] ?? '', '/');
            // Retirer /about si déjà là pour reconstruire proprement
            $path = preg_replace('#/about$#', '', $path);
            return 'https://www.youtube.com' . $path . '/about';
        }

        // @handle ou handle nu
        $handle = ltrim($input, '@');
        return 'https://www.youtube.com/@' . $handle . '/about';
    }

    /**
     * Scrape une page About YouTube et retourne les données extraites.
     *
     * @return array{email: string|null, name: string|null, subscribers: string|null, website: string|null, description: string|null}
     */
    public function scrapeChannel(string $youtubeUrl): array
    {
        $aboutUrl = $this->normalizeAboutUrl($youtubeUrl);

        $result = [
            'email'       => null,
            'name'        => null,
            'subscribers' => null,
            'website'     => null,
            'description' => null,
            'about_url'   => $aboutUrl,
        ];

        try {
            $response = Http::timeout(self::TIMEOUT)
                ->withHeaders(['User-Agent' => self::USER_AGENT])
                ->get($aboutUrl);

            if (!$response->successful()) {
                return $result;
            }

            $html = $response->body();

            // --- Extraire ytInitialData ---
            if (preg_match('/var ytInitialData\s*=\s*(\{.+?\});\s*(?:var |<\/script>)/s', $html, $m)) {
                $ytData = json_decode($m[1], true);
                if ($ytData) {
                    $this->parseYtInitialData($ytData, $result);
                }
            }

            // --- Fallback : email regex dans le HTML brut ---
            if (!$result['email']) {
                $result['email'] = $this->extractEmailFromText($html);
            }

            // --- Scraper le site web lié si encore pas d'email ---
            if (!$result['email'] && $result['website']) {
                $result['email'] = $this->scrapeWebsiteForEmail($result['website']);
            }

        } catch (\Throwable $e) {
            Log::debug('YouTubeScraper error', ['url' => $aboutUrl, 'error' => $e->getMessage()]);
        }

        return $result;
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    private function parseYtInitialData(array $data, array &$result): void
    {
        $json = json_encode($data);

        // Nom de la chaîne
        if (preg_match('/"channelName"\s*:\s*"([^"]+)"/', $json, $m)) {
            $result['name'] = $m[1];
        }
        if (!$result['name'] && preg_match('/"title"\s*:\s*\{\s*"simpleText"\s*:\s*"([^"]+)"/', $json, $m)) {
            $result['name'] = $m[1];
        }

        // Abonnés
        if (preg_match('/"subscriberCountText"\s*:\s*\{\s*"simpleText"\s*:\s*"([^"]+)"/', $json, $m)) {
            $result['subscribers'] = $m[1];
        }
        if (!$result['subscribers'] && preg_match('/"subscriberCount"\s*:\s*"([^"]+)"/', $json, $m)) {
            $result['subscribers'] = $m[1];
        }

        // Description
        if (preg_match('/"description"\s*:\s*\{\s*"simpleText"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/s', $json, $m)) {
            $desc = json_decode('"' . $m[1] . '"') ?? $m[1];
            $result['description'] = $desc;
            // Chercher email dans la description
            if (!$result['email']) {
                $result['email'] = $this->extractEmailFromText($desc);
            }
        }

        // Email dans primaryLinks / links (nouvelle UI)
        if (preg_match_all('/"navigationEndpoint".*?"commandMetadata".*?"url"\s*:\s*"(mailto:[^"]+)"/', $json, $ms)) {
            foreach ($ms[1] as $mailto) {
                $email = ltrim(urldecode($mailto), 'mailto:');
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $result['email'] = $email;
                    break;
                }
            }
        }

        // Site web dans les links
        if (!$result['website'] && preg_match('/"title"\s*:\s*\{\s*"simpleText"\s*:\s*"(?:Website|Site web|Site|Web)"\s*\}.*?"url"\s*:\s*"(https?:\/\/[^"]+)"/', $json, $m)) {
            $result['website'] = $m[1];
        }
        // Fallback : premier lien externe non-youtube/social
        if (!$result['website']) {
            preg_match_all('/"url"\s*:\s*"(https?:\/\/(?!(?:www\.)?(?:youtube|youtu\.be|instagram|tiktok|twitter|x\.com|facebook|linkedin))[^"]+)"/', $json, $ms);
            foreach ($ms[1] as $url) {
                if (!str_contains($url, 'google.com') && !str_contains($url, 'goo.gl')) {
                    $result['website'] = $url;
                    break;
                }
            }
        }
    }

    private function scrapeWebsiteForEmail(string $url): ?string
    {
        // Nettoyer l'URL (Google redirect tracking)
        if (str_contains($url, 'google.com/url')) {
            parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $qs);
            $url = $qs['q'] ?? $url;
        }

        try {
            $response = Http::timeout(8)
                ->withHeaders(['User-Agent' => self::USER_AGENT])
                ->get($url);

            if ($response->successful()) {
                return $this->extractEmailFromText($response->body());
            }
        } catch (\Throwable) {
            // Silently ignore
        }
        return null;
    }

    private function extractEmailFromText(string $text): ?string
    {
        // Décoder les entités HTML
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);

        // Regex email standard
        if (preg_match('/\b([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})\b/', $text, $m)) {
            $email = strtolower($m[1]);
            // Exclure les faux positifs courants
            $excluded = ['noreply', 'no-reply', 'donotreply', 'example.com', 'domain.com', 'email.com', 'test.com'];
            foreach ($excluded as $ex) {
                if (str_contains($email, $ex)) return null;
            }
            return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
        }

        return null;
    }
}
