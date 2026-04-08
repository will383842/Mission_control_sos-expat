<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BacklinkEngineWebhookService
{
    /**
     * Contact types that should be synced to the backlink-engine.
     */
    public const SYNCABLE_TYPES = [
        'presse',
        'blog',
        'podcast_radio',
        'influenceur',
        'youtubeur',
        'instagrammeur',
        'backlink',
        'annuaire',
        'partenaire',
    ];

    /**
     * Send a new contact to the backlink-engine webhook.
     *
     * @param array{
     *   email: string,
     *   name?: string,
     *   firstName?: string,
     *   lastName?: string,
     *   type: string,
     *   publication?: string,
     *   country?: string,
     *   language?: string,
     *   source_url?: string,
     *   source_table: string,
     *   source_id: int,
     * } $payload
     */
    public static function sendContactCreated(array $payload): void
    {
        $url = config('services.backlink_engine.webhook_url');
        $secret = config('services.backlink_engine.webhook_secret');

        if (! $url || ! $secret) {
            Log::debug('BacklinkEngine webhook not configured, skipping sync', [
                'source_table' => $payload['source_table'] ?? null,
                'source_id' => $payload['source_id'] ?? null,
            ]);
            return;
        }

        try {
            $response = Http::withHeaders([
                'X-Webhook-Secret' => $secret,
                'Content-Type' => 'application/json',
            ])
                ->timeout(10)
                ->retry(2, 500)
                ->post($url, $payload);

            Log::info('BacklinkEngine webhook sent', [
                'status' => $response->status(),
                'body' => $response->json(),
                'email' => $payload['email'] ?? null,
                'source_table' => $payload['source_table'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('BacklinkEngine webhook failed', [
                'error' => $e->getMessage(),
                'email' => $payload['email'] ?? null,
                'source_table' => $payload['source_table'] ?? null,
            ]);
        }
    }

    /**
     * Check if a contact_type should be synced.
     */
    public static function isSyncable(string $type): bool
    {
        return in_array($type, self::SYNCABLE_TYPES, true);
    }
}
