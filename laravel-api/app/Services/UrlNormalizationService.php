<?php

namespace App\Services;

use App\Models\Influenceur;
use Illuminate\Support\Facades\Log;

class UrlNormalizationService
{
    /**
     * Normalize a URL to its root domain.
     * https://www.example.com/path?q=1 → example.com
     */
    public static function normalizeToRootDomain(?string $url): ?string
    {
        if (!$url) return null;

        $url = trim($url);
        if (!preg_match('#https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) return null;

        $host = strtolower($host);
        $host = preg_replace('/^www\./', '', $host);

        return $host;
    }

    /**
     * Batch normalize all influenceurs' profile_url_domain.
     * Only processes contacts where profile_url_domain is null or doesn't match.
     */
    public function runBatchNormalization(int $limit = 500): array
    {
        $stats = ['processed' => 0, 'updated' => 0, 'errors' => 0];

        Influenceur::whereNotNull('profile_url')
            ->where(function ($q) {
                $q->whereNull('profile_url_domain')
                  ->orWhereRaw("profile_url_domain LIKE '%[%'") // broken citations
                  ->orWhereRaw("profile_url_domain LIKE '%/%'"); // not normalized
            })
            ->limit($limit)
            ->each(function (Influenceur $inf) use (&$stats) {
                $stats['processed']++;
                $normalized = self::normalizeToRootDomain($inf->profile_url);
                if ($normalized && $normalized !== $inf->profile_url_domain) {
                    $inf->update(['profile_url_domain' => $normalized]);
                    $stats['updated']++;
                }
            });

        return $stats;
    }
}
