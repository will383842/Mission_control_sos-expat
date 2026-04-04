<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Tente d'extraire l'email de contact d'un profil Instagram francophone.
 * Instagram bloque la plupart des scrapes directs → on scrape le lien bio (linktree, site perso, etc.)
 */
class InstagramProfileScraperService
{
    private const TIMEOUT    = 15;
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36';

    /**
     * @return array{email: string|null, bio_link: string|null, followers: string|null}
     */
    public function scrapeProfile(string $instagramUrl): array
    {
        $result = ['email' => null, 'bio_link' => null, 'followers' => null];

        // Normaliser l'URL
        $url = $this->normalizeUrl($instagramUrl);
        if (!$url) return $result;

        try {
            // 1. Tenter de lire la page Instagram (souvent bloquée)
            $response = Http::timeout(self::TIMEOUT)
                ->withHeaders([
                    'User-Agent' => self::USER_AGENT,
                    'Accept-Language' => 'fr-FR,fr;q=0.9',
                ])
                ->get($url);

            if ($response->successful()) {
                $html = $response->body();

                // Chercher email direct dans la page
                $email = $this->extractEmailFromText($html);
                if ($email) { $result['email'] = $email; return $result; }

                // Chercher le lien bio (linktree, beacons, carrd, site perso...)
                $bioLink = $this->extractBioLink($html);
                if ($bioLink) {
                    $result['bio_link'] = $bioLink;
                    $email = $this->scrapeWebsiteForEmail($bioLink);
                    if ($email) { $result['email'] = $email; return $result; }
                }

                // Chercher abonnés dans le JSON embarqué
                if (preg_match('/"edge_followed_by"\s*:\s*\{\s*"count"\s*:\s*(\d+)/', $html, $m)) {
                    $result['followers'] = $m[1];
                }
            }
        } catch (\Throwable $e) {
            Log::debug('InstagramScraper error', ['url' => $url, 'error' => $e->getMessage()]);
        }

        return $result;
    }

    // =========================================================================

    private function normalizeUrl(string $input): ?string
    {
        $input = trim($input);
        if (str_starts_with($input, 'http')) return rtrim($input, '/') . '/';
        // @handle ou handle nu
        $handle = ltrim($input, '@');
        if ($handle) return 'https://www.instagram.com/' . $handle . '/';
        return null;
    }

    private function extractBioLink(string $html): ?string
    {
        // Patterns courants pour le lien dans la bio
        $patterns = [
            '/"external_url"\s*:\s*"([^"]+)"/',
            '/"biography_with_entities".*?"url"\s*:\s*"([^"]+)"/',
            '/rel="(?:me|noopener)"[^>]*href="(https?:\/\/(?!(?:www\.)?instagram\.com)[^"]+)"/',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $m)) {
                $url = html_entity_decode($m[1]);
                if (filter_var($url, FILTER_VALIDATE_URL)) return $url;
            }
        }
        return null;
    }

    public function scrapeWebsiteForEmail(string $url): ?string
    {
        // Nettoyer Google redirect
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
        } catch (\Throwable) {}

        return null;
    }

    public function extractEmailFromText(string $text): ?string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);
        if (preg_match('/\b([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})\b/', $text, $m)) {
            $email = strtolower($m[1]);
            $excluded = ['noreply', 'no-reply', 'donotreply', 'example.com', 'domain.com', 'test.com', 'sentry.io', 'wixpress'];
            foreach ($excluded as $ex) {
                if (str_contains($email, $ex)) return null;
            }
            return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
        }
        return null;
    }
}
