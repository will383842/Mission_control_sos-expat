<?php

namespace App\Jobs;

use App\Models\ContactTypeModel;
use App\Models\Influenceur;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunScraperBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const MAX_PER_BATCH = 50;
    private const MAX_AGE_DAYS = 60; // Increased from 30

    public int $timeout = 120;
    public int $tries = 1;

    public function handle(): void
    {
        if (!Setting::getBool('scraper_enabled')) {
            Log::debug('RunScraperBatchJob: global scraper disabled');
            return;
        }

        $enabledTypes = ContactTypeModel::where('scraper_enabled', true)
            ->where('is_active', true)
            ->pluck('value')
            ->toArray();

        if (empty($enabledTypes)) return;

        $dispatched = 0;

        // Priority 1: Never scraped contacts (with URL, missing email)
        $neverScraped = Influenceur::query()
            ->whereNull('email')
            ->where(fn($q) => $q->whereNotNull('profile_url')->orWhereNotNull('website_url'))
            ->whereIn('contact_type', $enabledTypes)
            ->whereNull('scraped_at')
            ->where('created_at', '>=', now()->subDays(self::MAX_AGE_DAYS))
            ->orderByDesc('created_at')
            ->limit(self::MAX_PER_BATCH)
            ->get();

        $dispatched += $this->dispatchBatch($neverScraped, 'never_scraped');

        // Priority 2: Previously failed — retry (only if we haven't filled the batch)
        if ($dispatched < self::MAX_PER_BATCH) {
            $remaining = self::MAX_PER_BATCH - $dispatched;
            $failed = Influenceur::query()
                ->where('scraper_status', 'failed')
                ->where(fn($q) => $q->whereNotNull('profile_url')->orWhereNotNull('website_url'))
                ->whereIn('contact_type', $enabledTypes)
                ->where('scraped_at', '<', now()->subDays(3)) // Retry after 3 days
                ->orderByDesc('created_at')
                ->limit($remaining)
                ->get();

            $dispatched += $this->dispatchBatch($failed, 'retry_failed');
        }

        // Priority 3: Scraped but no email found — re-scrape older ones
        if ($dispatched < self::MAX_PER_BATCH) {
            $remaining = self::MAX_PER_BATCH - $dispatched;
            $noEmail = Influenceur::query()
                ->whereNull('email')
                ->where('scraper_status', 'completed')
                ->where(fn($q) => $q->whereNotNull('profile_url')->orWhereNotNull('website_url'))
                ->whereIn('contact_type', $enabledTypes)
                ->where('scraped_at', '<', now()->subDays(7)) // Re-scrape after 7 days
                ->orderBy('scraped_at')
                ->limit($remaining)
                ->get();

            $dispatched += $this->dispatchBatch($noEmail, 'rescrape_no_email');
        }

        if ($dispatched > 0) {
            Log::info('RunScraperBatchJob: dispatched', ['total' => $dispatched]);
        }
    }

    private function dispatchBatch($contacts, string $reason): int
    {
        if ($contacts->isEmpty()) return 0;

        $ids = $contacts->pluck('id')->toArray();
        Influenceur::whereIn('id', $ids)->update(['scraper_status' => 'pending']);

        foreach ($contacts as $contact) {
            ScrapeContactJob::dispatch($contact->id);
        }

        Log::info("RunScraperBatchJob: {$reason}", ['count' => count($ids)]);
        return count($ids);
    }
}
