<?php

namespace App\Services\News;

use App\Models\RssFeed;
use App\Models\RssFeedItem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RssFetcherService
{
    /**
     * Fetch a RSS feed and persist new items.
     * Returns the number of new items created.
     */
    public function fetchFeed(RssFeed $feed): int
    {
        $xml = $this->downloadFeed($feed->url);

        if ($xml === null) {
            Log::warning("RssFetcherService: impossible de télécharger le feed #{$feed->id} ({$feed->url})");
            return 0;
        }

        $newCount = 0;
        $cutoff   = now()->subDays(2); // Nouvelles fraiches uniquement (aujourd'hui + hier)

        try {
            $parsed = @simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        } catch (\Throwable $e) {
            Log::error("RssFetcherService: erreur parsing XML feed #{$feed->id}", ['error' => $e->getMessage()]);
            $feed->update(['last_fetched_at' => now()]);
            return 0;
        }

        if (! $parsed) {
            Log::warning("RssFetcherService: XML invalide pour feed #{$feed->id}");
            $feed->update(['last_fetched_at' => now()]);
            return 0;
        }

        // Supports RSS 2.0 channel/item and Atom feed/entry
        $items = $parsed->channel->item ?? $parsed->entry ?? [];

        foreach ($items as $item) {
            try {
                $guid    = $this->extractGuid($item);
                $title   = (string) ($item->title ?? '');
                $link    = (string) ($item->link ?? ($item->id ?? ''));
                $pubDate = $this->extractPubDate($item);

                // Ignorer les items trop anciens
                if ($pubDate && $pubDate->lt($cutoff)) {
                    continue;
                }

                // Extraire le contenu
                $namespaces = $item->getNamespaces(true);
                $content    = '';
                if (isset($namespaces['content'])) {
                    $contentNs = $item->children($namespaces['content']);
                    $content   = (string) ($contentNs->encoded ?? '');
                }
                $description = (string) ($item->description ?? '');

                // Eviter les doublons
                $exists = RssFeedItem::where('feed_id', $feed->id)
                    ->where('guid', $guid)
                    ->exists();

                if ($exists) {
                    continue;
                }

                RssFeedItem::create([
                    'feed_id'          => $feed->id,
                    'guid'             => $guid,
                    'title'            => mb_substr($title, 0, 500),
                    'url'              => mb_substr($link, 0, 500),
                    'source_name'      => mb_substr($feed->name, 0, 255),
                    'published_at'     => $pubDate,
                    'original_title'   => mb_substr($title, 0, 500),
                    'original_excerpt' => $description ?: null,
                    'original_content' => $content ?: null,
                    'language'         => $feed->language,
                    'country'          => $feed->country,
                    'status'           => 'pending',
                    'relevance_score'  => null,
                ]);

                $newCount++;

            } catch (\Throwable $e) {
                Log::warning("RssFetcherService: erreur sur un item du feed #{$feed->id}", ['error' => $e->getMessage()]);
            }
        }

        $feed->update([
            'last_fetched_at'   => now(),
            'items_fetched_count' => $feed->items_fetched_count + $newCount,
        ]);

        return $newCount;
    }

    // ─────────────────────────────────────────
    // DOWNLOAD
    // ─────────────────────────────────────────

    private function downloadFeed(string $url): ?string
    {
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $response = Http::timeout(15)
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; SOS-Expat-RSSBot/1.0)'])
                    ->get($url);

                if ($response->successful()) {
                    return $response->body();
                }

                Log::warning("RssFetcherService: HTTP {$response->status()} pour {$url} (tentative {$attempt})");

            } catch (\Throwable $e) {
                Log::warning("RssFetcherService: exception téléchargement {$url} (tentative {$attempt})", ['error' => $e->getMessage()]);
            }

            if ($attempt < 2) {
                sleep(2);
            }
        }

        return null;
    }

    // ─────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────

    private function extractGuid(\SimpleXMLElement $item): string
    {
        // RSS 2.0 guid
        if (isset($item->guid) && (string) $item->guid !== '') {
            return (string) $item->guid;
        }

        // Atom id
        if (isset($item->id) && (string) $item->id !== '') {
            return (string) $item->id;
        }

        // Fallback: link
        $link = (string) ($item->link ?? '');
        if ($link !== '') {
            return $link;
        }

        // Dernier recours: hash du titre
        return md5((string) ($item->title ?? '') . (string) ($item->pubDate ?? ''));
    }

    private function extractPubDate(\SimpleXMLElement $item): ?\Illuminate\Support\Carbon
    {
        $raw = (string) ($item->pubDate ?? ($item->updated ?? ($item->published ?? '')));

        if ($raw === '') {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }
}
