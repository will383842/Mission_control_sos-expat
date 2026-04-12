<?php

namespace App\Console\Commands;

use App\Jobs\GenerateArticleJob;
use App\Models\ContentGenerationCampaign;
use App\Models\ContentCampaignItem;
use App\Models\GeneratedArticle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Country Campaign — Generate 100 articles for one country (all content types),
 * then move to the next country.
 *
 * Strategy: Topical Authority SEO 2026. Google ranks sites higher when they have
 * comprehensive coverage of a topic (country). 100 diverse articles per country
 * (guides, articles juridiques, pratiques, pain points, comparatifs, Q/R,
 * tutoriels, lifestyle, statistiques, outreach, temoignages) build a topical
 * cluster that outranks sites with fewer articles per country.
 *
 * Usage:
 *   php artisan content:country-campaign TH          # Generate for Thailand
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
        {--limit=0 : Override articles limit (0 = use DB config)}
        {--resume : Resume a paused/incomplete campaign}';

    protected $description = 'Generate a complete country content cluster (100 articles, all types)';

    /**
     * Top 3 expat cities per country for city guides.
     */
    private const TOP_CITIES = [
        'TH' => ['Bangkok', 'Chiang Mai', 'Phuket'],
        'US' => ['New York', 'Miami', 'Los Angeles'],
        'VN' => ['Ho Chi Minh-Ville', 'Hanoi', 'Da Nang'],
        'SG' => ['Singapour Centre', 'Orchard', 'Marina Bay'],
        'PT' => ['Lisbonne', 'Porto', 'Algarve'],
        'ES' => ['Barcelone', 'Madrid', 'Valence'],
        'ID' => ['Bali', 'Jakarta', 'Yogyakarta'],
        'MX' => ['Mexico', 'Playa del Carmen', 'Guadalajara'],
        'MA' => ['Casablanca', 'Marrakech', 'Rabat'],
        'AE' => ['Dubai', 'Abu Dhabi', 'Sharjah'],
        'JP' => ['Tokyo', 'Osaka', 'Kyoto'],
        'DE' => ['Berlin', 'Munich', 'Francfort'],
        'GB' => ['Londres', 'Manchester', 'Edimbourg'],
        'CA' => ['Montreal', 'Toronto', 'Vancouver'],
        'AU' => ['Sydney', 'Melbourne', 'Brisbane'],
        'BR' => ['Sao Paulo', 'Rio de Janeiro', 'Florianopolis'],
        'CO' => ['Medellin', 'Bogota', 'Cartagena'],
        'CR' => ['San Jose', 'Tamarindo', 'Puerto Viejo'],
        'GR' => ['Athenes', 'Thessalonique', 'Crete'],
        'HR' => ['Zagreb', 'Split', 'Dubrovnik'],
        'IT' => ['Rome', 'Milan', 'Florence'],
        'NL' => ['Amsterdam', 'Rotterdam', 'La Haye'],
        'BE' => ['Bruxelles', 'Anvers', 'Gand'],
        'CH' => ['Geneve', 'Zurich', 'Lausanne'],
        'TR' => ['Istanbul', 'Antalya', 'Izmir'],
        'PH' => ['Manille', 'Cebu', 'Davao'],
        'MY' => ['Kuala Lumpur', 'Penang', 'Johor Bahru'],
        'KH' => ['Phnom Penh', 'Siem Reap', 'Sihanoukville'],
        'IN' => ['Mumbai', 'Bangalore', 'Goa'],
        'PL' => ['Varsovie', 'Cracovie', 'Wroclaw'],
    ];

    /**
     * Content plan template: 100 articles per country, diversified by type and intent.
     * {country} and {country_name} are replaced at runtime.
     */
    private function getContentPlan(string $countryCode, string $countryName): array
    {
        $year = date('Y');
        $cities = self::TOP_CITIES[$countryCode] ?? ['la capitale', 'la deuxieme ville', 'la troisieme ville'];

        return [
            // ── PILLAR CONTENT / GUIDES (5) — Foundation of the cluster ──
            ['type' => 'guide', 'intent' => 'informational', 'topic' => "S'expatrier en {$countryName} : guide complet {$year}"],
            ['type' => 'guide', 'intent' => 'informational', 'topic' => "Cout de la vie en {$countryName} en {$year} : budget detaille"],
            ['type' => 'guide', 'intent' => 'informational', 'topic' => "Visa et permis de sejour en {$countryName} : toutes les options {$year}"],
            ['type' => 'guide', 'intent' => 'informational', 'topic' => "Systeme de sante en {$countryName} : guide complet pour expatries ({$year})"],
            ['type' => 'guide', 'intent' => 'informational', 'topic' => "Travailler en {$countryName} : marche de l'emploi et opportunites ({$year})"],

            // ── GUIDES VILLE (3) — Top 3 cities per country ──
            ['type' => 'guide_city', 'intent' => 'informational', 'topic' => "Vivre a {$cities[0]} en tant qu'expatrie : guide complet ({$year})"],
            ['type' => 'guide_city', 'intent' => 'informational', 'topic' => "Vivre a {$cities[1]} en tant qu'expatrie : guide complet ({$year})"],
            ['type' => 'guide_city', 'intent' => 'informational', 'topic' => "Vivre a {$cities[2]} en tant qu'expatrie : guide complet ({$year})"],

            // ── ARTICLES JURIDIQUES (12) — High conversion, legal topics ──
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Droit du travail en {$countryName} : droits et obligations des salaries etrangers ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Fiscalite en {$countryName} pour les expatries : ce qu'il faut savoir ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Acheter un bien immobilier en {$countryName} : droits des etrangers ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Divorce en {$countryName} en tant qu'expatrie : procedure et couts ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Garde d'enfant en {$countryName} apres separation : ce que dit la loi ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Heritage et succession en {$countryName} pour les etrangers ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Creer une entreprise en {$countryName} : demarches et pieges a eviter ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Permis de conduire en {$countryName} : obtention et conversion ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Assurance sante en {$countryName} : quelle couverture choisir ? ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Scolariser ses enfants en {$countryName} : ecoles internationales et options ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Retraite en {$countryName} : droits, fiscalite et demarches ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Ouvrir un compte bancaire en {$countryName} en tant qu'expatrie ({$year})"],

            // ── ARTICLES PRATIQUES (10) — Daily life topics ──
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Se loger en {$countryName} : quartiers, prix et conseils ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Transports en {$countryName} : se deplacer au quotidien ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Telephonie et internet en {$countryName} : meilleurs operateurs ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Systeme de sante en {$countryName} : hopitaux, medecins et urgences ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Securite en {$countryName} : zones a eviter et precautions ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Climat et meilleure periode pour s'installer en {$countryName} ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Vie sociale en {$countryName} : rencontrer des gens et s'integrer ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Animaux de compagnie en {$countryName} : import, veterinaires et regles ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Communaute expatriee en {$countryName} : groupes, associations et reseaux ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Culture et coutumes en {$countryName} : ce qu'il faut savoir avant de partir ({$year})"],

            // ── PAIN POINTS / URGENCES (10) — Highest conversion ──
            ['type' => 'pain_point', 'intent' => 'urgency', 'topic' => "Passeport vole en {$countryName} : que faire en urgence ({$year})"],
            ['type' => 'pain_point', 'intent' => 'urgency', 'topic' => "Accident en {$countryName} : vos droits et premiers reflexes ({$year})"],
            ['type' => 'pain_point', 'intent' => 'urgency', 'topic' => "Arrestation en {$countryName} : droits et demarches ({$year})"],
            ['type' => 'pain_point', 'intent' => 'urgency', 'topic' => "Arnaque en {$countryName} : comment reagir et porter plainte ({$year})"],
            ['type' => 'pain_point', 'intent' => 'urgency', 'topic' => "Expulsion de {$countryName} : que faire en urgence ({$year})"],
            ['type' => 'pain_point', 'intent' => 'urgency', 'topic' => "Agression en {$countryName} : que faire et qui contacter ({$year})"],
            ['type' => 'pain_point', 'intent' => 'urgency', 'topic' => "Hospitalisation en {$countryName} : couts, assurance et demarches ({$year})"],
            ['type' => 'pain_point', 'intent' => 'urgency', 'topic' => "Perte de bagages en {$countryName} : recours et indemnisation ({$year})"],
            ['type' => 'pain_point', 'intent' => 'urgency', 'topic' => "Catastrophe naturelle en {$countryName} : que faire en cas d'urgence ({$year})"],
            ['type' => 'pain_point', 'intent' => 'urgency', 'topic' => "Litige avec un proprietaire en {$countryName} : vos recours ({$year})"],

            // ── COMPARATIFS (8) — Commercial investigation intent ──
            ['type' => 'comparative', 'intent' => 'commercial_investigation', 'topic' => "Meilleure assurance sante en {$countryName} pour expatries : comparatif {$year}"],
            ['type' => 'comparative', 'intent' => 'commercial_investigation', 'topic' => "Meilleure banque en {$countryName} pour expatries : comparatif {$year}"],
            ['type' => 'comparative', 'intent' => 'commercial_investigation', 'topic' => "Meilleur forfait telephone en {$countryName} : comparatif operateurs {$year}"],
            ['type' => 'comparative', 'intent' => 'commercial_investigation', 'topic' => "Transfert d'argent vers {$countryName} : Wise vs Revolut vs banque ({$year})"],
            ['type' => 'comparative', 'intent' => 'commercial_investigation', 'topic' => "VPN en {$countryName} : quel service choisir ? ({$year})"],
            ['type' => 'comparative', 'intent' => 'commercial_investigation', 'topic' => "Meilleur espace coworking en {$countryName} : comparatif {$year}"],
            ['type' => 'comparative', 'intent' => 'commercial_investigation', 'topic' => "Demenageurs internationaux vers {$countryName} : comparatif prix et avis ({$year})"],
            ['type' => 'comparative', 'intent' => 'commercial_investigation', 'topic' => "Ecoles internationales en {$countryName} : comparatif et classement ({$year})"],

            // ── Q/R — QUESTIONS GOOGLE (20) — Featured snippets ──
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
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Quel est le salaire moyen en {$countryName} ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Comment ouvrir un compte bancaire en {$countryName} sans residences ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Faut-il un permis de conduire international en {$countryName} ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Comment se faire soigner en {$countryName} en tant qu'etranger ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Quelle est la meilleure ville pour vivre en {$countryName} ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Comment scolariser ses enfants en {$countryName} ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Combien de temps peut-on rester en {$countryName} sans visa ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Faut-il parler la langue locale pour vivre en {$countryName} ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Comment trouver du travail en {$countryName} en tant qu'etranger ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Quels sont les impots a payer en {$countryName} pour un expatrie ? ({$year})"],

            // ── TUTORIELS (8) — Step-by-step, how-to schema ──
            ['type' => 'article', 'intent' => 'transactional', 'topic' => "Comment obtenir un visa pour {$countryName} etape par etape ({$year})"],
            ['type' => 'article', 'intent' => 'transactional', 'topic' => "Demenager en {$countryName} : checklist complete ({$year})"],
            ['type' => 'article', 'intent' => 'transactional', 'topic' => "Ouvrir un compte bancaire en {$countryName} en ligne : tutoriel ({$year})"],
            ['type' => 'article', 'intent' => 'transactional', 'topic' => "S'inscrire au consulat en {$countryName} : demarche pas a pas ({$year})"],
            ['type' => 'article', 'intent' => 'transactional', 'topic' => "Trouver un avocat en {$countryName} : ou chercher et combien ca coute ({$year})"],
            ['type' => 'article', 'intent' => 'transactional', 'topic' => "Passer le permis de conduire en {$countryName} : tutoriel complet ({$year})"],
            ['type' => 'article', 'intent' => 'transactional', 'topic' => "Souscrire une assurance sante en {$countryName} : guide pas a pas ({$year})"],
            ['type' => 'article', 'intent' => 'transactional', 'topic' => "Declaration d'impots en {$countryName} : guide pour expatries ({$year})"],

            // ── DIGITAL NOMAD / LIFESTYLE (8) — Growing segment ──
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Digital nomad en {$countryName} : visa, coworking et cout de vie ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Retraite en {$countryName} : visa, fiscalite et qualite de vie ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Etudier en {$countryName} : universites, bourses et vie etudiante ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Vie nocturne en {$countryName} : bars, clubs et sorties ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Gastronomie en {$countryName} : plats typiques et ou manger ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Sport et activites en plein air en {$countryName} ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Benevolat en {$countryName} : associations et missions pour expatries ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Espaces de coworking en {$countryName} : les meilleurs spots ({$year})"],

            // ── STATISTIQUES (6) — Data-driven authority ──
            ['type' => 'statistiques', 'intent' => 'informational', 'topic' => "Population expatriee en {$countryName} : chiffres et tendances ({$year})"],
            ['type' => 'statistiques', 'intent' => 'informational', 'topic' => "Cout de la vie en {$countryName} en chiffres : loyer, courses, transport ({$year})"],
            ['type' => 'statistiques', 'intent' => 'informational', 'topic' => "Salaires moyens en {$countryName} par secteur ({$year})"],
            ['type' => 'statistiques', 'intent' => 'informational', 'topic' => "Prix de l'immobilier en {$countryName} : achat et location ({$year})"],
            ['type' => 'statistiques', 'intent' => 'informational', 'topic' => "Criminalite et securite en {$countryName} : statistiques ({$year})"],
            ['type' => 'statistiques', 'intent' => 'informational', 'topic' => "Qualite de vie en {$countryName} : classement et indicateurs ({$year})"],

            // ── OUTREACH (5) — Affiliate recruitment ──
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Devenir chatter SOS-Expat en {$countryName} : aider les expatries et gagner de l'argent ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Influenceur expatriation en {$countryName} : rejoindre le programme SOS-Expat ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Admin de groupe expat en {$countryName} : monetiser votre communaute ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Avocat en {$countryName} : devenir partenaire SOS-Expat ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Expat en {$countryName} : aidez d'autres expatries et gagnez des commissions ({$year})"],

            // ── TEMOIGNAGES (5) — Social proof ──
            ['type' => 'testimonial', 'intent' => 'informational', 'topic' => "Temoignage : mon expatriation en {$countryName}, les debuts ({$year})"],
            ['type' => 'testimonial', 'intent' => 'informational', 'topic' => "Temoignage : travailler en {$countryName} en tant qu'etranger ({$year})"],
            ['type' => 'testimonial', 'intent' => 'informational', 'topic' => "Temoignage : s'installer en famille en {$countryName} ({$year})"],
            ['type' => 'testimonial', 'intent' => 'informational', 'topic' => "Temoignage : retraite en {$countryName}, le bilan apres un an ({$year})"],
            ['type' => 'testimonial', 'intent' => 'informational', 'topic' => "Temoignage : digital nomad en {$countryName}, avantages et difficultes ({$year})"],
        ];
        // Total: 5 + 3 + 12 + 10 + 10 + 8 + 20 + 8 + 8 + 6 + 5 + 5 = 100
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

    /**
     * Get campaign threshold from DB config.
     */
    private function getThreshold(): int
    {
        $config = DB::table('content_orchestrator_config')->first();
        return (int) ($config->campaign_articles_per_country ?? 100);
    }

    /**
     * Get campaign country order from DB, fallback to COUNTRY_ORDER constant.
     */
    private function getCountryOrder(): array
    {
        $config = DB::table('content_orchestrator_config')->first();
        $queue = json_decode($config->campaign_country_queue ?? '[]', true);

        if (!empty($queue)) {
            // Convert flat array to code => name map
            $ordered = [];
            foreach ($queue as $code) {
                $ordered[$code] = self::COUNTRY_ORDER[$code] ?? $code;
            }
            return $ordered;
        }

        return self::COUNTRY_ORDER;
    }

    public function handle(): int
    {
        // --status mode
        if ($this->option('status')) {
            return $this->showStatus();
        }

        $threshold = $this->getThreshold();

        // Determine country
        $countryCode = $this->argument('country');
        if ($this->option('auto')) {
            $countryCode = $this->autoPickCountry();
            if (!$countryCode) {
                $this->info("All countries have {$threshold}+ articles. Campaign complete!");
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
        if ($limit <= 0) {
            $limit = $threshold;
        }
        $isDryRun = $this->option('dry-run');

        $this->info("=== Country Campaign: {$countryName} ({$countryCode}) — target: {$threshold} articles ===");

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
            'description' => "{$threshold} articles all types for {$countryName}",
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
     * Auto-pick the next country below threshold (reads order from DB).
     */
    private function autoPickCountry(): ?string
    {
        $threshold = $this->getThreshold();
        $countryOrder = $this->getCountryOrder();

        $counts = GeneratedArticle::where('language', 'fr')
            ->whereIn('status', ['review', 'published', 'approved'])
            ->whereNotNull('country')
            ->where('word_count', '>', 0)
            ->groupBy('country')
            ->selectRaw('country, COUNT(*) as total')
            ->pluck('total', 'country')
            ->toArray();

        foreach ($countryOrder as $code => $name) {
            $existing = $counts[$code] ?? 0;
            if ($existing < $threshold) {
                $this->info("Auto-selected: {$name} ({$code}) — {$existing}/{$threshold} articles");
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
        $threshold = $this->getThreshold();
        $countryOrder = $this->getCountryOrder();

        $counts = GeneratedArticle::where('language', 'fr')
            ->whereIn('status', ['review', 'published', 'approved'])
            ->whereNotNull('country')
            ->where('word_count', '>', 0)
            ->groupBy('country')
            ->selectRaw('country, COUNT(*) as total')
            ->pluck('total', 'country')
            ->toArray();

        $rows = [];
        foreach ($countryOrder as $code => $name) {
            $count = $counts[$code] ?? 0;
            $pct = min(1, $count / max(1, $threshold));
            $bar = str_repeat("\u{2588}", (int) ($pct * 20)) . str_repeat("\u{2591}", 20 - (int) ($pct * 20));
            $status = $count >= $threshold ? 'DONE' : ($count > 0 ? 'IN PROGRESS' : 'PENDING');
            $rows[] = [$code, $name, "{$count}/{$threshold}", $bar, $status];
        }

        $this->table(['Code', 'Country', 'Articles', "Progress ({$threshold})", 'Status'], $rows);

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
