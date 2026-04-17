<?php

namespace App\Console\Commands;

use App\Jobs\GenerateSocialPostJob;
use App\Models\SocialPost;
use App\Services\Social\SocialDriverManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Multi-platform editorial calendar filler — run daily at 06:00 UTC.
 *
 * For each enabled platform, scan the next 30 weekdays and create one post
 * per day that has no existing post. source_type rotation uses ISO week number
 * so the same week always gets the same content angle (deterministic).
 *
 * Usage:
 *   php artisan social:fill-calendar
 *   php artisan social:fill-calendar --platform=linkedin
 *   php artisan social:fill-calendar --dry-run --days=14
 */
class FillSocialCalendarCommand extends Command
{
    protected $signature   = 'social:fill-calendar
                               {--platform= : Only this platform (default: all enabled)}
                               {--dry-run   : Show gaps without generating}
                               {--days=30   : Number of calendar days to cover (default 30)}';
    protected $description = 'Ensure the next 30 days always have 1 post per enabled platform/weekday';

    /** dow → [day_type label, fallback source_type] */
    private const DAY_BASE = [
        1 => ['monday',    'article'],
        3 => ['wednesday', 'hot_take'],
        5 => ['friday',    'faq'],
        6 => ['saturday',  'tip'],
    ];

    /** Deterministic rotation keyed by ISO week number. */
    private const MON_ROTATION = ['article', 'article', 'hot_take', 'article', 'article', 'hot_take'];
    private const WED_ROTATION = ['hot_take', 'reactive', 'myth', 'counter_intuition', 'hot_take', 'news'];
    private const FRI_ROTATION = ['faq', 'sondage', 'faq', 'poll', 'faq', 'sondage'];
    private const SAT_ROTATION = ['tip', 'milestone', 'partner_story', 'case_study', 'tip', 'counter_intuition'];

    public function __construct(private SocialDriverManager $manager)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $days    = max(7, min(60, (int) $this->option('days')));
        $dryRun  = (bool) $this->option('dry-run');

        $platforms = $this->option('platform')
            ? [$this->option('platform')]
            : $this->manager->availablePlatforms();

        foreach ($platforms as $platform) {
            if (!$this->manager->isEnabled($platform)) {
                $this->warn("Skipping {$platform} (disabled)");
                continue;
            }

            $this->fillFor($platform, $days, $dryRun);
        }

        return self::SUCCESS;
    }

    private function fillFor(string $platform, int $horizon, bool $dryRun): void
    {
        $driver       = $this->manager->driver($platform);
        $dflAccount   = $driver->supportedAccountTypes()[0];
        $generated    = 0;
        $covered      = 0;
        $gaps         = [];

        $this->info("🔍 {$platform}: fill calendar (horizon {$horizon}d" . ($dryRun ? ', DRY RUN' : '') . ')');

        // ── 1. Existing covered dates ─────────────────────────────────
        $coveredDates = SocialPost::forPlatform($platform)
            ->whereIn('status', ['generating', 'scheduled', 'published', 'pending_confirm'])
            ->whereBetween('scheduled_at', [now()->startOfDay(), now()->addDays($horizon)->endOfDay()])
            ->pluck('scheduled_at')
            ->map(fn($d) => $d->toDateString())
            ->unique()
            ->flip()
            ->toArray();

        // ── 2. Walk horizon and collect gaps ──────────────────────────
        for ($offset = 1; $offset <= $horizon; $offset++) {
            $date = now()->addDays($offset)->startOfDay();
            $dow  = (int) $date->format('N'); // 1=Mon .. 7=Sun

            // Publishing days: Mon(1), Wed(3), Fri(5), Sat(6)
            if (!in_array($dow, [1, 3, 5, 6], true)) continue;

            $dateStr = $date->toDateString();

            if (isset($coveredDates[$dateStr])) { $covered++; continue; }
            $gaps[] = $date;
        }

        if (empty($gaps)) {
            $this->info("   ✅ no gaps");
            return;
        }

        $this->warn("   📅 " . count($gaps) . " gap(s) found");

        // ── 3. Generate one post per gap ──────────────────────────────
        foreach ($gaps as $date) {
            $dow      = (int) $date->format('N');
            $isoWeek  = (int) $date->format('W');
            [$dayType, $sourceType] = $this->resolveTypes($dow, $isoWeek);

            $label = $date->format('D d M Y') . " — {$dayType} / {$sourceType}";
            $this->line("   📌 {$label}");

            if ($dryRun) continue;

            // 07:30 UTC on Mon/Wed/Fri, 09:00 UTC on Sat
            $slot = $date->copy()->setHour($dow === 6 ? 9 : 7)->setMinute($dow === 6 ? 0 : 30)->setSecond(0);

            // Race condition guard: re-check the slot is free
            $conflict = SocialPost::forPlatform($platform)
                ->whereIn('status', ['generating', 'scheduled'])
                ->whereDate('scheduled_at', $date->toDateString())
                ->exists();

            if ($conflict) {
                $this->line('   ⚠️  Slot taken concurrently — skipping');
                $covered++;
                continue;
            }

            $post = SocialPost::create([
                'platform'       => $platform,
                'source_type'    => $sourceType,
                'source_id'      => null,
                'source_title'   => null,
                'day_type'       => $dayType,
                'lang'           => 'fr',
                'account_type'   => $dflAccount,
                'hook'           => '',
                'body'           => '',
                'hashtags'       => [],
                'status'         => 'generating',
                'phase'          => 1,
                'scheduled_at'   => $slot,
                'auto_scheduled' => true,
            ]);

            GenerateSocialPostJob::dispatch($post->id, $platform);
            $generated++;

            Log::info('social:fill-calendar: post created', [
                'platform'    => $platform,
                'post_id'     => $post->id,
                'date'        => $date->toDateString(),
                'day_type'    => $dayType,
                'source_type' => $sourceType,
            ]);

            usleep(80_000); // 80 ms — concurrent-run collision guard
        }

        if (!$dryRun) {
            $this->info("   ✅ {$generated} generated, {$covered} already covered");
        }
    }

    private function resolveTypes(int $dow, int $isoWeek): array
    {
        [$dayType, $base] = self::DAY_BASE[$dow];

        $sourceType = match ($dow) {
            1 => self::MON_ROTATION[$isoWeek % count(self::MON_ROTATION)],
            3 => self::WED_ROTATION[$isoWeek % count(self::WED_ROTATION)],
            5 => self::FRI_ROTATION[$isoWeek % count(self::FRI_ROTATION)],
            6 => self::SAT_ROTATION[$isoWeek % count(self::SAT_ROTATION)],
            default => $base,
        };

        return [$dayType, $sourceType];
    }
}
