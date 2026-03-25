<?php

namespace App\Jobs;

use App\Models\OutreachConfig;
use App\Models\OutreachSequence;
use App\Services\AiEmailGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Scheduled every 15 minutes.
 * Advances sequences: generates next step emails when delay has passed.
 */
class ProcessSequencesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries = 1;

    public function handle(AiEmailGenerationService $emailService): void
    {
        $sequences = OutreachSequence::readyToAdvance()
            ->with('influenceur')
            ->limit(20)
            ->get();

        if ($sequences->isEmpty()) return;

        Log::info('ProcessSequences: advancing', ['count' => $sequences->count()]);

        foreach ($sequences as $seq) {
            $inf = $seq->influenceur;
            if (!$inf) continue;

            $config = OutreachConfig::getFor($inf->contact_type);
            $nextStep = $seq->current_step + 1;

            // Check if sequence is done
            if ($nextStep > $config->max_steps) {
                $seq->update(['status' => 'completed', 'completed_at' => now()]);
                Log::info('Sequence completed', ['inf_id' => $inf->id, 'steps' => $seq->current_step]);
                continue;
            }

            // Check if contact replied, bounced, or unsubscribed (stop sequence)
            $lastEmail = $inf->outreachEmails()->where('step', $seq->current_step)->latest()->first();
            if ($lastEmail && in_array($lastEmail->status, ['replied', 'bounced', 'unsubscribed'])) {
                $seq->update([
                    'status'      => 'stopped',
                    'stop_reason' => $lastEmail->status,
                ]);
                Log::info('Sequence stopped', ['inf_id' => $inf->id, 'reason' => $lastEmail->status]);
                continue;
            }

            // Generate next step email
            $email = $emailService->generate($inf, $nextStep);
            if ($email) {
                // Calculate next send time
                $nextDelay = $config->getStepDelay($nextStep + 1);
                $seq->update([
                    'current_step' => $nextStep,
                    'next_send_at' => $nextStep < $config->max_steps ? now()->addDays($nextDelay) : null,
                ]);

                Log::info('Sequence advanced', ['inf_id' => $inf->id, 'step' => $nextStep]);
            }
        }
    }
}
