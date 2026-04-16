<?php

namespace App\Console\Commands;

use App\Models\LandingPage;
use App\Services\Content\LandingGenerationService;
use Illuminate\Console\Command;

/**
 * Backfill hreflang_map for all published landing pages using actual canonical_urls.
 *
 * Fixes the bug where hreflang_map was built at generation time with the
 * French slug for all languages (causing 404s for EN/DE/ZH/etc. hreflang links).
 *
 * Usage: php artisan landings:sync-hreflang [--dry-run]
 */
class SyncLandingHreflangCommand extends Command
{
    protected $signature = 'landings:sync-hreflang
                            {--dry-run : Show what would be updated without writing}';

    protected $description = 'Rebuild hreflang_map for all published landing pages from actual canonical_urls';

    public function handle(LandingGenerationService $service): int
    {
        $dryRun = $this->option('dry-run');

        // Get all root landing pages (parent_id IS NULL = primary language)
        $roots = LandingPage::published()
            ->whereNull('parent_id')
            ->whereNotNull('canonical_url')
            ->get();

        $this->info("Found {$roots->count()} root landing pages to sync.");

        $fixed = 0;
        $skipped = 0;

        foreach ($roots as $root) {
            // Count siblings
            $siblingCount = LandingPage::where('parent_id', $root->id)
                ->whereNotNull('canonical_url')
                ->count();

            if ($siblingCount === 0) {
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $this->line("  [DRY] {$root->language} {$root->canonical_url} → {$siblingCount} siblings");
            } else {
                $map = $service->syncHreflangMap($root);
                $this->line("  [OK] {$root->language} → " . count($map) . " hreflang (" . implode(',', array_keys($map)) . ")");
            }

            $fixed++;
        }

        $this->info("Done. Synced: {$fixed}, Skipped (no siblings): {$skipped}");

        return self::SUCCESS;
    }
}
