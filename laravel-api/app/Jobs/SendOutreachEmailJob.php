<?php

namespace App\Jobs;

use App\Models\OutreachEmail;
use App\Services\EmailSendingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendOutreachEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 30;
    public int $tries = 1; // Don't retry — warmup limit or SMTP issues need manual review

    public function __construct(private int $outreachEmailId) {}

    public function handle(EmailSendingService $service): void
    {
        $email = OutreachEmail::with('influenceur')->find($this->outreachEmailId);

        if (!$email) {
            Log::warning('SendOutreachEmail: email not found', ['id' => $this->outreachEmailId]);
            return;
        }

        // Only process emails that were atomically transitioned to 'queued'
        if ($email->status !== 'queued') {
            Log::debug('SendOutreachEmail: skipped, status is not queued', [
                'id'     => $email->id,
                'status' => $email->status,
            ]);
            return;
        }

        $result = $service->send($email);

        Log::info('SendOutreachEmail: result', [
            'id'     => $email->id,
            'to'     => $email->influenceur?->email,
            'step'   => $email->step,
            'result' => $result,
        ]);
    }
}
