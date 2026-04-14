<?php

namespace App\Console\Commands;

use App\Http\Controllers\LinkedInController;
use App\Jobs\GenerateLinkedInPostJob;
use App\Models\LinkedInPost;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Maintains a rolling 30-day LinkedIn editorial calendar.
 *
 * Run daily at 06:00 UTC (before the 07:30 posting slot).
 *
 * Logic:
 *  1. Scan the next 30 weekdays.
 *  2. For each day with no post (any status), generate one.
 *  3. Source type follows the day-of-week editorial rhythm.
 *  4. Free-type rotation varies by week number to avoid redundancy.
 *  5. Anti-redundancy: source_id dedup is handled in GenerateLinkedInPostJob
 *     (usedSourceIds query). This command adds topic rotation at the macro level.
 *
 * Usage:
 *   php artisan linkedin:fill-calendar
 *   php artisan linkedin:fill-calendar --dry-run   (show gaps, no generation)
 *   php artisan linkedin:fill-calendar --days=14   (fill only 14 days ahead)
 */
class FillLinkedInCalendarCommand extends Command
{
    protected $signature   = 'linkedin:fill-calendar
                               {--dry-run : Show gaps without generating}
                               {--days=30 : Number of calendar days to cover (default 30)}';
    protected $description = 'Ensure the next 30 days always have 1 LinkedIn post per weekday';

    /** Day-of-week number (1=Mon…5=Fri) → base source type */
    private const DAY_BASE = [
        1 => ['monday',    'article'],
        2 => ['tuesday',   'faq'],
        3 => ['wednesday', 'hot_take'],   // overridden by rotation
        4 => ['thursday',  'faq'],        // overridden by rotation
        5 => ['friday',    'tip'],        // overridden by rotation
    ];

    /**
     * Rotation arrays per day.
     * Indexed by (ISO week number % count) for deterministic variation.
     * Repeating the most impactful types more often is intentional.
     */
    private const WED_ROTATION = ['hot_take', 'reactive', 'myth', 'counter_intuition', 'hot_take', 'news'];
    private const THU_ROTATION = ['faq', 'sondage', 'faq', 'poll', 'faq', 'sondage'];
    private const FRI_ROTATION = ['tip', 'milestone', 'partner_story', 'case_study', 'tip', 'counter_intuition'];

    public function handle(): int
    {
        $days    = (int) $this->option('days');
        $dryRun  = (bool) $this->option('dry-run');
        $horizon = max(7, min(60, $days));

        $controller = new LinkedInController();
        $generated  = 0;
        $covered    = 0;
        $gaps       = [];

        $this->info("🔍 LinkedIn calendar fill — horizon: {$horizon} days" . ($dryRun ? ' [DRY RUN]' : ''));

        // ── 1. Build set of already-covered weekdays ──────────────────────
        $coveredDates = LinkedInPost::whereIn('status', ['generating', 'scheduled', 'published', 'pending_confirm'])
            ->whereBetween('scheduled_at', [now()->startOfDay(), now()->addDays($horizon)->endOfDay()])
            ->pluck('scheduled_at')
            ->map(fn($d) => $d->toDateString())
            ->unique()
            ->flip()  // make it a hash for O(1) lookup
            ->toArray();

        // ── 2. Walk next $horizon calendar days ───────────────────────────
        for ($offset = 1; $offset <= $horizon + 7; $offset++) {
            if ($generated + $covered >= ($horizon * 5 / 7 + 2)) break; // enough days scanned

            $date = now()->addDays($offset)->startOfDay();
            $dow  = (int) $date->format('N'); // 1=Mon … 5=Fri
            if ($dow > 5) continue;           // skip weekends

            $dateStr = $date->toDateString();

            if (isset($coveredDates[$dateStr])) {
                $covered++;
                continue;
            }

            $gaps[] = $date;
        }

        if (empty($gaps)) {
            $this->info("✅ Calendar is full — no gaps in the next {$horizon} days.");
            return self::SUCCESS;
        }

        $this->line('');
        $this->warn("📅 {$this->count($gaps)} gap(s) found:");

        foreach ($gaps as $date) {
            $dow      = (int) $date->format('N');
            $isoWeek  = (int) $date->format('W');
            [$dayType, $sourceType] = $this->resolveTypes($dow, $isoWeek);

            $label = $date->format('D d M Y') . " — {$dayType} / {$sourceType}";
            $this->line("   📌 {$label}");

            if ($dryRun) continue;

            // ── 3. Assign slot at 07:30 UTC ───────────────────────────────
            $slot = $date->copy()->setHour(7)->setMinute(30)->setSecond(0);

            // Safety: skip if slot is already taken (race condition guard)
            $conflict = LinkedInPost::whereIn('status', ['generating', 'scheduled'])
                ->whereDate('scheduled_at', $date->toDateString())
                ->exists();

            if ($conflict) {
                $this->line("   ⚠️  Slot already taken (race condition) — skipping");
                $covered++;
                continue;
            }

            // ── 4. Create post record + dispatch job ──────────────────────
            $post = LinkedInPost::create([
                'source_type'    => $sourceType,
                'source_id'      => null,      // auto-selected in Job
                'source_title'   => null,
                'day_type'       => $dayType,
                'lang'           => 'fr',
                'account'        => 'personal',
                'hook'           => '',
                'body'           => '',
                'hashtags'       => [],
                'status'         => 'generating',
                'phase'          => 1,
                'scheduled_at'   => $slot,
                'auto_scheduled' => true,
            ]);

            GenerateLinkedInPostJob::dispatch($post->id);
            $generated++;

            Log::info('linkedin:fill-calendar: post created', [
                'post_id'     => $post->id,
                'date'        => $dateStr,
                'day_type'    => $dayType,
                'source_type' => $sourceType,
            ]);

            // Small delay to avoid slot collision with concurrent runs
            usleep(80_000); // 80 ms
        }

        $this->line('');
        if ($dryRun) {
            $this->warn("DRY RUN — no posts created. Run without --dry-run to generate.");
        } else {
            $this->info("✅ Done: {$generated} post(s) dispatched, {$covered} days already covered.");
        }

        return self::SUCCESS;
    }

    /**
     * Resolve the editorial day_type + source_type for a given DOW + ISO week.
     * Week number drives the rotation so the pattern stays consistent and
     * predictable (same week always gets the same content angle).
     */
    private function resolveTypes(int $dow, int $isoWeek): array
    {
        [$dayType, $base] = self::DAY_BASE[$dow];

        $sourceType = match ($dow) {
            3 => self::WED_ROTATION[$isoWeek % count(self::WED_ROTATION)],
            4 => self::THU_ROTATION[$isoWeek % count(self::THU_ROTATION)],
            5 => self::FRI_ROTATION[$isoWeek % count(self::FRI_ROTATION)],
            default => $base,
        };

        return [$dayType, $sourceType];
    }

    /** Count without Countable dependency */
    private function count(array $arr): int
    {
        return count($arr);
    }
}
