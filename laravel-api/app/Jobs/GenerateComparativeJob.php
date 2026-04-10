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

        // Basic quality gate before auto-publish (Comparative is not a GeneratedArticle,
        // so we can't use QualityGuardService directly — inline essential checks)
        $text = strip_tags($comparative->content_html ?? '');
        $wordCount = str_word_count($text);
        $h2Count = preg_match_all('/<h2[^>]*>/i', $comparative->content_html ?? '');
        $hasBrandIssue = preg_match('/\bMLM\b|recruter|salarié|salarie/iu', $text);

        $canPublish = !empty($comparative->content_html)
            && $wordCount >= 500
            && $h2Count >= 2
            && !$hasBrandIssue;

        $comparative->update([
            'quality_score' => $canPublish ? 80 : 40,
        ]);

        if ($canPublish) {
            $this->autoPublish($comparative);
        } else {
            $comparative->update(['status' => 'review']);
        }

        Log::info('GenerateComparativeJob completed', [
            'id' => $comparative->id,
            'title' => $comparative->title,
            'seo_score' => $comparative->seo_score,
            'cost_cents' => $comparative->generation_cost_cents,
            'quality_score' => $qualityScore,
            'auto_published' => $canPublish,
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

            PublishContentJob::dispatch($queueItem->id);
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
