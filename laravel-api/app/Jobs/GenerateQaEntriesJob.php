<?php

namespace App\Jobs;

use App\Models\GeneratedArticle;
use App\Models\PublicationQueueItem;
use App\Models\PublishingEndpoint;
use App\Models\QaEntry;
use App\Services\Content\QaGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateQaEntriesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 2;
    public int $maxExceptions = 1;

    public function __construct(
        public int $articleId,
        public array $faqIds = [],
    ) {
        $this->onQueue('content');
    }

    public function handle(QaGenerationService $service): void
    {
        $article = GeneratedArticle::findOrFail($this->articleId);

        Log::info('GenerateQaEntriesJob started', [
            'article_id' => $article->id,
            'faq_ids' => $this->faqIds,
        ]);

        $entries = $service->generateFromArticleFaqs($article, $this->faqIds);

        // Auto-publish each Q/A entry
        foreach ($entries as $entry) {
            $this->autoPublish($entry);
        }

        Log::info('GenerateQaEntriesJob completed', [
            'article_id' => $article->id,
            'entries_created' => $entries->count(),
            'auto_published' => $entries->count(),
        ]);
    }

    private function autoPublish(QaEntry $entry): void
    {
        try {
            $endpoint = PublishingEndpoint::where('is_default', true)->where('is_active', true)->first();
            if (!$endpoint) return;

            $alreadyQueued = PublicationQueueItem::where('publishable_type', QaEntry::class)
                ->where('publishable_id', $entry->id)
                ->whereIn('status', ['pending', 'published', 'scheduled'])
                ->exists();
            if ($alreadyQueued) return;

            $queueItem = PublicationQueueItem::create([
                'publishable_type' => QaEntry::class,
                'publishable_id'   => $entry->id,
                'endpoint_id'      => $endpoint->id,
                'status'           => 'pending',
                'priority'         => 'default',
                'max_attempts'     => 5,
            ]);

            PublishContentJob::dispatch($queueItem->id)->delay(now()->addSeconds(30));
        } catch (\Throwable $e) {
            Log::error('GenerateQaEntriesJob: auto-publish failed (non-blocking)', [
                'qa_entry_id' => $entry->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('GenerateQaEntriesJob failed', [
            'article_id' => $this->articleId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
}
