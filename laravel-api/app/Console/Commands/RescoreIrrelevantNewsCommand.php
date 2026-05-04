<?php

namespace App\Console\Commands;

use App\Models\RssFeedItem;
use App\Services\News\RelevanceFilterService;
use Illuminate\Console\Command;

/**
 * Re-evaluate the relevance score of items previously marked "irrelevant".
 *
 * Use after tweaking the system prompt of RelevanceFilterService or after
 * lowering relevance_threshold on rss_feeds. The default mode is dry-run
 * (no writes); pass --apply to persist the new scores/statuses.
 *
 * Cost: GPT-4o-mini scores roughly 1.5¢ per 1k items. The --since and --limit
 * flags keep the batch bounded.
 *
 * Examples:
 *   php artisan news:rescore-irrelevant
 *   php artisan news:rescore-irrelevant --apply
 *   php artisan news:rescore-irrelevant --since=4 --limit=200 --apply
 */
class RescoreIrrelevantNewsCommand extends Command
{
    protected $signature = 'news:rescore-irrelevant
        {--apply : Persist new scores/statuses (default is dry-run)}
        {--since=4 : Only items created in the last N days}
        {--limit=500 : Max items to process}';

    protected $description = 'Re-score RSS feed items previously marked irrelevant — useful after a prompt or threshold tweak';

    public function handle(RelevanceFilterService $filter): int
    {
        $apply = (bool) $this->option('apply');
        $sinceDays = max(1, (int) $this->option('since'));
        $limit = max(1, (int) $this->option('limit'));

        $items = RssFeedItem::with('feed')
            ->where('status', 'irrelevant')
            ->where('created_at', '>=', now()->subDays($sinceDays))
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        $this->info('Mode: ' . ($apply ? 'APPLY (writes enabled)' : 'DRY-RUN (no writes)'));
        $this->info("Scope: {$items->count()} items (last {$sinceDays} days, limit {$limit})");

        if ($items->isEmpty()) {
            return self::SUCCESS;
        }

        $promoted = 0;
        $stillIrrelevant = 0;
        $failed = 0;
        $bar = $this->output->createProgressBar($items->count());
        $bar->start();

        foreach ($items as $item) {
            $oldScore = $item->relevance_score;
            $oldStatus = $item->status;

            try {
                if ($apply) {
                    // evaluate() persists immediately; in apply mode we let it write.
                    $filter->evaluate($item);
                    $item->refresh();
                } else {
                    // Dry-run: temporarily detach from DB by cloning so evaluate's
                    // ->update() call does not mutate the row. We instead inspect
                    // what the new score would be by intercepting via a fresh
                    // ad-hoc evaluation. Simpler approach: run normally on a
                    // *replicated* model so writes don't persist.
                    $clone = $item->replicate();
                    $clone->id = $item->id; // keep id for log readability
                    $clone->setRelation('feed', $item->feed);
                    // We can't avoid the write in evaluate() without code change,
                    // so dry-run skips the write by using a transaction rollback.
                    \DB::beginTransaction();
                    try {
                        $filter->evaluate($item);
                        $item->refresh();
                    } finally {
                        \DB::rollBack();
                    }
                }

                if ($item->status === 'pending') {
                    $promoted++;
                    $this->newLine();
                    $this->line(sprintf(
                        '  [PROMOTED] #%d %s → %d (was %s)',
                        $item->id,
                        mb_substr($item->title, 0, 60),
                        $item->relevance_score,
                        $oldScore ?? 'null'
                    ));
                } else {
                    $stillIrrelevant++;
                }
            } catch (\Throwable $e) {
                $failed++;
                $this->newLine();
                $this->warn("  [FAIL] #{$item->id}: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Result', 'Count'],
            [
                ['Promoted to pending', $promoted],
                ['Still irrelevant', $stillIrrelevant],
                ['Failed', $failed],
            ]
        );

        if (!$apply) {
            $this->warn('Dry-run only. Re-run with --apply to persist new scores.');
        }

        return self::SUCCESS;
    }
}
