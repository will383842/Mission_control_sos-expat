<?php

namespace App\Jobs;

use App\Services\JournalistDirectoryScraperService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ScrapeJournalistDirectoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600; // 10 min max per directory
    public int $tries   = 2;

    public function __construct(private string $sourceSlug)
    {
        $this->onQueue('scraper');
    }

    public function middleware(): array
    {
        return [new WithoutOverlapping("journalist-dir:{$this->sourceSlug}")];
    }

    public function handle(JournalistDirectoryScraperService $service): void
    {
        Log::info("ScrapeJournalistDirectoryJob: starting [{$this->sourceSlug}]");

        $result = $service->scrapeSource($this->sourceSlug);

        Log::info("ScrapeJournalistDirectoryJob: [{$this->sourceSlug}] done", [
            'saved'   => $result['saved'],
            'skipped' => $result['skipped'],
            'pages'   => $result['pages'],
            'error'   => $result['error'],
        ]);
    }

    public function failed(\Throwable $e): void
    {
        DB::table('journalist_directory_sources')
            ->where('slug', $this->sourceSlug)
            ->update([
                'status'     => 'failed',
                'last_error' => $e->getMessage(),
                'updated_at' => now(),
            ]);
    }
}
