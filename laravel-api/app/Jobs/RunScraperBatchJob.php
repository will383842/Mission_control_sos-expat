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
    private const MAX_AGE_DAYS = 30;

    public int $timeout = 120;
    public int $tries = 1;

    public function handle(): void
    {
        // Check global toggle
        if (!Setting::getBool('scraper_enabled')) {
            Log::debug('RunScraperBatchJob: global scraper disabled, skipping batch');
            return;
        }

        // Get contact types with scraper enabled
        $enabledTypes = ContactTypeModel::where('scraper_enabled', true)
            ->where('is_active', true)
            ->pluck('value')
            ->toArray();

        if (empty($enabledTypes)) {
            Log::debug('RunScraperBatchJob: no contact types have scraper enabled');
            return;
        }

        // Find contacts needing scraping
        $contacts = Influenceur::query()
            ->whereNull('email')                              // Missing email
            ->where(function ($q) {                           // Has a URL to scrape
                $q->whereNotNull('profile_url')
                    ->where('profile_url', '!=', '')
                    ->orWhere(function ($q2) {
                        $q2->whereNotNull('website_url')
                            ->where('website_url', '!=', '');
                    });
            })
            ->whereIn('contact_type', $enabledTypes)          // Type has scraper enabled
            ->whereNull('scraped_at')                         // Not already scraped
            ->where('created_at', '>=', now()->subDays(self::MAX_AGE_DAYS)) // Recent contacts only
            ->orderBy('created_at', 'desc')                   // Newest first
            ->limit(self::MAX_PER_BATCH)
            ->get();

        $count = $contacts->count();

        if ($count === 0) {
            Log::debug('RunScraperBatchJob: no contacts need scraping');
            return;
        }

        Log::info('RunScraperBatchJob: starting batch', [
            'contacts'      => $count,
            'enabled_types' => $enabledTypes,
        ]);

        // Mark them as pending first to avoid double-dispatching
        $ids = $contacts->pluck('id')->toArray();
        Influenceur::whereIn('id', $ids)->update(['scraper_status' => 'pending']);

        // Dispatch individual jobs
        foreach ($contacts as $contact) {
            ScrapeContactJob::dispatch($contact->id);
        }

        Log::info('RunScraperBatchJob: batch dispatched', [
            'dispatched' => $count,
        ]);
    }
}
