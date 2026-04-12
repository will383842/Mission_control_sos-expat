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
        // French prepositions: "en Thailande", "au Japon", "aux Etats-Unis"
        $en = (self::COUNTRY_PREP[$countryCode] ?? 'en') . ' ' . $countryName; // "en Thailande"
        $de = (self::COUNTRY_DE_PREP[$countryCode] ?? 'de') . ' ' . $countryName; // "de Thailande"
        // Fix: "a Singapour" → "à Singapour"
        if (str_starts_with($en, 'a ')) {
            $en = 'à ' . mb_substr($en, 2);
        }

        return [
            // ── FICHE PAYS (1) — Pure country factsheet, data-driven ──
            ['type' => 'statistics', 'intent' => 'informational', 'topic' => "{$countryName} : superficie, population, langues, monnaie, economie et chiffres cles ({$year})"],

            // ── PILLAR CONTENT / GUIDES (5) — Foundation of the cluster ──
            ['type' => 'guide', 'intent' => 'informational', 'topic' => "S'expatrier {$en} : guide complet {$year}"],
            ['type' => 'guide', 'intent' => 'informational', 'topic' => "Cout de la vie {$en} en {$year} : budget detaille"],
            ['type' => 'guide', 'intent' => 'informational', 'topic' => "Visa et permis de sejour {$en} : toutes les options {$year}"],
            ['type' => 'guide', 'intent' => 'informational', 'topic' => "Systeme de sante {$en} : guide complet pour expatries ({$year})"],
            ['type' => 'guide', 'intent' => 'informational', 'topic' => "Travailler {$en} : marche de l'emploi et opportunites ({$year})"],

            // ── GUIDES VILLE (3) — Top 3 cities per country ──
            ['type' => 'guide_city', 'intent' => 'informational', 'topic' => "Vivre a {$cities[0]} en tant qu'expatrie : guide complet ({$year})"],
            ['type' => 'guide_city', 'intent' => 'informational', 'topic' => "Vivre a {$cities[1]} en tant qu'expatrie : guide complet ({$year})"],
            ['type' => 'guide_city', 'intent' => 'informational', 'topic' => "Vivre a {$cities[2]} en tant qu'expatrie : guide complet ({$year})"],

            // ── ARTICLES JURIDIQUES (12) — High conversion, legal topics ──
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Droit du travail {$en} : droits et obligations des salaries etrangers ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Fiscalite {$en} pour les expatries : ce qu'il faut savoir ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Acheter un bien immobilier {$en} : droits des etrangers ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Divorce {$en} en tant qu'expatrie : procedure et couts ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Garde d'enfant {$en} apres separation : ce que dit la loi ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Heritage et succession {$en} pour les etrangers ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Creer une entreprise {$en} : demarches et pieges a eviter ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Permis de conduire {$en} : obtention et conversion ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Assurance sante {$en} : quelle couverture choisir ? ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Scolariser ses enfants {$en} : ecoles internationales et options ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Contrat de travail {$en} : droits et clauses pour expatries ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Ouvrir un compte bancaire {$en} en tant qu'expatrie ({$year})"],

            // ── ARTICLES PRATIQUES (10) — Daily life topics ──
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Se loger {$en} : quartiers, prix et conseils ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Transports {$en} : se deplacer au quotidien ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Telephonie et internet {$en} : meilleurs operateurs ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Hopitaux et medecins {$en} : guide pratique urgences ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Securite {$en} : zones a eviter et precautions ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Climat et meilleure periode pour s'installer {$en} ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Vie sociale {$en} : rencontrer des gens et s'integrer ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Animaux de compagnie {$en} : import, veterinaires et regles ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Communaute expatriee {$en} : groupes, associations et reseaux ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Culture et coutumes {$en} : ce qu'il faut savoir avant de partir ({$year})"],

            // ── PAIN POINTS / URGENCES (10) — Highest conversion ──
            ['type' => 'pain_point', 'intent' => 'urgency', 'topic' => "Passeport vole {$en} : que faire en urgence ({$year})"],
            ['type' => 'pain_point', 'intent' => 'urgency', 'topic' => "Accident {$en} : vos droits et premiers reflexes ({$year})"],
            ['type' => 'pain_point', 'intent' => 'urgency', 'topic' => "Arrestation {$en} : droits et demarches ({$year})"],
            ['type' => 'pain_point', 'intent' => 'urgency', 'topic' => "Arnaque {$en} : comment reagir et porter plainte ({$year})"],
            ['type' => 'pain_point', 'intent' => 'urgency', 'topic' => "Expulsion {$de} : que faire en urgence ({$year})"],
            ['type' => 'pain_point', 'intent' => 'urgency', 'topic' => "Agression {$en} : que faire et qui contacter ({$year})"],
            ['type' => 'pain_point', 'intent' => 'urgency', 'topic' => "Hospitalisation {$en} : couts, assurance et demarches ({$year})"],
            ['type' => 'pain_point', 'intent' => 'urgency', 'topic' => "Perte de bagages {$en} : recours et indemnisation ({$year})"],
            ['type' => 'pain_point', 'intent' => 'urgency', 'topic' => "Catastrophe naturelle {$en} : que faire en cas d'urgence ({$year})"],
            ['type' => 'pain_point', 'intent' => 'urgency', 'topic' => "Litige avec un proprietaire {$en} : vos recours ({$year})"],

            // ── COMPARATIFS (8) — Commercial investigation intent ──
            ['type' => 'comparative', 'intent' => 'commercial_investigation', 'topic' => "Meilleure assurance sante {$en} pour expatries : comparatif {$year}"],
            ['type' => 'comparative', 'intent' => 'commercial_investigation', 'topic' => "Meilleure banque {$en} pour expatries : comparatif {$year}"],
            ['type' => 'comparative', 'intent' => 'commercial_investigation', 'topic' => "Meilleur forfait telephone {$en} : comparatif operateurs {$year}"],
            ['type' => 'comparative', 'intent' => 'commercial_investigation', 'topic' => "Transfert d'argent vers {$countryName} : Wise vs Revolut vs banque ({$year})"],
            ['type' => 'comparative', 'intent' => 'commercial_investigation', 'topic' => "VPN {$en} : quel service choisir ? ({$year})"],
            ['type' => 'comparative', 'intent' => 'commercial_investigation', 'topic' => "Meilleur espace coworking {$en} : comparatif {$year}"],
            ['type' => 'comparative', 'intent' => 'commercial_investigation', 'topic' => "Demenageurs internationaux vers {$countryName} : comparatif prix et avis ({$year})"],
            ['type' => 'comparative', 'intent' => 'commercial_investigation', 'topic' => "Ecoles internationales {$en} : comparatif et classement ({$year})"],

            // ── Q/R — QUESTIONS GOOGLE (20) — Featured snippets ──
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Faut-il un visa pour aller {$en} ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Combien coute la vie {$en} par mois ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Peut-on travailler {$en} avec un visa touristique ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Comment obtenir un permis de travail {$en} ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Quel budget pour s'installer {$en} ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Est-ce dangereux de vivre {$en} ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Comment trouver un logement {$en} depuis l'etranger ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Quels vaccins pour aller {$en} ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Comment envoyer de l'argent {$en} pas cher ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Peut-on acheter un bien immobilier {$en} en tant qu'etranger ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Quel est le salaire moyen {$en} ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Comment ouvrir un compte bancaire {$en} sans residences ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Faut-il un permis de conduire international {$en} ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Comment se faire soigner {$en} en tant qu'etranger ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Quelle est la meilleure ville pour vivre {$en} ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Comment scolariser ses enfants {$en} ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Combien de temps peut-on rester {$en} sans visa ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Comment trouver du travail {$en} en tant qu'etranger ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Quels sont les impots a payer {$en} pour un expatrie ? ({$year})"],

            // ── TUTORIELS (8) — Step-by-step, how-to schema ──
            ['type' => 'tutorial', 'intent' => 'transactional', 'topic' => "Comment obtenir un visa pour {$countryName} etape par etape ({$year})"],
            ['type' => 'tutorial', 'intent' => 'transactional', 'topic' => "Demenager {$en} : checklist complete ({$year})"],
            ['type' => 'tutorial', 'intent' => 'transactional', 'topic' => "Ouvrir un compte bancaire {$en} en ligne : tutoriel ({$year})"],
            ['type' => 'tutorial', 'intent' => 'transactional', 'topic' => "S'inscrire au consulat {$en} : demarche pas a pas ({$year})"],
            ['type' => 'tutorial', 'intent' => 'transactional', 'topic' => "Trouver un avocat {$en} : ou chercher et combien ca coute ({$year})"],
            ['type' => 'tutorial', 'intent' => 'transactional', 'topic' => "Passer le permis de conduire {$en} : tutoriel complet ({$year})"],
            ['type' => 'tutorial', 'intent' => 'transactional', 'topic' => "Souscrire une assurance sante {$en} : guide pas a pas ({$year})"],
            ['type' => 'tutorial', 'intent' => 'transactional', 'topic' => "Declaration d'impots {$en} : guide pour expatries ({$year})"],

            // ── DIGITAL NOMAD / LIFESTYLE (6) — Growing segment ──
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Digital nomad {$en} : visa, coworking et cout de vie ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Retraite {$en} : visa, fiscalite et qualite de vie ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Etudier {$en} : universites, bourses et vie etudiante ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Gastronomie {$en} : plats typiques et ou manger ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Benevolat {$en} : associations et missions pour expatries ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Apprendre la langue locale {$en} : ecoles et methodes ({$year})"],

            // ── STATISTIQUES (6) — Data-driven authority ──
            ['type' => 'statistics', 'intent' => 'informational', 'topic' => "Population expatriee {$en} : chiffres et tendances ({$year})"],
            ['type' => 'statistics', 'intent' => 'informational', 'topic' => "Cout de la vie {$en} en chiffres : loyer, courses, transport ({$year})"],
            ['type' => 'statistics', 'intent' => 'informational', 'topic' => "Salaires moyens {$en} par secteur ({$year})"],
            ['type' => 'statistics', 'intent' => 'informational', 'topic' => "Prix de l'immobilier {$en} : achat et location ({$year})"],
            ['type' => 'statistics', 'intent' => 'informational', 'topic' => "Criminalite et securite {$en} : statistiques ({$year})"],
            ['type' => 'statistics', 'intent' => 'informational', 'topic' => "Qualite de vie {$en} : classement et indicateurs ({$year})"],

            // ── OUTREACH (6) — Affiliate recruitment ──
            ['type' => 'outreach', 'intent' => 'informational', 'topic' => "Devenir chatter SOS-Expat {$en} : aider les expatries et gagner de l'argent ({$year})"],
            ['type' => 'outreach', 'intent' => 'informational', 'topic' => "Influenceur expatriation {$en} : rejoindre le programme SOS-Expat ({$year})"],
            ['type' => 'outreach', 'intent' => 'informational', 'topic' => "Blogueur voyage {$en} : monetiser votre blog avec SOS-Expat ({$year})"],
            ['type' => 'outreach', 'intent' => 'informational', 'topic' => "Admin de groupe expat {$en} : monetiser votre communaute ({$year})"],
            ['type' => 'outreach', 'intent' => 'informational', 'topic' => "Avocat {$en} : devenir partenaire SOS-Expat ({$year})"],
            ['type' => 'outreach', 'intent' => 'informational', 'topic' => "Expat {$en} : aidez d'autres expatries et gagnez des commissions ({$year})"],

            // ── TEMOIGNAGES (4) — Social proof ──
            ['type' => 'testimonial', 'intent' => 'informational', 'topic' => "Temoignage : mon expatriation {$en}, les debuts ({$year})"],
            ['type' => 'testimonial', 'intent' => 'informational', 'topic' => "Temoignage : travailler {$en} en tant qu'etranger ({$year})"],
            ['type' => 'testimonial', 'intent' => 'informational', 'topic' => "Temoignage : s'installer en famille en {$countryName} ({$year})"],
            ['type' => 'testimonial', 'intent' => 'informational', 'topic' => "Temoignage : digital nomad {$en}, avantages et difficultes ({$year})"],

            // ── BRAND CONTENT (2) — SOS-Expat positioning ──
            ['type' => 'article', 'intent' => 'commercial_investigation', 'topic' => "SOS-Expat {$en} : comment ca marche et pourquoi les expatries l'utilisent ({$year})"],
            ['type' => 'article', 'intent' => 'commercial_investigation', 'topic' => "Assistance juridique {$en} : SOS-Expat vs avocat local vs assurance ({$year})"],
        ];
        // Total: fiche_pays(1/stats) + guides(5) + city(3) + juridique(12) + pratique(10) + pain(10)
        //       + comparatif(8) + Q/R(19) + tutorial(8) + lifestyle(6) + stats(6)
        //       + outreach(6) + temoignages(4) + brand(2) = 100
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
     * French prepositions for countries: "en" (feminine/vowel), "au" (masc sing), "aux" (plural).
     * Used in templates: "S'expatrier {prep} {country}"
     */
    private const COUNTRY_PREP = [
        'TH' => 'en',  'VN' => 'au',  'PT' => 'au',  'ES' => 'en',  'ID' => 'en',
        'MX' => 'au',  'MA' => 'au',  'AE' => 'aux', 'SG' => 'a',   'JP' => 'au',
        'DE' => 'en',  'GB' => 'au',  'US' => 'aux', 'CA' => 'au',  'AU' => 'en',
        'BR' => 'au',  'CO' => 'en',  'CR' => 'au',  'GR' => 'en',  'HR' => 'en',
        'IT' => 'en',  'NL' => 'aux', 'BE' => 'en',  'CH' => 'en',  'TR' => 'en',
        'PH' => 'aux', 'MY' => 'en',  'KH' => 'au',  'IN' => 'en',  'PL' => 'en',
    ];

    /**
     * French preposition "de" variants: "de" (vowel/fem), "du" (masc), "des" (plural).
     * Used in templates: "Expulsion {de_prep} {country}"
     */
    private const COUNTRY_DE_PREP = [
        'TH' => 'de',  'VN' => 'du',  'PT' => 'du',  'ES' => "d'",  'ID' => "d'",
        'MX' => 'du',  'MA' => 'du',  'AE' => 'des', 'SG' => 'de',  'JP' => 'du',
        'DE' => "d'",  'GB' => 'du',  'US' => 'des', 'CA' => 'du',  'AU' => "d'",
        'BR' => 'du',  'CO' => 'de',  'CR' => 'du',  'GR' => 'de',  'HR' => 'de',
        'IT' => "d'",  'NL' => 'des', 'BE' => 'de',  'CH' => 'de',  'TR' => 'de',
        'PH' => 'des', 'MY' => 'de',  'KH' => 'du',  'IN' => "d'",  'PL' => 'de',
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
            ->toArray();

        $existingCount = count($existingTitles);
        $this->info("Existing articles for {$countryCode}: {$existingCount}");

        // Filter out already-generated topics using keyword-based semantic dedup
        $existingKeywordSets = array_map(fn ($t) => $this->extractDedupKeywords($t, $countryName), $existingTitles);

        $toGenerate = [];
        foreach ($plan as $item) {
            $topicKeywords = $this->extractDedupKeywords($item['topic'], $countryName);
            $isDuplicate = false;
            foreach ($existingKeywordSets as $existingKw) {
                // Count overlapping keywords — if >= 2 core keywords match, it's a duplicate
                $overlap = count(array_intersect($topicKeywords, $existingKw));
                if ($overlap >= 2) {
                    $isDuplicate = true;
                    $this->line("  [SKIP] \"{$item['topic']}\" — overlaps with existing article");
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
                'content_type'   => $item['type'],
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
     * Extract core keywords for dedup comparison.
     * Strips country name, year, accents, stopwords — returns array of significant words.
     */
    private function extractDedupKeywords(string $text, string $countryName): array
    {
        // Normalize: lowercase, strip accents, remove year, remove country name
        $text = mb_strtolower($text);
        $text = $this->stripAccents($text);
        $countryNorm = $this->stripAccents(mb_strtolower($countryName));
        $text = str_replace($countryNorm, '', $text);
        $text = preg_replace('/\(\d{4}\)|\b\d{4}\b/', '', $text);
        $text = preg_replace('/[^a-z\s]/', ' ', $text);
        $text = preg_replace('/\s+/', ' ', trim($text));

        // Remove stopwords
        $stopwords = ['en', 'de', 'du', 'des', 'le', 'la', 'les', 'un', 'une', 'et', 'ou', 'pour', 'par',
            'ce', 'que', 'qui', 'il', 'est', 'au', 'aux', 'son', 'sa', 'ses', 'a', 'dans', 'sur',
            'pas', 'ne', 'se', 'avec', 'plus', 'tant', 'qu', 'votre', 'vos', 'nos', 'mon', 'ma',
            'quel', 'quelle', 'quels', 'quelles', 'comment', 'faut', 'peut', 'on', 'faire',
            'guide', 'complet', 'complete', 'pratique', 'pratiques', 'conseils', 'etapes',
            'essentielles', 'detaille', 'tout', 'toutes', 'savoir', 'an', 'ans',
            'expatrie', 'expatries', 'expatriation', 'etranger', 'etrangers'];

        $words = array_filter(explode(' ', $text), fn ($w) => strlen($w) >= 3 && !in_array($w, $stopwords));

        return array_values(array_unique($words));
    }

    /**
     * Strip accents from a string (e→e, ï→i, etc.)
     */
    private function stripAccents(string $str): string
    {
        $transliterator = \Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC');
        return $transliterator ? $transliterator->transliterate($str) : $str;
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
