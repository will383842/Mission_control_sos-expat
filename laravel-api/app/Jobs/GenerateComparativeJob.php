<?php

namespace App\Jobs;

use App\Jobs\PublishContentJob;
use App\Models\Comparative;
use App\Models\PublicationQueueItem;
use App\Models\PublishingEndpoint;
use App\Services\Content\ComparativeGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateComparativeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 2;

    public function __construct(
        public array $params,
    ) {
        $this->onQueue('content');
    }

    public function handle(ComparativeGenerationService $service): void
    {
        Log::info('GenerateComparativeJob started', [
            'title' => $this->params['title'] ?? null,
            'entities' => $this->params['entities'] ?? [],
            'language' => $this->params['language'] ?? null,
        ]);

        $comparative = $service->generate($this->params);

        // Calculate and persist total generation cost
        $totalCost = \App\Models\ApiCost::where('costable_type', \App\Models\Comparative::class)
            ->where('costable_id', $comparative->id)
            ->sum('cost_cents');
        if ($totalCost > 0) {
            $comparative->update(['generation_cost_cents' => $totalCost]);
        }

        // Auto-publish to default endpoint
        $this->autoPublish($comparative);

        Log::info('GenerateComparativeJob completed', [
            'id' => $comparative->id,
            'title' => $comparative->title,
            'seo_score' => $comparative->seo_score,
            'cost_cents' => $comparative->generation_cost_cents,
            'auto_published' => true,
        ]);
    }

    private function autoPublish(Comparative $comparative): void
    {
        try {
            $endpoint = PublishingEndpoint::where('is_default', true)->where('is_active', true)->first();
            if (!$endpoint) return;

            $alreadyQueued = PublicationQueueItem::where('publishable_type', Comparative::class)
                ->where('publishable_id', $comparative->id)
                ->whereIn('status', ['pending', 'published', 'scheduled'])
                ->exists();
            if ($alreadyQueued) return;

            $queueItem = PublicationQueueItem::create([
                'publishable_type' => Comparative::class,
                'publishable_id'   => $comparative->id,
                'endpoint_id'      => $endpoint->id,
                'status'           => 'pending',
                'priority'         => 'default',
                'max_attempts'     => 5,
            ]);

            PublishContentJob::dispatch($queueItem->id)->delay(now()->addSeconds(60));
        } catch (\Throwable $e) {
            Log::error('GenerateComparativeJob: auto-publish failed (non-blocking)', [
                'comparative_id' => $comparative->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('GenerateComparativeJob failed', [
            'params' => $this->params,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
}
