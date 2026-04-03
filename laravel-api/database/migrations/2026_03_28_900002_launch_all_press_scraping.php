<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Jobs\ScrapePressPublicationJob;
use App\Jobs\ScrapePublicationAuthorsJob;
use App\Jobs\ScrapeJournalistDirectoryJob;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Fix: ensure all publications have language = 'fr' ──────────────
        DB::table('press_publications')
            ->whereNull('language')
            ->update(['language' => 'fr']);

        DB::table('press_publications')
            ->where('language', '')
            ->update(['language' => 'fr']);

        // Correct Swiss/Belgian/Canadian pubs to their country
        $swissSlugs = ['le-temps-suisse', 'lhebdo-heidinews-suisse', 'rts-radio-television-suisse'];
        DB::table('press_publications')->whereIn('slug', $swissSlugs)->update(['country' => 'CH', 'language' => 'fr']);

        $belgianSlugs = ['le-soir-belgique', 'la-libre-belgique', 'rtbf', 'rtl-belgique-info', 'journal-des-francophones-belgique'];
        DB::table('press_publications')->whereIn('slug', $belgianSlugs)->update(['country' => 'BE', 'language' => 'fr']);

        $canadianSlugs = ['le-devoir-canada', 'la-presse-canada', 'radio-canada', 'le-petit-journal-montreal'];
        DB::table('press_publications')->whereIn('slug', $canadianSlugs)->update(['country' => 'CA', 'language' => 'fr']);

        $africanSlugs = ['jeune-afrique', 'mondafrique', 'africanews', 'rfi-afrique', 'le-monde-afrique'];
        DB::table('press_publications')->whereIn('slug', $africanSlugs)->update(['country' => 'INTL', 'language' => 'fr']);

        // ── 2-4. Dispatch scraping jobs (skipped if queue driver unavailable) ──
        try {
            // ── 2. Dispatch team-page scraping for ALL pending publications ────
            $pubs = DB::table('press_publications')
                ->whereIn('status', ['pending', 'failed'])
                ->orWhereNull('status')
                ->get(['id', 'slug']);

            $delay = 0;
            foreach ($pubs as $pub) {
                ScrapePressPublicationJob::dispatch($pub->id)
                    ->onQueue('scraper')
                    ->delay(now()->addSeconds($delay));
                $delay += 5;
            }

            // ── 3. Dispatch bylines scraping for configured publications ───────
            $bylinePubs = DB::table('press_publications')
                ->where(function ($q) {
                    $q->whereNotNull('authors_url')->orWhereNotNull('articles_url');
                })
                ->get(['id']);

            $delay = 10;
            foreach ($bylinePubs as $pub) {
                ScrapePublicationAuthorsJob::dispatch($pub->id, true)
                    ->onQueue('scraper')
                    ->delay(now()->addSeconds($delay));
                $delay += 10;
            }

            // ── 4. Dispatch journalist directory scraping ─────────────────────
            $dirs = DB::table('journalist_directory_sources')
                ->where('status', '!=', 'running')
                ->get(['slug']);

            $delay = 30;
            foreach ($dirs as $dir) {
                ScrapeJournalistDirectoryJob::dispatch($dir->slug)
                    ->onQueue('scraper')
                    ->delay(now()->addSeconds($delay));
                $delay += 60;
            }

            \Illuminate\Support\Facades\Log::info('Press scraping launched: ' . $pubs->count() . ' team pages, ' . $bylinePubs->count() . ' bylines, ' . $dirs->count() . ' directories');
        } catch (\Throwable $e) {
            // Queue driver not available in this environment — skip job dispatch
            \Illuminate\Support\Facades\Log::warning('Press scraping jobs not dispatched (queue unavailable): ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        // Cannot undo dispatched jobs
    }
};
