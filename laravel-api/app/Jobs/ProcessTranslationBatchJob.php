<?php

namespace App\Jobs;

use App\Models\GeneratedArticle;
use App\Models\QaEntry;
use App\Models\TranslationBatch;
use App\Services\Content\TranslationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessTranslationBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 3;

    public function __construct(
        public int $batchId,
    ) {
        $this->onQueue('content');
    }

    public function handle(TranslationService $translationService): void
    {
        $batch = TranslationBatch::find($this->batchId);

        if (!$batch) {
            Log::warning('ProcessTranslationBatchJob: batch not found', ['batch_id' => $this->batchId]);
            return;
        }

        // Check if batch is paused or cancelled
        if (in_array($batch->status, ['paused', 'cancelled', 'completed'], true)) {
            Log::info('ProcessTranslationBatchJob: batch not running', [
                'batch_id' => $batch->id,
                'status' => $batch->status,
            ]);
            return;
        }

        $targetLanguage = $batch->target_language;
        $contentType = $batch->content_type;

        try {
            $item = null;
            $itemType = null;

            // Find next untranslated item
            if ($contentType === 'article' || $contentType === 'all') {
                $item = GeneratedArticle::where('language', 'fr')
                    ->whereNull('parent_article_id')
                    ->whereIn('status', ['review', 'published'])
                    ->whereDoesntHave('translations', function ($q) use ($targetLanguage) {
                        $q->where('language', $targetLanguage);
                    })
                    ->first();

                if ($item) {
                    $itemType = 'article';
                }
            }

            if (!$item && ($contentType === 'qa' || $contentType === 'all')) {
                $item = QaEntry::where('language', 'fr')
                    ->whereNull('parent_qa_id')
                    ->whereIn('status', ['draft', 'review', 'published'])
                    ->whereDoesntHave('translations', function ($q) use ($targetLanguage) {
                        $q->where('language', $targetLanguage);
                    })
                    ->first();

                if ($item) {
                    $itemType = 'qa';
                }
            }

            // No more items to translate
            if (!$item) {
                $batch->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);

                Log::info('ProcessTranslationBatchJob: batch completed', [
                    'batch_id' => $batch->id,
                    'completed_items' => $batch->completed_items,
                ]);
                return;
            }

            // Update current item
            $batch->update(['current_item_id' => $item->id]);

            // Process the item
            if ($itemType === 'article') {
                $translationService->translateArticle($item, $targetLanguage);
            } else {
                // For Q&A, translate via the translation service using the same pattern
                $this->translateQaEntry($item, $targetLanguage, $translationService);
            }

            // Update batch progress
            $batch->increment('completed_items');

            Log::info('ProcessTranslationBatchJob: item translated', [
                'batch_id' => $batch->id,
                'item_type' => $itemType,
                'item_id' => $item->id,
                'progress' => ($batch->completed_items + 1) . '/' . $batch->total_items,
            ]);
        } catch (\Throwable $e) {
            $batch->increment('failed_items');

            $errorLog = $batch->error_log ?? [];
            $errorLog[] = [
                'item_id' => $item->id ?? null,
                'error' => $e->getMessage(),
                'at' => now()->toIso8601String(),
            ];
            $batch->update(['error_log' => $errorLog]);

            Log::error('ProcessTranslationBatchJob: item failed', [
                'batch_id' => $batch->id,
                'item_id' => $item->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        // Re-dispatch for next item (reload batch to get fresh status)
        $batch->refresh();
        $processed = $batch->completed_items + $batch->failed_items + $batch->skipped_items;

        if ($batch->status === 'running' && $processed < $batch->total_items) {
            self::dispatch($this->batchId);
        } elseif ($processed >= $batch->total_items) {
            $batch->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        }
    }

    /**
     * Translate a Q&A entry using OpenAI via the translation service pattern.
     */
    private function translateQaEntry(QaEntry $original, string $targetLanguage, TranslationService $translationService): void
    {
        // Use the translation service's translateArticle-like pattern for Q&A
        // We create a translated QaEntry based on the original
        $openAi = app(\App\Services\AI\OpenAiService::class);
        $slugService = app(\App\Services\Seo\SlugService::class);

        $from = $original->language;

        $translatedQuestion = $this->translateText($openAi, $original->question, $from, $targetLanguage);
        $translatedAnswerShort = $this->translateText($openAi, $original->answer_short ?? '', $from, $targetLanguage);
        $translatedAnswerHtml = $this->translateText($openAi, $original->answer_detailed_html ?? '', $from, $targetLanguage);
        $translatedMetaTitle = $this->translateText($openAi, $original->meta_title ?? '', $from, $targetLanguage);
        $translatedMetaDesc = $this->translateText($openAi, $original->meta_description ?? '', $from, $targetLanguage);

        $slug = $slugService->generateSlug($translatedQuestion, $targetLanguage);
        $slug = $slugService->ensureUnique($slug, $targetLanguage, 'qa_entries');

        $parentId = $original->parent_qa_id ?? $original->id;

        // Adapt JSON-LD: replace language prefix in URLs
        $adaptedJsonLd = $original->json_ld;
        if ($adaptedJsonLd) {
            $jsonLdStr = json_encode($adaptedJsonLd);
            $jsonLdStr = str_replace("/{$original->language}/", "/{$targetLanguage}/", $jsonLdStr);
            $adaptedJsonLd = json_decode($jsonLdStr, true);
        }

        $translatedQa = QaEntry::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'parent_qa_id' => $parentId,
            'parent_article_id' => $original->parent_article_id,
            'cluster_id' => $original->cluster_id,
            'question' => $translatedQuestion,
            'answer_short' => mb_substr($translatedAnswerShort, 0, 500),
            'answer_detailed_html' => $translatedAnswerHtml,
            'language' => $targetLanguage,
            'country' => $original->country,
            'category' => $original->category,
            'slug' => $slug,
            'meta_title' => mb_substr($translatedMetaTitle, 0, 60),
            'meta_description' => mb_substr($translatedMetaDesc, 0, 160),
            'json_ld' => $adaptedJsonLd,
            'keywords_primary' => $original->keywords_primary,
            'source_type' => $original->source_type,
            'status' => 'review',
            'word_count' => str_word_count(strip_tags($translatedAnswerHtml)),
        ]);
    }

    /**
     * Translate text using OpenAI.
     */
    private function translateText(\App\Services\AI\OpenAiService $openAi, string $text, string $from, string $to): string
    {
        if (empty(trim($text))) {
            return '';
        }

        $result = $openAi->translate($text, $from, $to);

        return $result['success'] ? trim($result['content']) : $text;
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ProcessTranslationBatchJob failed', [
            'batch_id' => $this->batchId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
}
