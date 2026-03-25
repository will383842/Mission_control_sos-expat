<?php

namespace App\Jobs;

use App\Models\OutreachEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Scheduled every 5 minutes.
 * Picks approved emails ready to send, atomically transitions to 'queued',
 * then dispatches SendOutreachEmailJob for each.
 *
 * Race condition prevention: uses lockForUpdate + status transition.
 */
class ProcessEmailQueueJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;
    public int $tries = 1;

    public function handle(): void
    {
        // Atomic pick: lock rows, transition status, then dispatch
        $emailIds = DB::transaction(function () {
            $ids = OutreachEmail::where('status', 'approved')
                ->where(fn($q) => $q->whereNull('send_after')->orWhere('send_after', '<=', now()))
                ->orderBy('created_at')
                ->limit(20)
                ->lockForUpdate()
                ->pluck('id')
                ->toArray();

            if (empty($ids)) return [];

            // Atomically mark as queued — no other worker can pick these
            OutreachEmail::whereIn('id', $ids)->update(['status' => 'queued']);

            return $ids;
        });

        if (empty($emailIds)) return;

        Log::info('ProcessEmailQueue: dispatching', ['count' => count($emailIds)]);

        foreach ($emailIds as $id) {
            SendOutreachEmailJob::dispatch($id)->onQueue('email');
        }
    }
}
