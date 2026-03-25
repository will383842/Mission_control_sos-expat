<?php

namespace App\Jobs;

use App\Services\DeduplicationService;
use App\Services\EmailVerificationService;
use App\Services\QualityScoreService;
use App\Services\TypeVerificationService;
use App\Services\UrlNormalizationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunQualityVerificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 1;

    public function handle(
        UrlNormalizationService $urlService,
        DeduplicationService $dedupService,
        TypeVerificationService $typeService,
        EmailVerificationService $emailService,
        QualityScoreService $scoreService,
    ): void {
        Log::info('Quality verification: starting pipeline');

        // Step 1: URL normalization
        $urlStats = $urlService->runBatchNormalization(200);
        Log::info('Quality: URL normalization', $urlStats);

        // Step 2: Deduplication detection
        $crossDupes = $dedupService->findCrossTypeDuplicates(100);
        $withinDupes = $dedupService->findWithinTypeDuplicates(100);
        Log::info('Quality: dedup', ['cross' => $crossDupes, 'within' => $withinDupes]);

        // Step 3: Type verification
        $typeStats = $typeService->detectMisclassified(200);
        Log::info('Quality: type verification', $typeStats);

        // Step 4: Email verification (rate limited, max 30 per run)
        $emailStats = $emailService->batchVerify(30);
        Log::info('Quality: email verification', $emailStats);

        // Step 5: Quality score recalculation
        $scoreStats = $scoreService->recalculateBatch(200);
        Log::info('Quality: score recalculation', $scoreStats);

        Log::info('Quality verification: pipeline complete');
    }
}
