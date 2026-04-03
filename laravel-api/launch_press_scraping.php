<?php

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Jobs\ScrapePressPublicationJob;
use App\Jobs\ScrapePublicationAuthorsJob;
use App\Jobs\ScrapeJournalistDirectoryJob;
use Illuminate\Support\Facades\DB;

// ── 1. Team pages — toutes les publications pending/failed ────────────────
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
echo "Team pages: " . $pubs->count() . " jobs queues (stagger 5s)\n";

// ── 2. Bylines — publications avec authors_url ou articles_url ────────────
$bylinePubs = DB::table('press_publications')
    ->where(function ($q) {
        $q->whereNotNull('authors_url')->orWhereNotNull('articles_url');
    })
    ->get(['id']);

$delay = 60; // Commence apres la premiere vague
foreach ($bylinePubs as $pub) {
    ScrapePublicationAuthorsJob::dispatch($pub->id, true)
        ->onQueue('scraper')
        ->delay(now()->addSeconds($delay));
    $delay += 10;
}
echo "Bylines: " . $bylinePubs->count() . " jobs queues (stagger 10s, debut dans 60s)\n";

// ── 3. Annuaires de journalistes ──────────────────────────────────────────
$dirs = DB::table('journalist_directory_sources')
    ->where('status', '!=', 'running')
    ->orWhereNull('status')
    ->get(['slug']);

$delay = 120;
foreach ($dirs as $dir) {
    ScrapeJournalistDirectoryJob::dispatch($dir->slug)
        ->onQueue('scraper')
        ->delay(now()->addSeconds($delay));
    $delay += 90;
}
echo "Annuaires journalistes: " . $dirs->count() . " jobs queues (stagger 90s, debut dans 2min)\n";

// ── Stats finales ─────────────────────────────────────────────────────────
$total = DB::table('jobs')->count();
echo "\nTotal jobs en queue: $total\n";
echo "Duree estimee: ~" . round(($pubs->count() * 5 + $bylinePubs->count() * 10 + $dirs->count() * 90) / 3600, 1) . "h\n";
