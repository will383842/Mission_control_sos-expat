<?php

namespace App\Jobs;

use App\Models\ContentBusiness;
use App\Models\ContentSource;
use App\Services\BusinessDirectoryScraperService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ScrapeBusinessDetailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 14400; // 4h
    public int $tries = 1;

    public function __construct(
        private int $sourceId,
    ) {
        $this->onQueue('content-scraper');
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('scrape-biz-details-' . $this->sourceId))
                ->releaseAfter(14400)
                ->expireAfter(14400),
        ];
    }

    public function handle(BusinessDirectoryScraperService $scraper): void
    {
        $source = ContentSource::find($this->sourceId);
        if (!$source) return;

        $remaining = ContentBusiness::where('source_id', $source->id)
            ->where('detail_scraped', false)
            ->count();

        Log::info('ScrapeBusinessDetailsJob: starting', [
            'source'    => $source->slug,
            'remaining' => $remaining,
        ]);

        $businesses = ContentBusiness::where('source_id', $source->id)
            ->where('detail_scraped', false)
            ->orderByDesc('is_premium')
            ->cursor();

        $detailCount = 0;
        $emailCount = 0;
        $consecutiveFailures = 0;

        foreach ($businesses as $biz) {
            $scraper->rateLimitSleep();

            try {
                $detail = $scraper->scrapeBusinessDetail($biz->url);
                if (!$detail) {
                    $consecutiveFailures++;
                    if ($consecutiveFailures >= 20) {
                        Log::warning('ScrapeBusinessDetailsJob: stopping after 20 failures');
                        break;
                    }
                    // Mark as scraped even if empty to avoid re-trying
                    $biz->update(['detail_scraped' => true]);
                    continue;
                }

                $updateData = ['detail_scraped' => true];
                if (!empty($detail['contact_email'])) { $updateData['contact_email'] = $detail['contact_email']; $emailCount++; }
                if (!empty($detail['contact_phone'])) $updateData['contact_phone'] = $detail['contact_phone'];
                if (!empty($detail['contact_name'])) $updateData['contact_name'] = $detail['contact_name'];
                if (!empty($detail['website'])) $updateData['website'] = $detail['website'];
                if (!empty($detail['description'])) $updateData['description'] = $detail['description'];
                if (isset($detail['is_premium'])) $updateData['is_premium'] = $detail['is_premium'];
                if (!empty($detail['views'])) $updateData['views'] = $detail['views'];
                if (!empty($detail['recommendations'])) $updateData['recommendations'] = $detail['recommendations'];
                if (!empty($detail['images'])) $updateData['images'] = $detail['images'];
                if (!empty($detail['opening_hours'])) $updateData['opening_hours'] = $detail['opening_hours'];
                if (!empty($detail['logo_url'])) $updateData['logo_url'] = $detail['logo_url'];
                if (!empty($detail['schema_type'])) $updateData['schema_type'] = $detail['schema_type'];
                if (!empty($detail['latitude'])) $updateData['latitude'] = $detail['latitude'];
                if (!empty($detail['longitude'])) $updateData['longitude'] = $detail['longitude'];
                if (!empty($detail['address'])) $updateData['address'] = $detail['address'];

                $biz->update($updateData);
                $detailCount++;
                $consecutiveFailures = 0;

                if ($detailCount % 50 === 0) {
                    gc_collect_cycles();
                    Log::info('ScrapeBusinessDetailsJob: progress', [
                        'scraped' => $detailCount,
                        'emails'  => $emailCount,
                    ]);
                }

            } catch (\Throwable $e) {
                $consecutiveFailures++;
                Log::warning('ScrapeBusinessDetailsJob: detail failed', [
                    'business' => $biz->name,
                    'error'    => $e->getMessage(),
                ]);
                if ($consecutiveFailures >= 20) break;
            }
        }

        Log::info('ScrapeBusinessDetailsJob: completed', [
            'detailed' => $detailCount,
            'emails'   => $emailCount,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ScrapeBusinessDetailsJob: failed permanently', [
            'sourceId' => $this->sourceId,
            'error'    => $e->getMessage(),
        ]);
    }
}
