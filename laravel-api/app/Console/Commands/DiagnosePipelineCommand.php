<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * Full diagnostic of the content generation → publication pipeline.
 * Identifies WHY articles are stuck in draft/review and not reaching the blog.
 */
class DiagnosePipelineCommand extends Command
{
    protected $signature = 'pipeline:diagnose {--fix : Auto-fix recoverable issues (re-queue stuck articles)}';
    protected $description = 'Diagnose the content generation → publication pipeline and identify blockers';

    public function handle(): int
    {
        $this->newLine();
        $this->components->info('Pipeline Diagnostic — ' . now()->toDateTimeString());
        $this->newLine();

        $exitCode = self::SUCCESS;

        // ─── 1. Article status distribution ───
        $this->components->twoColumnDetail('<fg=cyan>1. Article Status Distribution</>');
        $statuses = DB::table('generated_articles')
            ->select(DB::raw('status, count(*) as cnt'))
            ->groupBy('status')
            ->orderByDesc('cnt')
            ->get();

        foreach ($statuses as $s) {
            $icon = match ($s->status) {
                'published' => '<fg=green>✓</>',
                'review' => '<fg=yellow>⚠</>',
                'draft' => '<fg=red>✗</>',
                'processing' => '<fg=blue>⟳</>',
                default => ' ',
            };
            $this->components->twoColumnDetail("  {$icon} {$s->status}", (string) $s->cnt);
        }

        // ─── 2. Review articles — WHY blocked? ───
        $reviewCount = DB::table('generated_articles')->where('status', 'review')->count();
        $this->newLine();
        $this->components->twoColumnDetail('<fg=cyan>2. Articles in Review</>', (string) $reviewCount);

        if ($reviewCount > 0) {
            $reviews = DB::table('generated_articles')
                ->where('status', 'review')
                ->orderByDesc('created_at')
                ->limit(20)
                ->get(['id', 'title', 'quality_score', 'generation_notes', 'word_count', 'content_type', 'language', 'created_at']);

            $reasons = [
                'low_score' => 0,
                'has_issues' => 0,
                'low_words' => 0,
                'no_content' => 0,
                'brand_compliance' => 0,
                'cannibalization' => 0,
                'h2_structure' => 0,
            ];

            foreach ($reviews as $r) {
                $issues = $r->generation_notes;
                if (is_string($issues)) {
                    $issues = json_decode($issues, true) ?? [];
                }
                $issues = $issues ?? [];

                if ($r->quality_score < 60) $reasons['low_score']++;
                if (!empty($issues)) $reasons['has_issues']++;
                if ($r->word_count < 300) $reasons['low_words']++;
                if (empty($r->word_count)) $reasons['no_content']++;

                foreach ($issues as $issue) {
                    if (str_contains($issue, 'Brand')) $reasons['brand_compliance']++;
                    if (str_contains($issue, 'annibal')) $reasons['cannibalization']++;
                    if (str_contains($issue, 'H2')) $reasons['h2_structure']++;
                }
            }

            $this->components->twoColumnDetail('  Score < 60', (string) $reasons['low_score']);
            $this->components->twoColumnDetail('  Has critical issues', (string) $reasons['has_issues']);
            $this->components->twoColumnDetail('  Word count too low', (string) $reasons['low_words']);
            $this->components->twoColumnDetail('  Brand compliance fail', (string) $reasons['brand_compliance']);
            $this->components->twoColumnDetail('  Cannibalization detected', (string) $reasons['cannibalization']);
            $this->components->twoColumnDetail('  H2 structure insufficient', (string) $reasons['h2_structure']);

            // Show last 5 review articles with details
            $this->newLine();
            $this->line('  <fg=yellow>Last 5 review articles:</>');
            foreach ($reviews->take(5) as $r) {
                $issues = is_string($r->generation_notes) ? json_decode($r->generation_notes, true) : ($r->generation_notes ?? []);
                $issueStr = !empty($issues) ? implode(' | ', array_slice($issues, 0, 2)) : 'no issues logged';
                $this->line(sprintf(
                    '    [%s] score:%d words:%d type:%s lang:%s — %s',
                    substr($r->id, 0, 8),
                    $r->quality_score ?? 0,
                    $r->word_count ?? 0,
                    $r->content_type ?? '?',
                    $r->language ?? '?',
                    $issueStr
                ));
                $this->line("      Title: " . mb_substr($r->title ?? '?', 0, 80));
            }
        }

        // ─── 3. Publishing Endpoint ───
        $this->newLine();
        $this->components->twoColumnDetail('<fg=cyan>3. Publishing Endpoint</>');

        $endpoint = DB::table('publishing_endpoints')
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();

        if ($endpoint) {
            $this->components->twoColumnDetail('  Default endpoint', "<fg=green>{$endpoint->name}</> (type: {$endpoint->type})");
        } else {
            $this->components->twoColumnDetail('  Default endpoint', '<fg=red>NONE — articles cannot be published!</>');
            $exitCode = self::FAILURE;

            $allEndpoints = DB::table('publishing_endpoints')->get(['name', 'type', 'is_active', 'is_default']);
            if ($allEndpoints->isEmpty()) {
                $this->error('  No publishing endpoints exist at all! Run: php artisan db:seed --class=PublishingEndpointSeeder');
            } else {
                $this->line('  Existing endpoints:');
                foreach ($allEndpoints as $ep) {
                    $this->line(sprintf('    %s (type: %s, active: %s, default: %s)',
                        $ep->name, $ep->type,
                        $ep->is_active ? 'yes' : 'NO',
                        $ep->is_default ? 'yes' : 'no'
                    ));
                }
            }
        }

        // ─── 4. Publication Queue ───
        $this->newLine();
        $this->components->twoColumnDetail('<fg=cyan>4. Publication Queue</>');

        $queueStatuses = DB::table('publication_queue_items')
            ->select(DB::raw('status, count(*) as cnt'))
            ->groupBy('status')
            ->get();

        if ($queueStatuses->isEmpty()) {
            $this->components->twoColumnDetail('  Queue', '<fg=yellow>Empty — no articles have been queued for publication</>');
        } else {
            foreach ($queueStatuses as $qs) {
                $icon = match ($qs->status) {
                    'published' => '<fg=green>✓</>',
                    'pending', 'scheduled' => '<fg=yellow>⟳</>',
                    'failed' => '<fg=red>✗</>',
                    default => ' ',
                };
                $this->components->twoColumnDetail("  {$icon} {$qs->status}", (string) $qs->cnt);
            }
        }

        // Show failed publication details
        $failedPubs = DB::table('publication_queue_items')
            ->where('status', 'failed')
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get(['id', 'publishable_id', 'last_error', 'attempts', 'updated_at']);

        if ($failedPubs->isNotEmpty()) {
            $this->newLine();
            $this->line('  <fg=red>Last 5 failed publications:</>');
            foreach ($failedPubs as $fp) {
                $this->line(sprintf(
                    '    [%d] attempts:%d — %s',
                    $fp->id,
                    $fp->attempts,
                    mb_substr($fp->last_error ?? '?', 0, 120)
                ));
            }
        }

        // ─── 5. Redis Queue Depth ───
        $this->newLine();
        $this->components->twoColumnDetail('<fg=cyan>5. Redis Queue Depth</>');
        try {
            $queues = ['default', 'publication', 'content', 'email', 'scraper'];
            foreach ($queues as $q) {
                $len = Redis::llen("queues:{$q}");
                $color = $len > 100 ? 'red' : ($len > 10 ? 'yellow' : 'green');
                $this->components->twoColumnDetail("  {$q}", "<fg={$color}>{$len} jobs</>");
            }
        } catch (\Throwable $e) {
            $this->components->twoColumnDetail('  Redis', '<fg=red>Connection failed: ' . $e->getMessage() . '</>');
        }

        // ─── 6. Failed Jobs ───
        $this->newLine();
        $this->components->twoColumnDetail('<fg=cyan>6. Failed Jobs (last 24h)</>');
        $failedJobs = DB::table('failed_jobs')
            ->where('failed_at', '>=', now()->subDay())
            ->select(DB::raw("queue, count(*) as cnt"))
            ->groupBy('queue')
            ->get();

        if ($failedJobs->isEmpty()) {
            $this->components->twoColumnDetail('  Failed jobs', '<fg=green>None in last 24h</>');
        } else {
            foreach ($failedJobs as $fj) {
                $this->components->twoColumnDetail("  {$fj->queue}", "<fg=red>{$fj->cnt} failed</>");
            }
        }

        // ─── 7. Orchestrator Status ───
        $this->newLine();
        $this->components->twoColumnDetail('<fg=cyan>7. Orchestrator Status</>');
        $config = DB::table('content_orchestrator_config')->first();
        if ($config) {
            $this->components->twoColumnDetail('  Status', $config->status === 'running' ? '<fg=green>running</>' : "<fg=red>{$config->status}</>");
            $this->components->twoColumnDetail('  Auto-pilot', $config->auto_pilot ? '<fg=green>ON</>' : '<fg=red>OFF</>');
            $this->components->twoColumnDetail('  Daily target', (string) $config->daily_target);
            $this->components->twoColumnDetail('  Today generated', (string) $config->today_generated);
            $this->components->twoColumnDetail('  Today cost', '$' . number_format($config->today_cost_cents / 100, 2));
            $this->components->twoColumnDetail('  Last run', $config->last_run_at ?? 'never');
        } else {
            $this->components->twoColumnDetail('  Config', '<fg=red>No orchestrator config found!</>');
        }

        // ─── 8. Cost Budget ───
        $this->newLine();
        $this->components->twoColumnDetail('<fg=cyan>8. API Cost Budget</>');
        $todayCost = DB::table('api_costs')
            ->where('created_at', '>=', now()->startOfDay())
            ->sum('cost_cents');
        $dailyBudget = config('services.ai.daily_budget', 5000);
        $pct = $dailyBudget > 0 ? round($todayCost / $dailyBudget * 100) : 0;
        $color = $pct >= 90 ? 'red' : ($pct >= 70 ? 'yellow' : 'green');
        $this->components->twoColumnDetail('  Today spent', "<fg={$color}>\$" . number_format($todayCost / 100, 2) . " / \$" . number_format($dailyBudget / 100, 2) . " ({$pct}%)</>");

        // ─── 9. Source Stock ───
        $this->newLine();
        $this->components->twoColumnDetail('<fg=cyan>9. Source Items Stock</>');
        $stockByStatus = DB::table('generation_source_items')
            ->select(DB::raw('processing_status, count(*) as cnt'))
            ->groupBy('processing_status')
            ->get();

        foreach ($stockByStatus as $s) {
            $icon = $s->processing_status === 'ready' ? '<fg=green>✓</>' : ' ';
            $this->components->twoColumnDetail("  {$icon} {$s->processing_status}", (string) $s->cnt);
        }

        // ─── 10. Auto-fix (if --fix flag) ───
        if ($this->option('fix')) {
            $this->newLine();
            $this->components->info('Auto-fix mode enabled');

            // Re-queue articles that passed quality but were never queued
            $publishable = DB::table('generated_articles')
                ->where('status', 'review')
                ->where('quality_score', '>=', 60)
                ->whereRaw("(generation_notes IS NULL OR generation_notes = '[]' OR generation_notes = 'null')")
                ->where('word_count', '>', 0)
                ->whereNotNull('content_html')
                ->whereNull('parent_article_id')
                ->get(['id', 'title', 'quality_score']);

            if ($publishable->isEmpty()) {
                $this->line('  No recoverable articles found (all review articles have genuine quality issues).');
            } else {
                $this->line("  Found {$publishable->count()} articles that passed quality but were never published:");

                $endpointId = $endpoint->id ?? null;
                if (!$endpointId) {
                    $this->error('  Cannot fix: no default publishing endpoint exists!');
                } else {
                    $queued = 0;
                    foreach ($publishable as $article) {
                        // Check not already in queue
                        $alreadyQueued = DB::table('publication_queue_items')
                            ->where('publishable_id', $article->id)
                            ->where('publishable_type', 'App\\Models\\GeneratedArticle')
                            ->whereIn('status', ['pending', 'published', 'scheduled'])
                            ->exists();

                        if (!$alreadyQueued) {
                            $queueItemId = DB::table('publication_queue_items')->insertGetId([
                                'publishable_type' => 'App\\Models\\GeneratedArticle',
                                'publishable_id'   => $article->id,
                                'endpoint_id'      => $endpointId,
                                'status'            => 'pending',
                                'priority'          => 'default',
                                'max_attempts'      => 5,
                                'attempts'          => 0,
                                'created_at'        => now(),
                                'updated_at'        => now(),
                            ]);

                            \App\Jobs\PublishContentJob::dispatch($queueItemId)->delay(now()->addSeconds(30));
                            $queued++;
                            $this->line("    ✓ Queued: [{$article->id}] score:{$article->quality_score} — " . mb_substr($article->title, 0, 60));
                        }
                    }
                    $this->components->info("Re-queued {$queued} articles for publication.");
                }
            }

            // Retry failed publication queue items
            $failedItems = DB::table('publication_queue_items')
                ->where('status', 'failed')
                ->count();

            if ($failedItems > 0) {
                $this->newLine();
                $this->line("  Resetting {$failedItems} failed publication items to pending...");
                DB::table('publication_queue_items')
                    ->where('status', 'failed')
                    ->update(['status' => 'pending', 'attempts' => 0, 'updated_at' => now()]);

                // Re-dispatch them
                $pending = DB::table('publication_queue_items')
                    ->where('status', 'pending')
                    ->pluck('id');
                foreach ($pending as $itemId) {
                    \App\Jobs\PublishContentJob::dispatch($itemId)->delay(now()->addSeconds(rand(10, 60)));
                }
                $this->components->info("Reset and re-dispatched {$failedItems} failed publications.");
            }
        }

        // ─── Summary ───
        $this->newLine();
        $this->line('─────────────────────────────────────────');
        if ($exitCode === self::SUCCESS) {
            $this->components->info('Pipeline looks healthy. If articles are stuck in review, check their generation_notes above.');
        } else {
            $this->components->error('Pipeline has critical issues — see details above.');
        }
        $this->line('Tip: Run with --fix to auto-recover stuck articles: php artisan pipeline:diagnose --fix');
        $this->newLine();

        return $exitCode;
    }
}
