<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Phase 2.2 — 50 real SEO comparatives with genuine search intent.
 *
 * Each comparative targets a real "X vs Y" search query that people
 * actually type into Google. Categories: countries, services, visa types,
 * insurance, banking, lifestyle.
 *
 * These are NOT promotional articles. They are objective comparisons
 * that build trust and authority (E-E-A-T).
 */
class ComparativeSeoSeeder extends Seeder
{
    public function run(): void
    {
        $comparatives = [
            // ══════════════════════════════════════════
            // PAYS VS PAYS — Destination expatriation
            // ══════════════════════════════════════════
            ['entities' => ['Portugal', 'Espagne'], 'category' => 'country', 'intent' => 'Meilleur pays pour retraite en Europe du Sud', 'keywords' => 'portugal vs espagne expatriation, retraite portugal ou espagne'],
            ['entities' => ['Thailande', 'Vietnam'], 'category' => 'country', 'intent' => 'Cout de vie Asie du Sud-Est pour digital nomads', 'keywords' => 'thailande vs vietnam digital nomad, vivre en asie pas cher'],
            ['entities' => ['Dubai', 'Singapour'], 'category' => 'country', 'intent' => 'Hub business international zero impot', 'keywords' => 'dubai vs singapour expatrie, creer entreprise dubai ou singapour'],
            ['entities' => ['Canada', 'Australie'], 'category' => 'country', 'intent' => 'Immigration anglophone qualifiee', 'keywords' => 'canada vs australie immigration, travailler canada ou australie'],
            ['entities' => ['Mexique', 'Costa Rica'], 'category' => 'country', 'intent' => 'Retraite Amerique latine francophone', 'keywords' => 'mexique vs costa rica retraite, vivre amerique centrale'],
            ['entities' => ['Bali', 'Chiang Mai'], 'category' => 'country', 'intent' => 'Destination digital nomad Asie', 'keywords' => 'bali vs chiang mai nomade, meilleure destination remote work asie'],
            ['entities' => ['Allemagne', 'Pays-Bas'], 'category' => 'country', 'intent' => 'Expatriation emploi Europe du Nord', 'keywords' => 'allemagne vs pays-bas travail, salaire ingenieur allemagne hollande'],
            ['entities' => ['Maroc', 'Tunisie'], 'category' => 'country', 'intent' => 'Retraite Maghreb francophone', 'keywords' => 'maroc vs tunisie retraite, vivre au maghreb francais'],
            ['entities' => ['Grece', 'Croatie'], 'category' => 'country', 'intent' => 'Visa digital nomad Mediterranee', 'keywords' => 'grece vs croatie digital nomad, visa nomade mediterranee'],
            ['entities' => ['Suisse', 'Luxembourg'], 'category' => 'country', 'intent' => 'Expatriation salaire eleve Europe', 'keywords' => 'suisse vs luxembourg salaire, travailler frontalier suisse luxembourg'],
            ['entities' => ['Colombie', 'Equateur'], 'category' => 'country', 'intent' => 'Vivre en Amerique du Sud pas cher', 'keywords' => 'colombie vs equateur expatrie, cout vie amerique sud'],
            ['entities' => ['Malte', 'Chypre'], 'category' => 'country', 'intent' => 'Residence fiscale ile europeenne', 'keywords' => 'malte vs chypre fiscalite, optimisation fiscale ile europe'],
            ['entities' => ['Senegal', 'Cote d\'Ivoire'], 'category' => 'country', 'intent' => 'Expatriation Afrique de l\'Ouest francophone', 'keywords' => 'senegal vs cote ivoire expatrie, vivre afrique ouest'],
            ['entities' => ['Japon', 'Coree du Sud'], 'category' => 'country', 'intent' => 'Travailler en Asie de l\'Est', 'keywords' => 'japon vs coree sud travail, visa travail asie est'],
            ['entities' => ['Portugal', 'Grece'], 'category' => 'country', 'intent' => 'Golden visa Europe comparatif', 'keywords' => 'golden visa portugal vs grece, residence investissement europe'],

            // ══════════════════════════════════════════
            // ASSURANCES EXPATRIES
            // ══════════════════════════════════════════
            ['entities' => ['SafetyWing', 'World Nomads'], 'category' => 'insurance', 'intent' => 'Assurance nomade numerique comparatif', 'keywords' => 'safetywing vs world nomads, meilleure assurance digital nomad'],
            ['entities' => ['Cigna Global', 'Allianz Care'], 'category' => 'insurance', 'intent' => 'Assurance sante expat premium', 'keywords' => 'cigna vs allianz expatrie, assurance sante internationale comparatif'],
            ['entities' => ['AXA Expat', 'April International'], 'category' => 'insurance', 'intent' => 'Assurance expatrie francais', 'keywords' => 'axa expat vs april international, assurance expatrie francais comparatif'],
            ['entities' => ['CFE', 'Assurance privee'], 'category' => 'insurance', 'intent' => 'CFE ou assurance privee expatrie', 'keywords' => 'cfe vs assurance privee, securite sociale expatrie france'],
            ['entities' => ['Heymondo', 'Chapka'], 'category' => 'insurance', 'intent' => 'Assurance voyage longue duree PVT', 'keywords' => 'heymondo vs chapka, assurance pvt working holiday'],

            // ══════════════════════════════════════════
            // BANQUES & TRANSFERTS
            // ══════════════════════════════════════════
            ['entities' => ['Wise', 'Revolut'], 'category' => 'finance', 'intent' => 'Meilleure banque en ligne pour expatrie', 'keywords' => 'wise vs revolut expatrie, carte bancaire internationale sans frais'],
            ['entities' => ['Wise', 'Western Union'], 'category' => 'finance', 'intent' => 'Transfert argent international pas cher', 'keywords' => 'wise vs western union frais, envoyer argent etranger pas cher'],
            ['entities' => ['N26', 'Revolut'], 'category' => 'finance', 'intent' => 'Banque mobile Europe comparatif', 'keywords' => 'n26 vs revolut europe, compte bancaire digital expatrie'],
            ['entities' => ['Remitly', 'WorldRemit'], 'category' => 'finance', 'intent' => 'Envoyer argent Afrique pas cher', 'keywords' => 'remitly vs worldremit afrique, transfert argent afrique comparatif'],
            ['entities' => ['PayPal', 'Wise'], 'category' => 'finance', 'intent' => 'Recevoir paiements freelance international', 'keywords' => 'paypal vs wise freelance, recevoir paiement international'],

            // ══════════════════════════════════════════
            // VPN POUR EXPATRIES
            // ══════════════════════════════════════════
            ['entities' => ['NordVPN', 'ExpressVPN'], 'category' => 'vpn', 'intent' => 'Meilleur VPN pour expatrie', 'keywords' => 'nordvpn vs expressvpn expatrie, vpn streaming francais etranger'],
            ['entities' => ['NordVPN', 'Surfshark'], 'category' => 'vpn', 'intent' => 'VPN pas cher pour voyageurs', 'keywords' => 'nordvpn vs surfshark prix, vpn economique voyage'],

            // ══════════════════════════════════════════
            // VISA & STATUTS JURIDIQUES
            // ══════════════════════════════════════════
            ['entities' => ['Visa digital nomad', 'Visa touriste renouvele'], 'category' => 'visa', 'intent' => 'Travailler legalement a distance etranger', 'keywords' => 'visa digital nomad vs visa touriste, travailler distance legalement'],
            ['entities' => ['PVT Canada', 'PVT Australie'], 'category' => 'visa', 'intent' => 'Comparatif PVT pays anglophones', 'keywords' => 'pvt canada vs australie, working holiday visa comparatif'],
            ['entities' => ['Golden Visa', 'Visa investisseur'], 'category' => 'visa', 'intent' => 'Residence par investissement comparatif', 'keywords' => 'golden visa vs visa investisseur, residence investissement europe'],
            ['entities' => ['Visa D7 Portugal', 'NHR Portugal'], 'category' => 'visa', 'intent' => 'S\'installer au Portugal fiscalite', 'keywords' => 'visa d7 vs nhr portugal, optimisation fiscale portugal'],
            ['entities' => ['Micro-entreprise France', 'LLC USA'], 'category' => 'visa', 'intent' => 'Statut freelance international', 'keywords' => 'micro entreprise vs llc usa, creer entreprise freelance international'],

            // ══════════════════════════════════════════
            // HEBERGEMENT & LOGEMENT
            // ══════════════════════════════════════════
            ['entities' => ['Airbnb longue duree', 'Location classique'], 'category' => 'housing', 'intent' => 'Logement expatrie court vs long terme', 'keywords' => 'airbnb long terme vs location, logement expatrie temporaire'],
            ['entities' => ['HousingAnywhere', 'Spotahome'], 'category' => 'housing', 'intent' => 'Trouver logement avant arrivee expatrie', 'keywords' => 'housinganywhere vs spotahome, location longue duree en ligne'],
            ['entities' => ['Coliving', 'Appartement solo'], 'category' => 'housing', 'intent' => 'Coliving digital nomad avantages', 'keywords' => 'coliving vs appartement nomade, logement partage expatrie'],

            // ══════════════════════════════════════════
            // TELECOM & ESIM
            // ══════════════════════════════════════════
            ['entities' => ['Airalo', 'Holafly'], 'category' => 'telecom', 'intent' => 'Meilleure eSIM voyage international', 'keywords' => 'airalo vs holafly comparatif, esim voyage pas cher'],
            ['entities' => ['eSIM', 'SIM locale'], 'category' => 'telecom', 'intent' => 'Telephonie expatrie solution', 'keywords' => 'esim vs sim locale expatrie, forfait telephone etranger'],

            // ══════════════════════════════════════════
            // FORMATION & LANGUES
            // ══════════════════════════════════════════
            ['entities' => ['Babbel', 'Duolingo'], 'category' => 'education', 'intent' => 'Apprendre langue locale expatrie', 'keywords' => 'babbel vs duolingo expatrie, apprendre langue rapidement'],
            ['entities' => ['Preply', 'iTalki'], 'category' => 'education', 'intent' => 'Cours langue avec tuteur natif', 'keywords' => 'preply vs italki tuteur, cours langue en ligne natif'],

            // ══════════════════════════════════════════
            // LIFESTYLE COMPARATIFS
            // ══════════════════════════════════════════
            ['entities' => ['Expatriation', 'Digital nomadisme'], 'category' => 'lifestyle', 'intent' => 'S\'expatrier ou voyager en travaillant', 'keywords' => 'expatriation vs digital nomad, difference expatrie nomade'],
            ['entities' => ['Freelance a l\'etranger', 'Salarie detache'], 'category' => 'lifestyle', 'intent' => 'Statut travail expatrie comparatif', 'keywords' => 'freelance vs salarie etranger, travailler etranger quel statut'],
            ['entities' => ['Retraite a l\'etranger', 'Retraite en France'], 'category' => 'lifestyle', 'intent' => 'Vaut-il le coup de prendre sa retraite a l\'etranger', 'keywords' => 'retraite etranger vs france, avantages retraite hors france'],
            ['entities' => ['Ecole internationale', 'Ecole locale'], 'category' => 'lifestyle', 'intent' => 'Scolarite enfants expatries', 'keywords' => 'ecole internationale vs locale, scolarite enfant expatrie'],
            ['entities' => ['Assurance locale', 'Assurance internationale'], 'category' => 'lifestyle', 'intent' => 'Couverture sante expatrie choix', 'keywords' => 'assurance locale vs internationale expatrie, sante expatrie quelle couverture'],

            // ══════════════════════════════════════════
            // DEMENAGEMENT & SERVICES
            // ══════════════════════════════════════════
            ['entities' => ['Demenagement international', 'Envoi bagages'], 'category' => 'moving', 'intent' => 'Expedier affaires etranger pas cher', 'keywords' => 'demenagement vs envoi bagages international, expedier affaires etranger'],
            ['entities' => ['Sirelo', 'MoveHub'], 'category' => 'moving', 'intent' => 'Comparateur demenagement international', 'keywords' => 'sirelo vs movehub demenagement, devis demenagement international'],

            // ══════════════════════════════════════════
            // FISCALITE & ADMINISTRATION
            // ══════════════════════════════════════════
            ['entities' => ['Regime NHR Portugal', 'Beckham Law Espagne'], 'category' => 'tax', 'intent' => 'Regime fiscal avantageux expatrie Europe', 'keywords' => 'nhr portugal vs beckham espagne, optimisation fiscale expatrie europe'],
            ['entities' => ['Resident fiscal', 'Non-resident fiscal'], 'category' => 'tax', 'intent' => 'Statut fiscal expatrie France', 'keywords' => 'resident vs non resident fiscal france, impots expatrie france'],
            ['entities' => ['CFE Securite Sociale', 'Assurance privee internationale'], 'category' => 'tax', 'intent' => 'Protection sociale expatrie francais', 'keywords' => 'cfe vs assurance privee expatrie, securite sociale francais etranger'],
        ];

        $now = now();

        foreach ($comparatives as $i => $comp) {
            DB::table('comparatives')->insertOrIgnore([
                'uuid' => (string) Str::uuid(),
                'title' => implode(' vs ', $comp['entities']) . ' : comparatif ' . date('Y'),
                'slug' => Str::slug(implode(' vs ', $comp['entities'])),
                'language' => 'fr',
                'country' => null,
                'entities' => json_encode($comp['entities']),
                'comparison_data' => json_encode([
                    'category' => $comp['category'],
                    'search_intent' => $comp['intent'],
                    'target_keywords' => $comp['keywords'],
                ]),
                'content_html' => null,
                'excerpt' => $comp['intent'],
                'meta_title' => null,
                'meta_description' => null,
                'json_ld' => null,
                'hreflang_map' => null,
                'seo_score' => 0,
                'quality_score' => 0,
                'generation_cost_cents' => 0,
                'generation_tokens_input' => 0,
                'generation_tokens_output' => 0,
                'parent_id' => null,
                'status' => 'draft',
                'published_at' => null,
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->command?->info("Seeded " . count($comparatives) . " SEO comparatives.");
    }
}
