<?php

namespace App\Console\Commands;

use App\Jobs\GenerateLandingPageJob;
use App\Models\LandingPage;
use App\Services\Content\LandingGenerationService;
use Illuminate\Console\Command;

/**
 * Import manual landing pages (JSON produit par Claude Opus 4.7 via chat Max).
 *
 * JSON attendu :
 * {
 *   "params": {
 *     "audience_type": "lawyers",
 *     "template_id":  "general",
 *     "country_code": "VN",
 *     "language":     "fr",
 *     "problem_slug"| "category_slug" | "user_profile" | "origin_nationality": "..."
 *   },
 *   "parsed": {
 *     "title": "...",
 *     "url_slug": "...",
 *     "meta_title": "...",
 *     "meta_description": "...",
 *     "keywords_primary": "...",
 *     "keywords_secondary": ["..."],
 *     "sections": [...],
 *     "cta_links": [...],
 *     "lsi_keywords": ["..."],
 *     "internal_links": [...]
 *   }
 * }
 */
class ImportLandingPageCommand extends Command
{
    protected $signature = 'landings:import
        {--file= : Chemin JSON unique}
        {--dir= : Répertoire contenant plusieurs JSONs à importer}
        {--dispatch-variants=true : Dispatche les 8 variantes langues via Haiku 4.5}
        {--force-update=false : Si true, hydrate les landings existantes (même slug) au lieu de les skip — utilisé pour enrichir les landings vides}';

    protected $description = 'Importe des landing pages JSON pré-générées (Opus chat) et dispatche les variantes langues.';

    public function handle(LandingGenerationService $service): int
    {
        $files = $this->collectFiles();
        if (empty($files)) {
            $this->error('Aucun fichier JSON trouvé. Fournir --file ou --dir.');
            return self::FAILURE;
        }

        $this->info('Fichiers à importer : ' . count($files));
        $dispatchVariants = filter_var($this->option('dispatch-variants'), FILTER_VALIDATE_BOOLEAN);
        $forceUpdate      = filter_var($this->option('force-update'), FILTER_VALIDATE_BOOLEAN);

        if ($forceUpdate) {
            $this->warn('force-update=true → les landings existantes seront ÉCRASÉES avec le nouveau contenu.');
        }

        $okCount = 0;
        $koCount = 0;

        foreach ($files as $file) {
            $this->line('');
            $this->info("▶ {$file}");

            $raw = file_get_contents($file);
            $data = json_decode($raw, true);
            if (!is_array($data) || !isset($data['params']) || !isset($data['parsed'])) {
                $this->error('  ✗ JSON invalide (clés params + parsed requises)');
                $koCount++;
                continue;
            }

            try {
                if ($forceUpdate) {
                    $data['params']['force_update'] = true;
                }
                $landing = $service->importManual($data['params'], $data['parsed']);
                $okCount++;
                $this->info("  ✓ LP #{$landing->id} · slug={$landing->slug} · seo={$landing->seo_score}");

                if ($dispatchVariants && empty($data['params']['parent_id'])) {
                    $this->dispatchVariants($landing, $data['params']);
                }
            } catch (\Throwable $e) {
                $koCount++;
                $this->error('  ✗ Échec : ' . $e->getMessage());
            }
        }

        $this->line('');
        $this->info("Terminé : {$okCount} OK · {$koCount} KO");

        return $koCount > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** @return string[] */
    private function collectFiles(): array
    {
        $files = [];
        if ($path = $this->option('file')) {
            if (!is_file($path)) {
                $this->error("Fichier introuvable : {$path}");
                return [];
            }
            $files[] = $path;
        } elseif ($dir = $this->option('dir')) {
            if (!is_dir($dir)) {
                $this->error("Répertoire introuvable : {$dir}");
                return [];
            }
            $files = array_values(glob(rtrim($dir, '/\\') . '/*.json') ?: []);
            sort($files);
        }
        return $files;
    }

    private function dispatchVariants(LandingPage $master, array $params): void
    {
        $allLanguages = ['fr', 'en', 'es', 'de', 'pt', 'ar', 'hi', 'zh', 'ru'];
        $primary = $params['language'] ?? 'fr';
        $delay = 10;

        foreach ($allLanguages as $lang) {
            if ($lang === $primary) {
                continue;
            }

            GenerateLandingPageJob::dispatch(array_merge($params, [
                'language'                   => $lang,
                'parent_id'                  => $master->id,
                'use_cheap_model'            => true,
                'featured_image_url'         => $master->featured_image_url,
                'featured_image_alt'         => $master->featured_image_alt,
                'featured_image_attribution' => $master->featured_image_attribution,
                'photographer_name'          => $master->photographer_name,
                'photographer_url'           => $master->photographer_url,
            ]))
                ->delay(now()->addSeconds($delay))
                ->onQueue('landings');

            $this->line("    → variant {$lang} dispatché (+{$delay}s)");
            $delay += 8;
        }
    }
}
