<?php

namespace App\Console\Commands;

use App\Models\RssBlogFeed;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Option D2 — Import de plusieurs OPML publics depuis GitHub
 * (repos curés par la communauté).
 *
 * Télécharge chaque URL, parse OPML, insère dans rss_blog_feeds
 * (skip doublons). Idempotent.
 *
 * Usage : php artisan opml:import-public-repos [--dry-run]
 */
class ImportPublicOpmlReposCommand extends Command
{
    protected $signature = 'opml:import-public-repos {--dry-run : Compte sans ecrire}';

    protected $description = 'Import public OPML files from curated GitHub repos';

    /**
     * URLs OPML publiques + metadata pour classification.
     * Toutes vérifiées HTTP 200 au moment de l'ajout.
     *
     * @var array<int,array{url:string,category:string,language:string,source:string}>
     */
    private const OPML_SOURCES = [
        // plenaryapp/awesome-rss-feeds — ~20-50 feeds par fichier, curés
        ['url' => 'https://raw.githubusercontent.com/plenaryapp/awesome-rss-feeds/master/recommended/with_category/Travel.opml',             'category' => 'voyage',       'language' => 'en', 'source' => 'awesome-rss-feeds/travel'],
        ['url' => 'https://raw.githubusercontent.com/plenaryapp/awesome-rss-feeds/master/recommended/with_category/Business%20%26%20Economy.opml', 'category' => 'business',     'language' => 'en', 'source' => 'awesome-rss-feeds/business'],
        ['url' => 'https://raw.githubusercontent.com/plenaryapp/awesome-rss-feeds/master/recommended/with_category/Food.opml',               'category' => 'food',         'language' => 'en', 'source' => 'awesome-rss-feeds/food'],
        ['url' => 'https://raw.githubusercontent.com/plenaryapp/awesome-rss-feeds/master/recommended/with_category/Personal%20finance.opml', 'category' => 'finance',      'language' => 'en', 'source' => 'awesome-rss-feeds/finance'],
        ['url' => 'https://raw.githubusercontent.com/plenaryapp/awesome-rss-feeds/master/recommended/with_category/News.opml',               'category' => 'news',         'language' => 'en', 'source' => 'awesome-rss-feeds/news'],
        ['url' => 'https://raw.githubusercontent.com/plenaryapp/awesome-rss-feeds/master/recommended/with_category/Startups.opml',           'category' => 'startups',     'language' => 'en', 'source' => 'awesome-rss-feeds/startups'],
        ['url' => 'https://raw.githubusercontent.com/plenaryapp/awesome-rss-feeds/master/recommended/with_category/Tech.opml',               'category' => 'tech',         'language' => 'en', 'source' => 'awesome-rss-feeds/tech'],
        ['url' => 'https://raw.githubusercontent.com/plenaryapp/awesome-rss-feeds/master/recommended/with_category/Web%20Development.opml',  'category' => 'webdev',       'language' => 'en', 'source' => 'awesome-rss-feeds/webdev'],
        ['url' => 'https://raw.githubusercontent.com/plenaryapp/awesome-rss-feeds/master/recommended/with_category/Programming.opml',        'category' => 'programming',  'language' => 'en', 'source' => 'awesome-rss-feeds/programming'],
        ['url' => 'https://raw.githubusercontent.com/plenaryapp/awesome-rss-feeds/master/recommended/with_category/Photography.opml',        'category' => 'photo',        'language' => 'en', 'source' => 'awesome-rss-feeds/photo'],
        ['url' => 'https://raw.githubusercontent.com/plenaryapp/awesome-rss-feeds/master/recommended/with_category/Science.opml',            'category' => 'science',      'language' => 'en', 'source' => 'awesome-rss-feeds/science'],
        ['url' => 'https://raw.githubusercontent.com/plenaryapp/awesome-rss-feeds/master/recommended/with_category/Fashion.opml',            'category' => 'fashion',      'language' => 'en', 'source' => 'awesome-rss-feeds/fashion'],
        ['url' => 'https://raw.githubusercontent.com/plenaryapp/awesome-rss-feeds/master/recommended/with_category/Beauty.opml',             'category' => 'beauty',       'language' => 'en', 'source' => 'awesome-rss-feeds/beauty'],

        // kilimchoi/engineering-blogs — ~200 blogs de grandes entreprises tech
        ['url' => 'https://raw.githubusercontent.com/kilimchoi/engineering-blogs/master/engineering_blogs.opml', 'category' => 'tech', 'language' => 'en', 'source' => 'kilimchoi/engineering-blogs'],
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $this->info('=== Import Public OPML Repos ===');
        $this->line('  Sources : ' . count(self::OPML_SOURCES));
        $this->line('  Dry-run : ' . ($dryRun ? 'yes' : 'no'));
        $this->newLine();

        $totalOutlines = 0;
        $totalAdded = 0;
        $totalSkipped = 0;
        $totalErrors = 0;

        foreach (self::OPML_SOURCES as $src) {
            $this->line("→ {$src['source']}");
            try {
                $body = Http::timeout(30)
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; SOS-Expat-OpmlBot/1.0)'])
                    ->get($src['url'])
                    ->throw()
                    ->body();
            } catch (\Throwable $e) {
                $this->warn("  fetch failed: {$e->getMessage()}");
                $totalErrors++;
                continue;
            }

            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($body);
            if ($xml === false) {
                $this->warn('  XML parse failed');
                $totalErrors++;
                continue;
            }

            $outlines = [];
            foreach ($xml->xpath('//outline') as $outline) {
                $attrs = $outline->attributes();
                if (!isset($attrs['xmlUrl'])) continue;
                $outlines[] = [
                    'xmlUrl'  => (string) $attrs['xmlUrl'],
                    'htmlUrl' => isset($attrs['htmlUrl']) ? (string) $attrs['htmlUrl'] : null,
                    'title'   => isset($attrs['title']) ? (string) $attrs['title'] : (isset($attrs['text']) ? (string) $attrs['text'] : null),
                ];
            }
            $totalOutlines += count($outlines);
            $this->line("  {$src['source']} → " . count($outlines) . ' feeds trouvés');

            if ($dryRun) continue;

            $srcAdded = 0;
            $srcSkipped = 0;
            foreach ($outlines as $o) {
                if (!filter_var($o['xmlUrl'], FILTER_VALIDATE_URL)) continue;
                if (RssBlogFeed::where('url', $o['xmlUrl'])->exists()) {
                    $srcSkipped++;
                    continue;
                }
                try {
                    RssBlogFeed::create([
                        'name'     => substr($o['title'] ?? parse_url($o['xmlUrl'], PHP_URL_HOST) ?: 'Feed', 0, 255),
                        'url'      => $o['xmlUrl'],
                        'base_url' => $o['htmlUrl'],
                        'language' => $src['language'],
                        'category' => $src['category'],
                        'active'   => true,
                        'fetch_about' => true,
                        'fetch_pattern_inference' => false,
                        'fetch_interval_hours' => 24,
                        'notes'    => "Importé depuis OPML public : {$src['source']}",
                    ]);
                    $srcAdded++;
                } catch (\Throwable $e) {
                    $totalErrors++;
                }
            }
            $totalAdded += $srcAdded;
            $totalSkipped += $srcSkipped;
            $this->line("  → added={$srcAdded} skipped={$srcSkipped}");
        }

        $this->newLine();
        $this->info('=== TOTAL ===');
        $this->line("  Outlines scannes : {$totalOutlines}");
        $this->line("  Added            : {$totalAdded}");
        $this->line("  Skipped (dup)    : {$totalSkipped}");
        $this->line("  Errors           : {$totalErrors}");

        Log::info('ImportPublicOpmlReposCommand: done', [
            'outlines' => $totalOutlines,
            'added'    => $totalAdded,
            'skipped'  => $totalSkipped,
            'errors'   => $totalErrors,
        ]);

        return Command::SUCCESS;
    }
}
