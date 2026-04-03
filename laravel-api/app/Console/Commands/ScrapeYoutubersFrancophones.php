<?php

namespace App\Console\Commands;

use App\Models\Influenceur;
use App\Models\User;
use App\Services\AI\PerplexityService;
use App\Services\YouTubeChannelScraperService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Recherche les YouTubeurs/créateurs de contenu francophones pays par pays,
 * extrait leurs emails, et les importe dans la table influenceurs.
 *
 * Usage :
 *   php artisan youtube:scrape-francophones                    → toute l'Asie
 *   php artisan youtube:scrape-francophones --pays=Thaïlande   → un seul pays
 *   php artisan youtube:scrape-francophones --region=europe    → autre région
 *   php artisan youtube:scrape-francophones --dry-run          → affiche sans sauvegarder
 */
class ScrapeYoutubersFrancophones extends Command
{
    protected $signature = 'youtube:scrape-francophones
                            {--region=asie : Région à traiter (asie, europe, amerique, afrique, all)}
                            {--pays= : Traiter un seul pays (ex: Thaïlande)}
                            {--dry-run : Affiche les résultats sans les sauvegarder}
                            {--skip-scrape : Ne scrape pas YouTube, utilise uniquement Perplexity}';

    protected $description = 'Recherche les YouTubeurs francophones pays par pays via Perplexity + YouTube scraping et les importe dans Mission Control';

    // =========================================================================
    // LISTE DES PAYS PAR RÉGION
    // =========================================================================

    private const REGIONS = [
        'asie' => [
            'Thaïlande', 'Vietnam', 'Cambodge', 'Laos', 'Myanmar',
            'Indonésie', 'Malaisie', 'Singapour', 'Philippines',
            'Chine', 'Japon', 'Corée du Sud', 'Inde', 'Népal',
            'Sri Lanka', 'Bangladesh', 'Pakistan', 'Mongolie',
            'Hong Kong', 'Taïwan', 'Macao', 'Brunei', 'Timor-Leste',
            'Birmanie', 'Bhoutan', 'Maldives',
        ],
        'europe' => [
            'France', 'Belgique', 'Suisse', 'Luxembourg', 'Monaco',
            'Allemagne', 'Espagne', 'Portugal', 'Italie', 'Pays-Bas',
            'Royaume-Uni', 'Irlande', 'Pologne', 'Roumanie', 'Autriche',
            'République Tchèque', 'Hongrie', 'Grèce', 'Suède', 'Norvège',
            'Danemark', 'Finlande', 'Turquie',
        ],
        'amerique' => [
            'Canada', 'États-Unis', 'Mexique', 'Brésil', 'Argentine',
            'Chili', 'Colombie', 'Pérou', 'Équateur', 'Uruguay',
            'Guadeloupe', 'Martinique', 'Guyane', 'Haïti',
        ],
        'afrique' => [
            'Maroc', 'Algérie', 'Tunisie', 'Sénégal', 'Côte d\'Ivoire',
            'Cameroun', 'Madagascar', 'Réunion', 'Maurice', 'Congo',
            'RDC', 'Bénin', 'Togo', 'Burkina Faso', 'Mali',
            'Niger', 'Gabon', 'Guinée', 'Djibouti',
        ],
        'moyen-orient' => [
            'Émirats Arabes Unis', 'Qatar', 'Arabie Saoudite', 'Liban',
            'Égypte', 'Jordanie', 'Bahreïn', 'Koweït', 'Oman', 'Israël',
        ],
        'oceanie' => [
            'Australie', 'Nouvelle-Zélande', 'Nouvelle-Calédonie',
            'Polynésie Française', 'Fidji', 'Vanuatu',
        ],
    ];

    // =========================================================================

    public function handle(PerplexityService $perplexity, YouTubeChannelScraperService $ytScraper): int
    {
        if (!$perplexity->isConfigured()) {
            $this->error('PERPLEXITY_API_KEY non configurée dans .env');
            return 1;
        }

        $isDryRun    = $this->option('dry-run');
        $skipScrape  = $this->option('skip-scrape');
        $singlePays  = $this->option('pays');
        $regionKey   = strtolower($this->option('region') ?? 'asie');

        // Résoudre la liste de pays
        if ($singlePays) {
            $countries = [$singlePays];
        } elseif ($regionKey === 'all') {
            $countries = array_merge(...array_values(self::REGIONS));
        } else {
            $countries = self::REGIONS[$regionKey] ?? self::REGIONS['asie'];
        }

        $this->info('=== YouTubeurs Francophones — ' . strtoupper($regionKey) . ' ===');
        $this->info(count($countries) . ' pays à traiter' . ($isDryRun ? ' [DRY RUN]' : ''));
        $this->newLine();

        // Récupérer l'ID du premier admin pour created_by
        $adminId = User::where('role', 'admin')->value('id') ?? User::first()?->id ?? 1;

        $totalFound  = 0;
        $totalSaved  = 0;
        $totalSkipped = 0;

        foreach ($countries as $pays) {
            $this->line("🔍 <fg=yellow>{$pays}</>");

            // --- Phase 1 : Perplexity découverte ---
            $channels = $this->discoverWithPerplexity($perplexity, $pays);

            if (empty($channels)) {
                $this->line("  → Aucune chaîne trouvée");
                continue;
            }

            $this->line("  → " . count($channels) . " chaînes trouvées");
            $totalFound += count($channels);

            foreach ($channels as $channel) {
                $name        = trim($channel['nom_chaine'] ?? $channel['name'] ?? '');
                $youtubeUrl  = trim($channel['url_youtube'] ?? $channel['youtube_url'] ?? '');
                $email       = strtolower(trim($channel['email'] ?? ''));
                $langue      = strtolower(trim($channel['langue'] ?? $channel['language'] ?? 'fr'));
                $createur    = trim($channel['nom_createur'] ?? $channel['creator'] ?? '');
                $abonnes     = $this->parseSubscribers($channel['abonnes'] ?? $channel['subscribers'] ?? '');

                if (!$name) continue;

                // Valider l'email trouvé par Perplexity
                if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $email = '';
                }

                // --- Phase 2 : Scrape YouTube si pas d'email ---
                if (!$email && !$skipScrape && $youtubeUrl) {
                    $this->line("    ↳ Scrape YouTube pour email ({$name})...");
                    $scraped = $ytScraper->scrapeChannel($youtubeUrl);

                    if ($scraped['email']) {
                        $email = $scraped['email'];
                    }
                    if (!$name && $scraped['name']) {
                        $name = $scraped['name'];
                    }
                    if (!$abonnes && $scraped['subscribers']) {
                        $abonnes = $this->parseSubscribers($scraped['subscribers']);
                    }

                    // Pause courte pour ne pas spammer YouTube
                    usleep(800_000);
                }

                // --- Phase 3 : Perplexity ciblé si toujours pas d'email ---
                if (!$email && $name) {
                    $email = $this->findEmailWithPerplexity($perplexity, $name, $pays);
                    if ($email) {
                        usleep(500_000);
                    }
                }

                // Règle stricte : on ne garde que les contacts avec email
                if (!$email) {
                    $this->line("    <fg=gray>✗ {$name} — pas d'email</>");
                    $totalSkipped++;
                    continue;
                }

                if ($isDryRun) {
                    $this->line("    <fg=green>✓ {$name}</> | {$email} | {$pays} | {$langue}");
                    $totalSaved++;
                    continue;
                }

                // --- Sauvegarde en base ---
                $saved = $this->saveChannel([
                    'name'        => $name,
                    'youtube_url' => $youtubeUrl,
                    'email'       => $email,
                    'country'     => $pays,
                    'language'    => $langue,
                    'followers'   => $abonnes,
                    'createur'    => $createur,
                    'admin_id'    => $adminId,
                ]);

                if ($saved) {
                    $this->line("    <fg=green>✓ SAUVÉ</> {$name} | {$email}");
                    $totalSaved++;
                } else {
                    $this->line("    <fg=cyan>~ EXISTANT</> {$name} | {$email}");
                }

                // Pause entre chaque chaîne pour respecter les rate limits
                usleep(300_000);
            }

            $this->newLine();
            // Pause entre pays
            sleep(2);
        }

        $this->newLine();
        $this->info("=== RÉSUMÉ ===");
        $this->line("Chaînes trouvées  : {$totalFound}");
        $this->line("Sauvegardées      : {$totalSaved}");
        $this->line("Sans email (ignorées) : {$totalSkipped}");

        return 0;
    }

    // =========================================================================
    // PERPLEXITY — 3 requêtes de découverte par pays (angles différents)
    // =========================================================================

    private function discoverWithPerplexity(PerplexityService $perplexity, string $pays): array
    {
        $systemPrompt = <<<SYS
Tu es un expert en veille YouTube. Tu réponds UNIQUEMENT en JSON valide, sans texte autour.
Le JSON doit être un tableau d'objets. Ne jamais inventer des données. Si tu n'es pas sûr d'une information, mets null.
SYS;

        $queries = [
            // Angle 1 : expatriés francophones vivant dans le pays
            "Recherche les chaînes YouTube tenues par des francophones (français, belges, suisses, québécois) qui vivent ou ont vécu en {$pays}. "
            . "Contenu sur l'expatriation, le voyage, le quotidien, la culture de {$pays}. "
            . "Pour chaque chaîne : nom_chaine, url_youtube, nom_createur, email (si public), abonnes, langue, sujet. "
            . "Réponse UNIQUEMENT en JSON : [{...}]",

            // Angle 2 : créateurs locaux francophones ou bilingues
            "Recherche les YouTubeurs francophones ou bilingues français basés en {$pays}. "
            . "Inclure les locaux qui font des vidéos en français, les expatriés, les couples mixtes. "
            . "Pour chaque chaîne : nom_chaine, url_youtube, nom_createur, email (cherche sur leur site officiel ou description YouTube), abonnes, langue, sujet. "
            . "Réponse UNIQUEMENT en JSON : [{...}]",

            // Angle 3 : vloggers, travel, lifestyle
            "Quels sont les vloggers, créateurs lifestyle, travel YouTubeurs francophones qui parlent de {$pays} ou y résident ? "
            . "Cherche aussi sur des termes comme 'vivre en {$pays}', 'expat {$pays}', 'français en {$pays}'. "
            . "Pour chaque chaîne : nom_chaine, url_youtube, nom_createur, email (si disponible publiquement), abonnes, langue, sujet. "
            . "Réponse UNIQUEMENT en JSON : [{...}]",
        ];

        $allChannels = [];
        $seenNames   = [];

        foreach ($queries as $i => $query) {
            if ($i > 0) usleep(1_500_000); // pause 1.5s entre requêtes

            $result = $perplexity->searchJson($query, $systemPrompt);

            if (!$result['success']) {
                Log::warning("Perplexity query {$i} failed", ['pays' => $pays, 'error' => $result['error'] ?? '?']);
                continue;
            }

            $data = $result['data'] ?? [];
            if (is_array($data) && !array_is_list($data)) {
                $data = array_values($data)[0] ?? [];
            }
            if (!is_array($data)) continue;

            foreach ($data as $channel) {
                $name = strtolower(trim($channel['nom_chaine'] ?? $channel['name'] ?? ''));
                if (!$name || isset($seenNames[$name])) continue;
                $seenNames[$name] = true;
                $allChannels[]    = $channel;
            }
        }

        return $allChannels;
    }

    // =========================================================================
    // PERPLEXITY — Recherche ciblée d'email pour une chaîne spécifique
    // =========================================================================

    private function findEmailWithPerplexity(PerplexityService $perplexity, string $channelName, string $pays): ?string
    {
        $query = "Quelle est l'adresse email de contact publique de la chaîne YouTube \"{$channelName}\" ({$pays}) ? "
               . "Cherche dans la description YouTube, le site officiel, les réseaux sociaux et les articles de presse. "
               . "Réponds UNIQUEMENT avec l'email si trouvé, ou le mot NULL si non trouvé. Pas d'autre texte.";

        $result = $perplexity->search($query);

        if (!$result['success']) return null;

        $content = trim($result['content']);

        // Vérifier si la réponse contient un email
        if (preg_match('/\b([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})\b/', $content, $m)) {
            $email = strtolower($m[1]);
            return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
        }

        return null;
    }

    // =========================================================================
    // SAUVEGARDE
    // =========================================================================

    private function saveChannel(array $data): bool
    {
        // Vérifier si déjà existant (par email OU par youtube_url)
        $existing = Influenceur::where(function ($q) use ($data) {
            if ($data['email']) {
                $q->where('email', $data['email']);
            }
            if ($data['youtube_url']) {
                $q->orWhere('youtube_url', $data['youtube_url']);
            }
        })->first();

        if ($existing) {
            // Enrichir si manque des données
            $updates = [];
            if (!$existing->email && $data['email'])           $updates['email'] = $data['email'];
            if (!$existing->youtube_url && $data['youtube_url']) $updates['youtube_url'] = $data['youtube_url'];
            if (!$existing->followers && $data['followers'])   $updates['followers'] = $data['followers'];
            if ($updates) {
                $existing->update($updates);
            }
            return false; // Déjà existant
        }

        // Extraire prénom/nom si possible
        $firstName = null;
        $lastName  = null;
        if ($data['createur']) {
            $parts = explode(' ', $data['createur'], 2);
            $firstName = $parts[0] ?? null;
            $lastName  = $parts[1] ?? null;
        }

        Influenceur::create([
            'contact_type'    => 'youtubeur',
            'category'        => 'medias_influence',
            'name'            => $data['name'],
            'first_name'      => $firstName,
            'last_name'       => $lastName,
            'email'           => $data['email'],
            'has_email'       => true,
            'youtube_url'     => $data['youtube_url'] ?: null,
            'profile_url'     => $data['youtube_url'] ?: null,
            'primary_platform'=> 'youtube',
            'platforms'       => ['youtube'],
            'followers'       => $data['followers'],
            'country'         => $data['country'],
            'language'        => substr($this->normalizeLanguage($data['language']), 0, 10),
            'status'          => 'prospect',
            'source'          => 'perplexity_youtube',
            'created_by'      => $data['admin_id'],
            'score'           => $this->computeScore($data),
        ]);

        return true;
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private function normalizeLanguage(?string $lang): string
    {
        if (!$lang) return 'fr';
        $lang = strtolower(trim($lang));
        // Mapper les noms de langues vers codes ISO courts
        $map = [
            'français'  => 'fr', 'french'    => 'fr', 'francais'  => 'fr',
            'anglais'   => 'en', 'english'   => 'en',
            'vietnamien'=> 'vi', 'vietnamese'=> 'vi',
            'chinois'   => 'zh', 'chinese'   => 'zh',
            'japonais'  => 'ja', 'japanese'  => 'ja',
            'thai'      => 'th', 'thaï'      => 'th', 'thaïlandais' => 'th',
            'espagnol'  => 'es', 'spanish'   => 'es',
            'allemand'  => 'de', 'german'    => 'de',
            'portugais' => 'pt', 'portuguese'=> 'pt',
            'arabe'     => 'ar', 'arabic'    => 'ar',
            'coréen'    => 'ko', 'korean'    => 'ko',
            'fr-be'     => 'fr-be', 'fr-ch'  => 'fr-ch',
        ];
        foreach ($map as $key => $code) {
            if (str_contains($lang, $key)) return $code;
        }
        // Si déjà un code court (ex: "fr", "en", "zh-cn"), on le retourne tel quel
        if (strlen($lang) <= 5) return $lang;
        return 'fr'; // fallback
    }

    private function parseSubscribers(string|int|null $raw): ?int
    {
        if ($raw === null || $raw === '') return null;
        if (is_int($raw)) return $raw;

        $raw = strtolower(str_replace([' ', ',', '.'], '', (string) $raw));

        if (str_ends_with($raw, 'k')) {
            return (int) ((float) $raw * 1_000);
        }
        if (str_ends_with($raw, 'm')) {
            return (int) ((float) $raw * 1_000_000);
        }

        return is_numeric($raw) ? (int) $raw : null;
    }

    private function computeScore(array $data): int
    {
        $score = 20; // base : a un email
        if ($data['youtube_url'])        $score += 10;
        if ($data['followers'] >= 10000) $score += 20;
        if ($data['followers'] >= 50000) $score += 20;
        if ($data['followers'] >= 100000) $score += 10;
        return min($score, 100);
    }
}
