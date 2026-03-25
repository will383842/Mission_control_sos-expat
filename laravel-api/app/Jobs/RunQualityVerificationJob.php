<?php

namespace App\Jobs;

use App\Services\DeduplicationService;
use App\Services\EmailDomainMatchService;
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

    public int $timeout = 600; // 10 min (increased for larger batches)
    public int $tries = 1;

    public function handle(
        UrlNormalizationService $urlService,
        DeduplicationService $dedupService,
        TypeVerificationService $typeService,
        EmailVerificationService $emailService,
        EmailDomainMatchService $domainMatchService,
        QualityScoreService $scoreService,
    ): void {
        Log::info('Quality verification: starting pipeline');

        // Step 1: URL normalization
        $urlStats = $urlService->runBatchNormalization(300);
        Log::info('Quality: URL normalization', $urlStats);

        // Step 2: Clean junk emails + email/site domain matching
        $matchStats = $domainMatchService->runBatch(200);
        Log::info('Quality: email domain match', $matchStats);

        // Step 3: Deduplication detection
        $crossDupes = $dedupService->findCrossTypeDuplicates(200);
        $withinDupes = $dedupService->findWithinTypeDuplicates(200);
        Log::info('Quality: dedup', ['cross' => $crossDupes, 'within' => $withinDupes]);

        // Step 4: Type verification
        $typeStats = $typeService->detectMisclassified(300);
        Log::info('Quality: type verification', $typeStats);

        // Step 5: Email MX/SMTP verification (increased to 100 per run)
        $emailStats = $emailService->batchVerify(100);
        Log::info('Quality: email verification', $emailStats);

        // Step 6: Quality score recalculation
        $scoreStats = $scoreService->recalculateBatch(300);
        Log::info('Quality: score recalculation', $scoreStats);

        Log::info('Quality verification: pipeline complete');
    }
}
