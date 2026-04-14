<?php

namespace App\Jobs;

use App\Models\LandingCampaign;
use App\Services\Content\LandingGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateLandingPageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;   // 10 min max
    public int $tries   = 3;
    public int $maxExceptions = 2;

    public function backoff(): array
    {
        return [60, 300]; // 1 min puis 5 min (identique à GenerateArticleJob)
    }

    /**
     * @param array{
     *   audience_type: string,
     *   template_id: string,
     *   country_code: string,
     *   language: string,
     *   problem_slug?: string|null,
     *   created_by?: int|null,
     * } $params
     */
    public function __construct(
        public readonly array $params,
    ) {
        $this->onQueue('landings');
    }

    public function handle(LandingGenerationService $service): void
    {
        Log::info('GenerateLandingPageJob started', [
            'audience_type' => $this->params['audience_type'],
            'template_id'   => $this->params['template_id'],
            'country_code'  => $this->params['country_code'],
            'problem_slug'  => $this->params['problem_slug'] ?? null,
        ]);

        $landing = $service->generate($this->params);

        // N'incrémenter que si la LP vient d'être créée (pas un hit de déduplication).
        // Avec 3 réplicas, deux workers peuvent recevoir le même job simultanément.
        if ($landing->wasRecentlyCreated) {
            LandingCampaign::where('audience_type', $this->params['audience_type'])
                ->increment('total_generated');

            if ($landing->generation_cost_cents > 0) {
                LandingCampaign::where('audience_type', $this->params['audience_type'])
                    ->increment('total_cost_cents', $landing->generation_cost_cents);
            }
        }

        Log::info('GenerateLandingPageJob completed', [
            'landing_id'    => $landing->id,
            'slug'          => $landing->slug,
            'audience_type' => $this->params['audience_type'],
            'country_code'  => $this->params['country_code'],
            'template_id'   => $this->params['template_id'],
            'seo_score'     => $landing->seo_score,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('GenerateLandingPageJob failed', [
            'params'    => $this->params,
            'error'     => $e->getMessage(),
            'trace'     => substr($e->getTraceAsString(), 0, 500),
        ]);
    }
}
