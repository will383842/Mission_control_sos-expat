<?php

namespace App\Console\Commands;

use App\Models\GeneratedArticle;
use App\Services\Seo\JsonLdService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Regenerate JSON-LD for published articles that are missing it.
 */
class FixMissingJsonLdCommand extends Command
{
    protected $signature = 'articles:fix-missing-jsonld {--dry-run : Show what would be fixed without changing anything}';
    protected $description = 'Regenerate JSON-LD for published articles where json_ld is NULL, empty, or {}';

    public function handle(JsonLdService $jsonLdService): int
    {
        $dryRun = $this->option('dry-run');

        $articles = GeneratedArticle::where('status', 'published')
            ->where(function ($q) {
                $q->whereNull('json_ld')
                  ->orWhereRaw("json_ld::text = '{}'")
                  ->orWhereRaw("json_ld::text = '[]'")
                  ->orWhereRaw("json_ld::text = 'null'");
            })
            ->get();

        $count = $articles->count();
        $this->info("Found {$count} published articles with missing JSON-LD" . ($dryRun ? ' (dry run)' : ''));

        if ($count === 0) {
            return self::SUCCESS;
        }

        $fixed = 0;
        $failed = 0;

        foreach ($articles as $article) {
            $this->line("  #{$article->id} [{$article->language}] {$article->title}");

            if ($dryRun) {
                $fixed++;
                continue;
            }

            try {
                $schema = $jsonLdService->generateFullSchema($article);

                $article->update(['json_ld' => $schema]);

                $this->info("    -> JSON-LD generated (" . count($schema['@graph'] ?? []) . " schema items)");
                $fixed++;
            } catch (\Throwable $e) {
                $this->error("    -> FAILED: {$e->getMessage()}");
                Log::error('FixMissingJsonLd failed', [
                    'article_id' => $article->id,
                    'error' => $e->getMessage(),
                ]);
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Done: {$fixed} fixed, {$failed} failed" . ($dryRun ? ' (dry run -- no changes made)' : ''));

        return self::SUCCESS;
    }
}
