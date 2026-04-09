<?php

namespace App\Console\Commands;

use App\Jobs\PublishContentJob;
use App\Models\PublicationQueueItem;
use Illuminate\Console\Command;

class RetryFailedPublicationsCommand extends Command
{
    protected $signature = 'publish:retry-failed
                            {--hours=48 : Only retry items that failed within the last N hours}
                            {--dry-run : Show what would be retried without dispatching}';

    protected $description = 'Retry all failed publication queue items by resetting their status and re-dispatching';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $dryRun = $this->option('dry-run');

        $failedItems = PublicationQueueItem::where('status', 'failed')
            ->where('updated_at', '>=', now()->subHours($hours))
            ->with(['publishable', 'endpoint'])
            ->get();

        if ($failedItems->isEmpty()) {
            $this->info('No failed publication items found in the last ' . $hours . ' hours.');
            return 0;
        }

        $this->info("Found {$failedItems->count()} failed item(s):");

        foreach ($failedItems as $item) {
            $title = $item->publishable?->title ?? 'N/A';
            $endpoint = $item->endpoint?->name ?? 'N/A';
            $error = mb_substr($item->last_error ?? '', 0, 80);

            $this->line("  [{$item->id}] {$title} → {$endpoint} (attempts: {$item->attempts}, error: {$error})");
        }

        if ($dryRun) {
            $this->warn('Dry run — no jobs dispatched.');
            return 0;
        }

        if (!$this->confirm("Retry all {$failedItems->count()} item(s)?")) {
            return 0;
        }

        $dispatched = 0;
        foreach ($failedItems as $item) {
            $item->update([
                'status' => 'pending',
                'attempts' => 0,
                'last_error' => null,
            ]);
            PublishContentJob::dispatch($item->id);
            $dispatched++;
        }

        $this->info("✓ {$dispatched} publication job(s) re-dispatched.");

        return 0;
    }
}
