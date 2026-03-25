<?php

namespace App\Jobs;

use App\Models\OutreachEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Scheduled every 5 minutes.
 * Picks approved emails ready to send and dispatches SendOutreachEmailJob.
 */
class ProcessEmailQueueJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;
    public int $tries = 1;

    public function handle(): void
    {
        $emails = OutreachEmail::readyToSend()
            ->limit(20)
            ->get();

        if ($emails->isEmpty()) return;

        Log::info('ProcessEmailQueue: dispatching', ['count' => $emails->count()]);

        foreach ($emails as $email) {
            SendOutreachEmailJob::dispatch($email->id)->onQueue('email');
        }
    }
}
