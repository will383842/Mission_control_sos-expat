<?php

namespace App\Console\Commands;

use App\Services\Content\KnowledgeBaseService;
use Illuminate\Console\Command;

/**
 * kb:export-json — dump the Knowledge Base to a pinnable JSON file.
 *
 * Downstream services (Blog_sos-expat_frontend, Social Multi-Platform,
 * Backlink Engine) can commit the resulting file as their pinned reference,
 * updating on demand rather than fetching at runtime.
 *
 * Usage:
 *   php artisan kb:export-json                           # public subset -> storage/kb-exports/kb-{version}.json
 *   php artisan kb:export-json --full                    # full KB (internal only)
 *   php artisan kb:export-json --to=/path/to/kb.json     # custom output path
 *   php artisan kb:export-json --stdout                  # print to STDOUT (pipe-friendly)
 *   php artisan kb:export-json --pretty=false            # compact JSON
 */
class ExportKnowledgeBaseJsonCommand extends Command
{
    protected $signature = 'kb:export-json
        {--full : Include sensitive sections (internal use only)}
        {--to= : Output path (default: storage/kb-exports/kb-{version}.json)}
        {--stdout : Print to STDOUT instead of writing a file}
        {--pretty=true : Pretty-print JSON (default true)}';

    protected $description = 'Export the Knowledge Base to a pinnable JSON file for downstream services.';

    public function handle(): int
    {
        $svc = new KnowledgeBaseService();
        $full = (bool) $this->option('full');
        $stdout = (bool) $this->option('stdout');
        $pretty = filter_var($this->option('pretty'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;

        $payload = $full ? $svc->toFullArray() : $svc->toPublicArray();
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }
        $json = json_encode($payload, $flags);

        if ($stdout) {
            $this->line($json);
            return self::SUCCESS;
        }

        $outPath = $this->option('to');
        if (!$outPath) {
            $version = $svc->getVersion();
            $scope = $full ? 'full' : 'public';
            $outPath = storage_path("kb-exports/kb-{$version}-{$scope}.json");
        }

        $dir = dirname($outPath);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            $this->error("Cannot create directory {$dir}");
            return self::FAILURE;
        }

        $bytes = file_put_contents($outPath, $json);
        if ($bytes === false) {
            $this->error("Cannot write to {$outPath}");
            return self::FAILURE;
        }

        $this->info("Wrote {$bytes} bytes to {$outPath}");
        $this->line("  kb_version    : " . $svc->getVersion());
        $this->line("  kb_updated_at : " . $svc->getUpdatedAt());
        $this->line("  scope         : " . ($full ? 'full (INTERNAL)' : 'public'));

        return self::SUCCESS;
    }
}
