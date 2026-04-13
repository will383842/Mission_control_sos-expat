<?php

namespace App\Console\Commands;

use App\Services\Content\GoogleSuggestService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Découvrir les vraies requêtes Google (PAA style) pour un pays via Google Autocomplete.
 *
 * 100% gratuit — aucune API key, aucun quota, aucun compte.
 * Alimente la table country_paa_questions qui sert de source aux 35 Q/R de la campagne pays.
 *
 * Usage :
 *   php artisan paa:discover TH                   # Thaïlande (dry run)
 *   php artisan paa:discover TH --apply           # Sauvegarder en DB
 *   php artisan paa:discover TH --apply --fresh   # Vider les questions existantes avant
 *   php artisan paa:discover --batch=TH,VN,SG     # Plusieurs pays d'un coup
 *   php artisan paa:discover --all-campaign        # Tous les pays de la queue campagne
 */
class DiscoverPaaCommand extends Command
{
    protected $signature = 'paa:discover
        {country? : Code ISO 2 lettres (ex: TH)}
        {--batch= : Plusieurs pays séparés par virgule (ex: TH,VN,SG)}
        {--all-campaign : Découvrir pour tous les pays de la queue campagne}
        {--lang=fr : Langue cible (défaut: fr)}
        {--apply : Sauvegarder en DB (défaut: dry run)}
        {--fresh : Supprimer les questions existantes avant insertion}
        {--limit=80 : Nombre max de questions à conserver par pays}';

    protected $description = 'Découvrir les vraies requêtes Google pour un pays (Google Autocomplete gratuit)';

    public function handle(GoogleSuggestService $suggest): int
    {
        $lang  = $this->option('lang');
        $apply = (bool) $this->option('apply');
        $fresh = (bool) $this->option('fresh');
        $limit = (int) $this->option('limit');

        // Résoudre la liste de pays
        $countries = $this->resolveCountries();
        if (empty($countries)) {
            $this->error('Aucun pays à traiter. Fournir un code pays, --batch ou --all-campaign.');
            return 1;
        }

        $this->info($apply ? '=== MODE APPLY ===' : '=== DRY RUN (ajouter --apply pour sauvegarder) ===');
        $this->info("Pays : " . implode(', ', array_column($countries, 'code')));
        $this->newLine();

        foreach ($countries as ['code' => $code, 'name' => $name, 'prep' => $prep]) {
            $this->processCountry($suggest, $code, $name, $prep, $lang, $apply, $fresh, $limit);
            $this->newLine();
        }

        return Command::SUCCESS;
    }

    private function processCountry(
        GoogleSuggestService $suggest,
        string $code,
        string $name,
        string $prep,
        string $lang,
        bool $apply,
        bool $fresh,
        int $limit
    ): void {
        $this->line("=== {$name} ({$code}) ===");

        if ($apply && $fresh) {
            DB::table('country_paa_questions')
                ->where('country_code', $code)
                ->where('language', $lang)
                ->delete();
            $this->line("  ↳ Questions existantes supprimées (--fresh)");
        }

        $this->line("  Interrogation de Google Suggest (~60 seeds)...");

        $results = $suggest->discoverForCountry($code, $name, $prep, $lang);

        $this->line("  Suggestions brutes    : " . count($results));

        // Limiter et ordonner (score = position suggest → 0 = plus populaire)
        $results = array_slice($results, 0, $limit);

        // Affichage
        $table = [];
        foreach ($results as $r) {
            $table[] = [
                mb_substr($r['question'], 0, 70),
                $r['intent'],
                $r['content_type'],
                $r['score'],
            ];
        }
        $this->table(['Question', 'Intent', 'Type', 'Score'], $table);

        if (!$apply) {
            $this->comment("  → Dry run. Ajouter --apply pour sauvegarder.");
            return;
        }

        // Upsert en DB (ignore les doublons)
        $inserted = 0;
        foreach ($results as $row) {
            try {
                $exists = DB::table('country_paa_questions')
                    ->where('country_code', $row['country_code'])
                    ->where('language', $row['language'])
                    ->where('question', $row['question'])
                    ->exists();

                if (!$exists) {
                    DB::table('country_paa_questions')->insert(array_merge($row, [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]));
                    $inserted++;
                }
            } catch (\Throwable $e) {
                // Doublon unique constraint → skip silencieux
            }
        }

        $this->info("  ✓ {$inserted} questions insérées en DB.");
    }

    /**
     * Résoudre la liste de pays à traiter.
     */
    private function resolveCountries(): array
    {
        $countryPreps  = CountryCampaignCommand::COUNTRY_PREP  ?? [];
        $countryOrder  = CountryCampaignCommand::COUNTRY_ORDER ?? [];

        // --all-campaign : lire la queue depuis la DB
        if ($this->option('all-campaign')) {
            $config = DB::table('content_orchestrator_config')->first();
            $queue  = json_decode($config->campaign_country_queue ?? '[]', true);
            if (empty($queue)) {
                $queue = array_keys($countryOrder);
            }
            return $this->buildCountryList($queue, $countryOrder, $countryPreps);
        }

        // --batch : plusieurs pays séparés par virgule
        if ($batch = $this->option('batch')) {
            $codes = array_map('trim', explode(',', strtoupper($batch)));
            return $this->buildCountryList($codes, $countryOrder, $countryPreps);
        }

        // Pays unique en argument
        if ($code = $this->argument('country')) {
            return $this->buildCountryList([strtoupper($code)], $countryOrder, $countryPreps);
        }

        return [];
    }

    private function buildCountryList(array $codes, array $nameMap, array $prepMap): array
    {
        $list = [];
        foreach ($codes as $code) {
            $name = $nameMap[$code] ?? $code;
            $prep = ($prepMap[$code] ?? 'en') . ' ' . $name;
            // Corriger "a Singapour" → "à Singapour"
            if (str_starts_with($prep, 'a ')) {
                $prep = 'à ' . mb_substr($prep, 2);
            }
            $list[] = ['code' => $code, 'name' => $name, 'prep' => $prep];
        }
        return $list;
    }
}
