<?php

namespace App\Console\Commands;

use App\Models\GeneratedArticle;
use App\Services\AI\OpenAiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * One-time fix: re-translate titles that were contaminated with article body content.
 * Detects titles with LENGTH > 120 (normal titles are 30-80 chars) and re-translates them.
 */
class FixCorruptedTitlesCommand extends Command
{
    protected $signature = 'articles:fix-corrupted-titles {--dry-run : Show what would be fixed without changing anything}';
    protected $description = 'Fix translated article titles contaminated with body content (LENGTH > 120)';

    public function handle(OpenAiService $openAi): int
    {
        $dryRun = $this->option('dry-run');

        $corrupted = GeneratedArticle::where('status', 'published')
            ->whereNotNull('parent_article_id')
            ->whereRaw('LENGTH(title) > 120')
            ->get();

        $this->info("Found {$corrupted->count()} corrupted titles" . ($dryRun ? ' (dry run)' : ''));

        $langNames = [
            'fr' => 'français', 'en' => 'English', 'es' => 'español', 'de' => 'Deutsch',
            'pt' => 'português', 'ru' => 'русский', 'zh' => '中文', 'ar' => 'العربية', 'hi' => 'हिन्दी',
        ];

        $fixed = 0;
        $failed = 0;

        foreach ($corrupted as $article) {
            $parent = GeneratedArticle::find($article->parent_article_id);
            if (!$parent) {
                $this->warn("  #{$article->id} — parent #{$article->parent_article_id} not found, skipping");
                continue;
            }

            $originalTitle = strip_tags($parent->title);
            $toName = $langNames[$article->language] ?? $article->language;

            $this->line("  #{$article->id} [{$article->language}] current: " . mb_substr($article->title, 0, 80) . '...');
            $this->line("    parent title: {$originalTitle}");

            if ($dryRun) {
                $fixed++;
                continue;
            }

            $result = $openAi->complete(
                "Translate this article TITLE to {$toName}. Return ONLY the translated title on a single line. No explanation, no HTML, no extra text.",
                $originalTitle,
                ['temperature' => 0.3, 'max_tokens' => 150]
            );

            if ($result['success']) {
                $newTitle = strip_tags(trim($result['content']));
                $newTitle = preg_replace('/^```\w*\s*|\s*```$/m', '', $newTitle);
                $newTitle = trim(explode("\n", $newTitle)[0], " \t\n\r\"'");

                if (!empty($newTitle) && mb_strlen($newTitle) <= 120) {
                    $article->update(['title' => $newTitle]);
                    $this->info("    ✓ fixed: {$newTitle}");
                    $fixed++;
                } else {
                    $this->error("    ✗ new title still too long or empty (" . mb_strlen($newTitle) . " chars)");
                    $failed++;
                }
            } else {
                $this->error("    ✗ API call failed: " . ($result['error'] ?? 'unknown'));
                $failed++;
            }

            usleep(500_000); // 500ms between API calls
        }

        $this->newLine();
        $this->info("Done: {$fixed} fixed, {$failed} failed" . ($dryRun ? ' (dry run — no changes made)' : ''));

        return self::SUCCESS;
    }
}
