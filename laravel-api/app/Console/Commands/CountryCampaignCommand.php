<?php

namespace App\Console\Commands;

use App\Jobs\GenerateArticleJob;
use App\Models\ContentGenerationCampaign;
use App\Models\ContentCampaignItem;
use App\Models\GeneratedArticle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Country Campaign — Generate 50 articles for one country (all content types),
 * then move to the next country.
 *
 * Strategy: Topical Authority SEO 2026. Google ranks sites higher when they have
 * comprehensive coverage of a topic (country). 50 diverse articles per country
 * (articles, Q/R, comparatifs, pain points, guides ville, tutoriels) build a
 * topical cluster that outranks sites with 1 article per country.
 *
 * Usage:
 *   php artisan content:country-campaign TH          # Generate 50 for Thailand
 *   php artisan content:country-campaign TH --dry-run # Preview content plan
 *   php artisan content:country-campaign --auto       # Auto-pick next country
 *   php artisan content:country-campaign --status     # Show campaign progress
 */
class CountryCampaignCommand extends Command
{
    protected $signature = 'content:country-campaign
        {country? : ISO 2-letter country code (e.g. TH, VN, PT)}
        {--dry-run : Preview the content plan without generating}
        {--auto : Auto-pick the next country with fewest articles}
        {--status : Show campaign progress for all countries}
        {--limit=50 : Number of articles to generate per country}
        {--resume : Resume a paused/incomplete campaign}';

    protected $description = 'Generate a complete country content cluster (50 articles, all types)';

    /**
     * Content plan template: 50 articles per country, diversified by type and intent.
     * {country} and {country_name} are replaced at runtime.
     */
    private function getContentPlan(string $countryCode, string $countryName): array
    {
        $year = date('Y');

        return [
            // ── PILLAR CONTENT (3) — Foundation of the cluster ──
            ['type' => 'guide', 'intent' => 'informational', 'topic' => "S'expatrier en {$countryName} : guide complet {$year}"],
            ['type' => 'guide', 'intent' => 'informational', 'topic' => "Cout de la vie en {$countryName} en {$year} : budget detaille"],
            ['type' => 'guide', 'intent' => 'informational', 'topic' => "Visa et permis de sejour en {$countryName} : toutes les options {$year}"],

            // ── ARTICLES JURIDIQUES (8) — High conversion, legal topics ──
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Ouvrir un compte bancaire en {$countryName} en tant qu'expatrie ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Acheter un bien immobilier en {$countryName} : droits des etrangers ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Fiscalite en {$countryName} pour les expatries : ce qu'il faut savoir ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Droit du travail en {$countryName} : droits et obligations des salaries etrangers ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Creer une entreprise en {$countryName} : demarches et pieges a eviter ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Assurance sante en {$countryName} : quelle couverture choisir ? ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Scolariser ses enfants en {$countryName} : ecoles internationales et options ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Permis de conduire en {$countryName} : obtention et conversion ({$year})"],

            // ── ARTICLES PRATIQUES (7) — Daily life topics ──
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Se loger en {$countryName} : quartiers, prix et conseils ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Transports en {$countryName} : se deplacer au quotidien ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Telephonie et internet en {$countryName} : meilleurs operateurs ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Systeme de sante en {$countryName} : hopitaux, medecins et urgences ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Vie sociale en {$countryName} : rencontrer des gens et s'integrer ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Securite en {$countryName} : zones a eviter et precautions ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Climat et meilleure periode pour s'installer en {$countryName} ({$year})"],

            // ── PAIN POINTS / URGENCES (7) — Highest conversion ──
            ['type' => 'article', 'intent' => 'urgency', 'topic' => "Passeport vole en {$countryName} : que faire en urgence ({$year})"],
            ['type' => 'article', 'intent' => 'urgency', 'topic' => "Accident en {$countryName} : vos droits et premiers reflexes ({$year})"],
            ['type' => 'article', 'intent' => 'urgency', 'topic' => "Arrestation en {$countryName} : droits et demarches ({$year})"],
            ['type' => 'article', 'intent' => 'urgency', 'topic' => "Arnaque en {$countryName} : comment reagir et porter plainte ({$year})"],
            ['type' => 'article', 'intent' => 'urgency', 'topic' => "Expulsion de {$countryName} : que faire en urgence ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Divorce en {$countryName} en tant qu'expatrie : procedure et couts ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Garde d'enfant en {$countryName} apres separation : ce que dit la loi ({$year})"],

            // ── COMPARATIFS (5) — Commercial investigation intent ──
            ['type' => 'article', 'intent' => 'commercial_investigation', 'topic' => "Meilleure assurance sante en {$countryName} pour expatries : comparatif {$year}"],
            ['type' => 'article', 'intent' => 'commercial_investigation', 'topic' => "Meilleure banque en {$countryName} pour expatries : comparatif {$year}"],
            ['type' => 'article', 'intent' => 'commercial_investigation', 'topic' => "Meilleur forfait telephone en {$countryName} : comparatif operateurs {$year}"],
            ['type' => 'article', 'intent' => 'commercial_investigation', 'topic' => "Transfert d'argent vers {$countryName} : Wise vs Revolut vs banque ({$year})"],
            ['type' => 'article', 'intent' => 'commercial_investigation', 'topic' => "VPN en {$countryName} : quel service choisir ? ({$year})"],

            // ── Q/R — QUESTIONS GOOGLE (10) — Featured snippets ──
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Faut-il un visa pour aller en {$countryName} ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Combien coute la vie en {$countryName} par mois ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Peut-on travailler en {$countryName} avec un visa touristique ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Comment obtenir un permis de travail en {$countryName} ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Quel budget pour s'installer en {$countryName} ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Est-ce dangereux de vivre en {$countryName} ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Comment trouver un logement en {$countryName} depuis l'etranger ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Quels vaccins pour aller en {$countryName} ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Comment envoyer de l'argent en {$countryName} pas cher ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Peut-on acheter un bien immobilier en {$countryName} en tant qu'etranger ? ({$year})"],

            // ── TUTORIELS (5) — Step-by-step, how-to schema ──
            ['type' => 'article', 'intent' => 'transactional', 'topic' => "Comment obtenir un visa pour {$countryName} etape par etape ({$year})"],
            ['type' => 'article', 'intent' => 'transactional', 'topic' => "Demenager en {$countryName} : checklist complete ({$year})"],
            ['type' => 'article', 'intent' => 'transactional', 'topic' => "Ouvrir un compte bancaire en {$countryName} en ligne : tutoriel ({$year})"],
            ['type' => 'article', 'intent' => 'transactional', 'topic' => "S'inscrire au consulat en {$countryName} : demarche pas a pas ({$year})"],
            ['type' => 'article', 'intent' => 'transactional', 'topic' => "Trouver un avocat en {$countryName} : ou chercher et combien ca coute ({$year})"],

            // ── DIGITAL NOMAD / LIFESTYLE (5) — Growing segment ──
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Digital nomad en {$countryName} : visa, coworking et cout de vie ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Retraite en {$countryName} : visa, fiscalite et qualite de vie ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Etudier en {$countryName} : universites, bourses et vie etudiante ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Animaux de compagnie en {$countryName} : import, veterinaires et regles ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Communaute expatriee en {$countryName} : groupes, associations et reseaux ({$year})"],
        ];
    }

    /**
     * Country priority order for auto mode.
     */
    private const COUNTRY_ORDER = [
        // Tier 1: Highest search volume for expat content
        'TH' => 'Thailande',
        'VN' => 'Vietnam',
        'PT' => 'Portugal',
        'ES' => 'Espagne',
        'ID' => 'Indonesie',
        'MX' => 'Mexique',
        'MA' => 'Maroc',
        'AE' => 'Emirats arabes unis',
        'SG' => 'Singapour',
        'JP' => 'Japon',
        // Tier 2
        'DE' => 'Allemagne',
        'GB' => 'Royaume-Uni',
        'US' => 'Etats-Unis',
        'CA' => 'Canada',
        'AU' => 'Australie',
        'BR' => 'Bresil',
        'CO' => 'Colombie',
        'CR' => 'Costa Rica',
        'GR' => 'Grece',
        'HR' => 'Croatie',
        // Tier 3
        'IT' => 'Italie',
        'NL' => 'Pays-Bas',
        'BE' => 'Belgique',
        'CH' => 'Suisse',
        'TR' => 'Turquie',
        'PH' => 'Philippines',
        'MY' => 'Malaisie',
        'KH' => 'Cambodge',
        'IN' => 'Inde',
        'PL' => 'Pologne',
    ];

    public function handle(): int
    {
        // --status mode
        if ($this->option('status')) {
            return $this->showStatus();
        }

        // Determine country
        $countryCode = $this->argument('country');
        if ($this->option('auto')) {
            $countryCode = $this->autoPickCountry();
            if (!$countryCode) {
                $this->info('All countries have 50+ articles. Campaign complete!');
                return 0;
            }
        }

        if (!$countryCode) {
            $this->error('Specify a country code (e.g. TH) or use --auto');
            return 1;
        }

        $countryCode = strtoupper($countryCode);
        $countryName = self::COUNTRY_ORDER[$countryCode] ?? $countryCode;
        $limit = (int) $this->option('limit');
        $isDryRun = $this->option('dry-run');

        $this->info("=== Country Campaign: {$countryName} ({$countryCode}) ===");

        // Get content plan
        $plan = $this->getContentPlan($countryCode, $countryName);

        // Check what already exists for this country
        $existingTitles = GeneratedArticle::where('country', $countryCode)
            ->where('language', 'fr')
            ->whereIn('status', ['generating', 'review', 'published', 'approved'])
            ->pluck('title')
            ->map(fn ($t) => mb_strtolower($t))
            ->toArray();

        $existingCount = count($existingTitles);
        $this->info("Existing articles for {$countryCode}: {$existingCount}");

        // Filter out already-generated topics (fuzzy match on first 30 chars)
        $toGenerate = [];
        foreach ($plan as $item) {
            $topicLower = mb_strtolower($item['topic']);
            $isDuplicate = false;
            foreach ($existingTitles as $existing) {
                // Fuzzy: if first 30 chars of topic match an existing title
                if (mb_substr($topicLower, 0, 30) === mb_substr($existing, 0, 30)) {
                    $isDuplicate = true;
                    break;
                }
            }
            if (!$isDuplicate) {
                $toGenerate[] = $item;
            }
        }

        $toGenerate = array_slice($toGenerate, 0, $limit);
        $this->info("Articles to generate: " . count($toGenerate) . " (limit: {$limit})");
        $this->newLine();

        if (empty($toGenerate)) {
            $this->info("Nothing to generate — {$countryName} already has all planned articles.");
            return 0;
        }

        // Display plan
        $typeStats = [];
        foreach ($toGenerate as $i => $item) {
            $num = $i + 1;
            $typeStats[$item['type']] = ($typeStats[$item['type']] ?? 0) + 1;
            $intentLabel = match ($item['intent']) {
                'urgency' => 'URG',
                'commercial_investigation' => 'COM',
                'transactional' => 'TXN',
                default => 'INF',
            };
            $this->line("  {$num}. [{$item['type']}][{$intentLabel}] {$item['topic']}");
        }

        $this->newLine();
        $this->info('Content mix: ' . collect($typeStats)->map(fn ($c, $t) => "{$t}: {$c}")->implode(', '));

        if ($isDryRun) {
            $this->warn('Dry run — nothing queued.');
            return 0;
        }

        if (!$this->option('resume') && !$this->confirm('Queue ' . count($toGenerate) . " articles for {$countryName}?")) {
            return 0;
        }

        // Create campaign record
        $campaign = ContentGenerationCampaign::create([
            'name' => "Country Campaign: {$countryName} ({$countryCode})",
            'description' => "50 articles all types for {$countryName}",
            'campaign_type' => 'country',
            'config' => [
                'country_code' => $countryCode,
                'country_name' => $countryName,
                'total_planned' => count($toGenerate),
            ],
            'status' => 'running',
            'total_items' => count($toGenerate),
            'started_at' => now(),
        ]);

        // Dispatch jobs with staggered delays (45s between each to avoid rate limits)
        foreach ($toGenerate as $i => $item) {
            $keywords = $this->extractKeywords($item['topic'], $countryName);

            GenerateArticleJob::dispatch([
                'topic'          => $item['topic'],
                'content_type'   => $item['type'] === 'qa' ? 'qa' : 'article',
                'language'       => 'fr',
                'country'        => $countryCode,
                'keywords'       => $keywords,
                'search_intent'  => $item['intent'],
                'force_generate' => true,
                'image_source'   => 'unsplash',
                'campaign_id'    => $campaign->id,
            ])->delay(now()->addSeconds($i * 45));

            // Create campaign item for tracking
            ContentCampaignItem::create([
                'campaign_id'   => $campaign->id,
                'title_hint'    => $item['topic'],
                'config_override' => [
                    'content_type'  => $item['type'],
                    'search_intent' => $item['intent'],
                ],
                'status'     => 'pending',
                'sort_order' => $i,
            ]);
        }

        $totalMinutes = (int) ceil(count($toGenerate) * 45 / 60);
        $this->newLine();
        $this->info(count($toGenerate) . " articles queued for {$countryName}.");
        $this->info("Estimated completion: ~{$totalMinutes} minutes (staggered at 45s intervals).");
        $this->info("Campaign ID: {$campaign->id}");
        $this->info("Monitor: php artisan content:country-campaign --status");

        return 0;
    }

    /**
     * Auto-pick the country with fewest articles (below 50).
     */
    private function autoPickCountry(): ?string
    {
        $counts = GeneratedArticle::where('language', 'fr')
            ->whereIn('status', ['review', 'published', 'approved'])
            ->whereNotNull('country')
            ->groupBy('country')
            ->selectRaw('country, COUNT(*) as total')
            ->pluck('total', 'country')
            ->toArray();

        foreach (self::COUNTRY_ORDER as $code => $name) {
            $existing = $counts[$code] ?? 0;
            if ($existing < 50) {
                $this->info("Auto-selected: {$name} ({$code}) — {$existing}/50 articles");
                return $code;
            }
        }

        return null;
    }

    /**
     * Show campaign progress for all countries.
     */
    private function showStatus(): int
    {
        $counts = GeneratedArticle::where('language', 'fr')
            ->whereIn('status', ['review', 'published', 'approved', 'generating'])
            ->whereNotNull('country')
            ->groupBy('country')
            ->selectRaw('country, COUNT(*) as total')
            ->pluck('total', 'country')
            ->toArray();

        $rows = [];
        foreach (self::COUNTRY_ORDER as $code => $name) {
            $count = $counts[$code] ?? 0;
            $bar = str_repeat('█', (int) ($count / 50 * 20)) . str_repeat('░', 20 - (int) ($count / 50 * 20));
            $status = $count >= 50 ? 'DONE' : ($count > 0 ? 'IN PROGRESS' : 'PENDING');
            $rows[] = [$code, $name, $count, $bar, $status];
        }

        $this->table(['Code', 'Country', 'Articles', 'Progress (50)', 'Status'], $rows);

        $totalArticles = array_sum($counts);
        $this->info("Total: {$totalArticles} articles across " . count($counts) . " countries");

        return 0;
    }

    /**
     * Extract keywords from a topic string.
     */
    private function extractKeywords(string $topic, string $countryName): array
    {
        // Remove year, country name, and common words to get keywords
        $clean = preg_replace('/\(\d{4}\)|\d{4}/', '', $topic);
        $clean = str_ireplace([$countryName, 'en tant qu\'expatrie', 'en tant qu\'etranger', 'pour les expatries', 'pour expatries'], '', $clean);
        $clean = preg_replace('/\s*:\s*/', ' ', $clean);
        $clean = preg_replace('/\s+/', ' ', trim($clean));

        // Primary keyword = first meaningful phrase
        $primary = mb_strtolower(trim($clean));

        // Add country name back for long-tail
        $withCountry = mb_strtolower($countryName) . ' ' . $primary;

        return array_filter([$primary, $withCountry, mb_strtolower($countryName) . ' expatrie']);
    }
}
