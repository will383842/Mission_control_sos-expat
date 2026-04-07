<?php

namespace App\Jobs;

use App\Models\StatisticsDataset;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GenerateStatisticsArticleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 300;

    public function __construct(
        private int $datasetId,
        private int $userId = 0,
    ) {}

    public function handle(): void
    {
        $dataset = StatisticsDataset::find($this->datasetId);
        if (!$dataset) {
            Log::warning('GenerateStatisticsArticleJob: dataset not found', ['id' => $this->datasetId]);
            return;
        }
        if ($dataset->status !== 'generating') {
            Log::info('GenerateStatisticsArticleJob: skipped, status is ' . $dataset->status, ['id' => $this->datasetId]);
            return;
        }

        $statsFormatted = collect($dataset->stats)->map(function ($stat) {
            return "- {$stat['label']}: {$stat['value']} ({$stat['year'] ?? 'N/A'}) — Source: {$stat['source_name'] ?? 'N/A'}";
        })->implode("\n");

        $sourcesFormatted = collect($dataset->sources)->map(function ($src) {
            return "- {$src['name']}: {$src['url'] ?? 'N/A'}";
        })->implode("\n");

        $topic = "Statistical analysis: {$dataset->title}\n\n"
            . "Key statistics:\n{$statsFormatted}\n\n"
            . "Sources:\n{$sourcesFormatted}\n\n"
            . ($dataset->summary ? "Analysis: {$dataset->summary}\n\n" : '')
            . "Write a comprehensive, data-driven article. Cite every statistic with its source.";

        try {
            $params = [
                'topic'                => $topic,
                'language'             => $dataset->language,
                'country'              => $dataset->country_name,
                'content_type'         => 'statistics',
                'tone'                 => 'professional',
                'length'               => 'long',
                'generate_faq'         => true,
                'faq_count'            => 6,
                'research_sources'     => false, // stats already researched via Perplexity
                'image_source'         => 'unsplash',
                'auto_internal_links'  => true,
                'auto_affiliate_links' => false,
                'keywords'             => [$dataset->theme, $dataset->country_name ?? 'global'],
            ];

            $controller = app(\App\Http\Controllers\GeneratedArticleController::class);
            $request = Request::create('/content-gen/articles', 'POST', $params);

            // Provide user context — GeneratedArticleController::store() calls $request->user()->id
            $user = $this->userId ? User::find($this->userId) : User::where('role', 'admin')->first();
            if (!$user) {
                Log::error('GenerateStatisticsArticleJob: no user available', ['dataset_id' => $this->datasetId]);
                $dataset->update(['status' => 'failed']);
                return;
            }
            $request->setUserResolver(fn () => $user);

            $response = $controller->store($request);

            if ($response->getStatusCode() >= 400) {
                Log::error('GenerateStatisticsArticleJob: article generation returned error', [
                    'dataset_id' => $this->datasetId,
                    'status'     => $response->getStatusCode(),
                    'body'       => substr($response->getContent(), 0, 500),
                ]);
                $dataset->update(['status' => 'failed']);
                return;
            }

            $articleData = json_decode($response->getContent(), true) ?? [];

            $dataset->update([
                'status'               => 'published',
                'generated_article_id' => $articleData['id'] ?? null,
            ]);

            Log::info('GenerateStatisticsArticleJob: OK', [
                'dataset_id' => $this->datasetId,
                'article_id' => $articleData['id'] ?? null,
            ]);

        } catch (\Throwable $e) {
            Log::error('GenerateStatisticsArticleJob failed', [
                'dataset_id' => $this->datasetId,
                'error'      => $e->getMessage(),
            ]);
            $dataset->update(['status' => 'failed']);
        }
    }
}
