<?php

namespace App\Jobs;

use App\Models\PressPublication;
use App\Services\PressScraperService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

class ScrapePressPublicationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries   = 2;

    public function __construct(private int $publicationId)
    {
        $this->onQueue('scraper');
    }

    public function middleware(): array
    {
        return [new WithoutOverlapping("scrape-press-pub-{$this->publicationId}")];
    }

    public function handle(PressScraperService $service): void
    {
        $pub = PressPublication::find($this->publicationId);
        if (!$pub) {
            Log::warning("ScrapePressPublicationJob: publication #{$this->publicationId} not found");
            return;
        }

        Log::info("ScrapePressPublicationJob: scraping {$pub->name}");

        $result = $service->scrapePublication($pub);

        if (!empty($result['contacts'])) {
            $saved = $service->saveContacts($pub, $result['contacts']);
            Log::info("ScrapePressPublicationJob: saved {$saved}/{$result['found']} contacts for {$pub->name}");
        } else {
            $pub->update([
                'status'          => 'failed',
                'last_scraped_at' => now(),
                'last_error'      => $result['error'] ?? 'No contacts found',
            ]);
            Log::warning("ScrapePressPublicationJob: no contacts found for {$pub->name}", ['error' => $result['error']]);
        }
    }
}
