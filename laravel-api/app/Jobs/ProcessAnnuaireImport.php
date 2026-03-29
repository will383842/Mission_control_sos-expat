<?php

namespace App\Jobs;

use App\Models\AnnuaireImportJob;
use App\Models\CountryDirectory;
use App\Services\WikidataService;
use App\Services\OverpassService;
use App\Services\PerplexityPracticalLinksService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job principal d'import de l'annuaire.
 * Lance en background depuis l'admin UI ou depuis artisan.
 *
 * Sources :
 *   wikidata   = ambassades (toutes nationalités, 9 langues via labels Wikidata)
 *   overpass   = institutions physiques OpenStreetMap (hôpitaux, banques, gares…)
 *   perplexity = liens web officiels recherchés sur le vrai web (URLs vérifiées, zéro hallucination)
 */
class ProcessAnnuaireImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout   = 7200; // 2h max (pour import "all")
    public int $tries     = 1;    // Pas de retry automatique
    public int $backoff   = 0;

    public function __construct(public int $importJobId) {}

    public function handle(
        WikidataService                  $wikidata,
        OverpassService                  $overpass,
        PerplexityPracticalLinksService  $perplexity
    ): void {
        $job = AnnuaireImportJob::findOrFail($this->importJobId);

        if ($job->status === 'cancelled') return;

        $job->update(['status' => 'running', 'started_at' => now()]);
        $job->appendLog("Démarrage import [{$job->source}] scope={$job->scope_type}:{$job->scope_value}");

        try {
            match ($job->source) {
                'wikidata'   => $this->runWikidata($job, $wikidata),
                'overpass'   => $this->runOverpass($job, $overpass),
                'perplexity' => $this->runPerplexity($job, $perplexity),
                default      => throw new \InvalidArgumentException("Source inconnue: {$job->source}"),
            };

            $job->update([
                'status'       => 'completed',
                'completed_at' => now(),
            ]);
            $job->appendLog("Import terminé — {$job->total_inserted} insérés, {$job->total_updated} mis à jour, {$job->total_errors} erreurs.");

        } catch (\Throwable $e) {
            Log::error("ProcessAnnuaireImport failed #{$this->importJobId}", ['error' => $e->getMessage()]);
            $job->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at'  => now(),
            ]);
            $job->appendLog("ERREUR: " . $e->getMessage());
        }
    }

    // ── Wikidata : ambassades ─────────────────────────────────────────────────

    private function runWikidata(AnnuaireImportJob $job, WikidataService $wikidata): void
    {
        $isoCodes = $this->resolveIsoCodes($job, WikidataService::getSupportedIsoCodes());
        $job->update(['total_expected' => count($isoCodes)]);
        $job->appendLog(count($isoCodes) . " nationalités à importer depuis Wikidata");

        foreach ($isoCodes as $iso) {
            if ($job->isCancelled()) { $job->appendLog("Import annulé."); return; }

            $name = WikidataService::COUNTRY_NAMES_FR[$iso] ?? $iso;
            $job->appendLog("[{$iso}] Interrogation Wikidata pour {$name}...");

            try {
                $bindings  = $wikidata->getEmbassiesByNationality($iso);
                $embassies = $wikidata->normalizeEmbassies($bindings, $iso);
                $job->appendLog("[{$iso}] {$name} : " . count($embassies) . " ambassades");

                $inserted = $updated = $errors = 0;
                foreach ($embassies as $data) {
                    try {
                        $existing = CountryDirectory::where('country_code', $data['country_code'])
                            ->where('nationality_code', $data['nationality_code'])
                            ->where('url', $data['url'])
                            ->first();

                        if ($existing) {
                            $existing->update(array_filter($data, fn($v) => $v !== null));
                            $updated++;
                        } else {
                            CountryDirectory::create($data);
                            $inserted++;
                        }
                    } catch (\Exception $e) {
                        $errors++;
                    }
                }

                $job->incrementProcessed($inserted, $updated, $errors);

            } catch (\Exception $e) {
                $job->appendLog("[{$iso}] Erreur : " . $e->getMessage());
                $job->incrementProcessed(0, 0, 1);
            }

            sleep(1); // rate limit Wikidata
        }
    }

    // ── OpenStreetMap : institutions physiques ────────────────────────────────

    private function runOverpass(AnnuaireImportJob $job, OverpassService $overpass): void
    {
        $countries   = $this->resolveIsoCodes($job, array_keys(WikidataService::COUNTRY_QID));
        $categories  = $job->categories ?? OverpassService::SUPPORTED_CATEGORIES;
        $total       = count($countries) * count($categories);

        $job->update(['total_expected' => $total]);
        $job->appendLog(count($countries) . " pays × " . count($categories) . " catégories = {$total} requêtes Overpass");

        foreach ($countries as $iso) {
            if ($job->isCancelled()) { $job->appendLog("Import annulé."); return; }

            $name = WikidataService::COUNTRY_NAMES_FR[$iso] ?? $iso;

            foreach ($categories as $cat) {
                if (!in_array($cat, OverpassService::SUPPORTED_CATEGORIES)) continue;

                try {
                    $elements = $overpass->getByCountryAndCategory($iso, $cat);
                    $entries  = $overpass->normalizeResults($elements, $iso, $cat);
                    $job->appendLog("[{$iso}] {$name}/{$cat} : " . count($entries) . " éléments");

                    $inserted = $updated = $errors = 0;
                    foreach ($entries as $data) {
                        try {
                            CountryDirectory::updateOrCreate(
                                ['country_code' => $data['country_code'], 'nationality_code' => null, 'url' => $data['url']],
                                $data
                            );
                            $inserted++;
                        } catch (\Exception $e) { $errors++; }
                    }

                    $job->incrementProcessed($inserted, 0, $errors);
                } catch (\Exception $e) {
                    $job->appendLog("[{$iso}/{$cat}] Erreur : " . $e->getMessage());
                    $job->incrementProcessed(0, 0, 1);
                }

                sleep(1); // rate limit Overpass
            }
        }
    }

    // ── Perplexity : liens web officiels (vraie recherche web, URLs vérifiées) ─

    private function runPerplexity(AnnuaireImportJob $job, PerplexityPracticalLinksService $perplexity): void
    {
        if (!$perplexity->isConfigured()) {
            throw new \RuntimeException("PERPLEXITY_API_KEY non configurée dans .env");
        }

        $countries  = $this->resolveIsoCodes($job, array_keys(WikidataService::COUNTRY_QID));
        $categories = $job->categories ?? PerplexityPracticalLinksService::SUPPORTED_CATEGORIES;
        $total      = count($countries) * count($categories);

        $job->update(['total_expected' => $total]);
        $job->appendLog(count($countries) . " pays × " . count($categories) . " catégories = {$total} requêtes Perplexity");

        foreach ($countries as $iso) {
            if ($job->isCancelled()) { $job->appendLog("Import annulé."); return; }

            $name = WikidataService::COUNTRY_NAMES_FR[$iso] ?? $iso;

            foreach ($categories as $cat) {
                if (!in_array($cat, PerplexityPracticalLinksService::SUPPORTED_CATEGORIES)) continue;

                try {
                    $entries = $perplexity->generateForCountry($iso, $cat);
                    $job->appendLog("[{$iso}] {$name}/{$cat} : " . count($entries) . " liens trouvés");

                    $inserted = $updated = $errors = 0;
                    foreach ($entries as $data) {
                        try {
                            $existing = CountryDirectory::where('country_code', $data['country_code'])
                                ->whereNull('nationality_code')
                                ->where('url', $data['url'])
                                ->first();

                            if ($existing) {
                                $existing->update(array_filter($data, fn($v) => $v !== null));
                                $updated++;
                            } else {
                                CountryDirectory::create($data);
                                $inserted++;
                            }
                        } catch (\Exception $e) { $errors++; }
                    }

                    $job->incrementProcessed($inserted, $updated, $errors);
                } catch (\Exception $e) {
                    $job->appendLog("[{$iso}/{$cat}] Erreur : " . $e->getMessage());
                    $job->incrementProcessed(0, 0, 1);
                }

                sleep(2); // Perplexity : pause 2s entre requêtes (rate limit sonar)
            }
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Résout la liste des codes ISO selon le scope du job.
     */
    private function resolveIsoCodes(AnnuaireImportJob $job, array $allCodes): array
    {
        if ($job->scope_type === 'all' || empty($job->scope_value)) {
            return $allCodes;
        }

        // scope_value peut être "nationality" ou "country" selon la source
        $requested = array_map('strtoupper', array_filter(
            array_map('trim', explode(',', $job->scope_value))
        ));

        return array_values(array_filter($requested, fn($c) => strlen($c) === 2));
    }
}
