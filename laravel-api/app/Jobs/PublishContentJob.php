<?php

namespace App\Jobs;

use App\Jobs\GenerateSitemapJob;
use App\Models\PublicationQueueItem;
use App\Models\PublicationSchedule;
use App\Services\Publishing\BlogPublisher;
use App\Services\Publishing\FirestorePublisher;
use App\Services\Publishing\WordPressPublisher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PublishContentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    /**
     * Use retryUntil instead of $tries to avoid release() counting as attempts.
     * This gives the job 24 hours to succeed (schedule/rate-limit delays won't exhaust retries).
     */
    public function retryUntil(): \DateTime
    {
        return now()->addHours(24);
    }

    /**
     * Exponential backoff in seconds.
     */
    public function backoff(): array
    {
        return [30, 60, 120, 300, 600];
    }

    public function __construct(
        public int $queueItemId,
    ) {
        $this->onQueue('publication');
    }

    public function handle(): void
    {
        $item = PublicationQueueItem::with(['endpoint', 'publishable'])->findOrFail($this->queueItemId);

        // Already processed
        if (in_array($item->status, ['published', 'cancelled'])) {
            return;
        }

        $endpoint = $item->endpoint;

        if (!$endpoint || !$endpoint->is_active) {
            $item->update([
                'status' => 'failed',
                'last_error' => 'Endpoint is inactive or deleted',
            ]);
            return;
        }

        // Check schedule constraints
        if (!$this->isWithinSchedule($endpoint->id)) {
            // Release back to queue with a delay — will be retried when schedule allows
            $this->release(300); // retry in 5 minutes
            return;
        }

        // Check rate limits
        if (!$this->isWithinRateLimit($endpoint->id)) {
            $this->release(600); // retry in 10 minutes
            return;
        }

        // Check scheduled_at (not ready yet)
        if ($item->scheduled_at && $item->scheduled_at->isFuture()) {
            $delay = now()->diffInSeconds($item->scheduled_at);
            $this->release(min($delay, 3600)); // max 1h delay
            return;
        }

        $publishable = $item->publishable;
        if (!$publishable) {
            $item->update([
                'status' => 'failed',
                'last_error' => 'Publishable content not found (deleted?)',
            ]);
            return;
        }

        try {
            $result = match ($endpoint->type) {
                'firestore' => app(FirestorePublisher::class)->publish($publishable, $endpoint),
                'wordpress' => app(WordPressPublisher::class)->publish($publishable, $endpoint),
                'blog'      => app(BlogPublisher::class)->publish($publishable, $endpoint),
                'webhook'   => $this->publishViaWebhook($publishable, $endpoint),
                default     => throw new \RuntimeException("Unknown endpoint type: {$endpoint->type}"),
            };

            $item->update([
                'status' => 'published',
                'published_at' => now(),
                'external_id' => $result['external_id'] ?? null,
                'external_url' => $result['external_url'] ?? null,
                'attempts' => $item->attempts + 1,
            ]);

            // Update the publishable model status
            $publishable->update([
                'status' => 'published',
                'published_at' => now(),
            ]);

            Log::info('Content published successfully', [
                'queue_item_id' => $item->id,
                'endpoint' => $endpoint->name,
                'type' => $endpoint->type,
                'external_url' => $result['external_url'] ?? null,
            ]);

            // Regenerate sitemap after successful publication
            GenerateSitemapJob::dispatch();

            // Submit to IndexNow for fast indexing (uses public URL from publisher)
            if (!empty($result['external_url'])) {
                SubmitIndexNowJob::dispatch($result['external_url']);
            }

        } catch (\Throwable $e) {
            $attempts = $item->attempts + 1;
            $maxAttempts = $item->max_attempts ?? 5;

            $item->update([
                'attempts' => $attempts,
                'last_error' => mb_substr($e->getMessage(), 0, 1000),
                'status' => $attempts >= $maxAttempts ? 'failed' : 'pending',
            ]);

            if ($attempts >= $maxAttempts) {
                Log::error('PublishContentJob permanently failed', [
                    'queue_item_id' => $item->id,
                    'endpoint' => $endpoint->name,
                    'attempts' => $attempts,
                    'error' => $e->getMessage(),
                ]);
            } else {
                // Let the queue handle retry with backoff
                throw $e;
            }
        }
    }

    /**
     * Check if current time is within the endpoint's publishing schedule.
     */
    private function isWithinSchedule(int $endpointId): bool
    {
        $schedule = PublicationSchedule::where('endpoint_id', $endpointId)->first();

        if (!$schedule || !$schedule->is_active) {
            return true; // No schedule = always OK
        }

        $now = now();

        // Check active days (0=Sunday, 6=Saturday)
        if (!empty($schedule->active_days) && !in_array($now->dayOfWeek, $schedule->active_days)) {
            return false;
        }

        // Check active hours
        if ($schedule->active_hours_start !== null && $schedule->active_hours_end !== null) {
            $currentHour = (int) $now->format('H');
            if ($currentHour < $schedule->active_hours_start || $currentHour >= $schedule->active_hours_end) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if publishing rate limits are respected.
     * Uses lockForUpdate on the schedule row to serialise concurrent checks.
     */
    private function isWithinRateLimit(int $endpointId): bool
    {
        return DB::transaction(function () use ($endpointId) {
            // Lock the schedule row to serialise concurrent rate-limit checks
            $schedule = PublicationSchedule::where('endpoint_id', $endpointId)
                ->lockForUpdate()
                ->first();

            if (!$schedule || !$schedule->is_active) {
                return true;
            }

            // Check max per hour
            if ($schedule->max_per_hour > 0) {
                $publishedLastHour = PublicationQueueItem::where('endpoint_id', $endpointId)
                    ->where('status', 'published')
                    ->where('published_at', '>=', now()->subHour())
                    ->count();

                if ($publishedLastHour >= $schedule->max_per_hour) {
                    return false;
                }
            }

            // Check max per day
            if ($schedule->max_per_day > 0) {
                $publishedToday = PublicationQueueItem::where('endpoint_id', $endpointId)
                    ->where('status', 'published')
                    ->where('published_at', '>=', now()->startOfDay())
                    ->count();

                if ($publishedToday >= $schedule->max_per_day) {
                    return false;
                }
            }

            // Check minimum interval
            if ($schedule->min_interval_minutes > 0) {
                $lastPublished = PublicationQueueItem::where('endpoint_id', $endpointId)
                    ->where('status', 'published')
                    ->orderByDesc('published_at')
                    ->value('published_at');

                if ($lastPublished && now()->diffInMinutes($lastPublished) < $schedule->min_interval_minutes) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * Publish content via webhook (POST JSON payload).
     */
    private function publishViaWebhook(mixed $publishable, mixed $endpoint): array
    {
        $config = $endpoint->config ?? [];
        $url = $config['webhook_url'] ?? null;

        if (!$url) {
            throw new \RuntimeException('Webhook URL not configured on endpoint');
        }

        $payload = [
            'event' => 'content.published',
            'content_type' => class_basename($publishable),
            'id' => $publishable->id,
            'uuid' => $publishable->uuid ?? null,
            'title' => $publishable->title,
            'slug' => $publishable->slug,
            'language' => $publishable->language,
            'country' => $publishable->country ?? null,
            'content_html' => $publishable->content_html,
            'excerpt' => $publishable->excerpt ?? null,
            'meta_title' => $publishable->meta_title ?? null,
            'meta_description' => $publishable->meta_description ?? null,
            'keywords_primary' => $publishable->keywords_primary ?? null,
            'json_ld' => $publishable->json_ld ?? null,
            'published_at' => now()->toIso8601String(),
        ];

        $headers = [];
        if (!empty($config['webhook_secret'])) {
            $headers['X-Webhook-Secret'] = $config['webhook_secret'];
        }
        if (!empty($config['webhook_headers']) && is_array($config['webhook_headers'])) {
            $headers = array_merge($headers, $config['webhook_headers']);
        }

        $response = Http::timeout(30)
            ->withHeaders($headers)
            ->post($url, $payload);

        if (!$response->successful()) {
            throw new \RuntimeException("Webhook returned HTTP {$response->status()}: " . mb_substr($response->body(), 0, 500));
        }

        $body = $response->json();

        return [
            'external_id' => $body['id'] ?? $body['external_id'] ?? null,
            'external_url' => $body['url'] ?? $body['external_url'] ?? null,
        ];
    }

    public function failed(\Throwable $e): void
    {
        $item = PublicationQueueItem::find($this->queueItemId);

        if ($item) {
            $item->update([
                'status' => 'failed',
                'last_error' => mb_substr($e->getMessage(), 0, 1000),
            ]);
        }

        Log::error('PublishContentJob failed permanently', [
            'queue_item_id' => $this->queueItemId,
            'error' => $e->getMessage(),
        ]);

        // Send Telegram alert if configured
        $botToken = config('services.telegram_alerts.bot_token');
        $chatId = config('services.telegram_alerts.chat_id');
        if ($botToken && $chatId) {
            try {
                Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                    'chat_id' => $chatId,
                    'parse_mode' => 'Markdown',
                    'text' => "🚨 *Job Failed*: `" . class_basename(static::class) . "`\n" .
                              "Error: " . mb_substr($e->getMessage(), 0, 500) . "\n" .
                              "Time: " . now()->toDateTimeString(),
                ]);
            } catch (\Throwable $tgError) {
                Log::warning('Failed to send Telegram alert', [
                    'error' => $tgError->getMessage(),
                ]);
            }
        }
    }
}
