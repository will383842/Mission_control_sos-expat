<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Phase 2.4 — 35 affiliate comparative article topics.
 *
 * These are article topics designed for affiliate conversion:
 * "Top 5 X for expatriates" or "Best X for digital nomads"
 *
 * Each article will include affiliate links to relevant programs
 * from the affiliate_programs table.
 */
class AffiliateComparativeSeeder extends Seeder
{
    public function run(): void
    {
        $topics = [
            // ── ASSURANCES ──────────────────────────────────────────
            ['title' => 'Top 7 assurances sante pour expatries en 2026', 'category' => 'insurance', 'programs' => 'SafetyWing,World Nomads,Cigna Global Health,AXA Expat,Allianz Care,iHi Bupa,Heymondo', 'keywords' => 'meilleure assurance expatrie 2026, comparatif assurance sante internationale'],
            ['title' => 'Meilleures assurances voyage longue duree pour digital nomads', 'category' => 'insurance', 'programs' => 'SafetyWing,World Nomads,Heymondo', 'keywords' => 'assurance digital nomad, assurance voyage longue duree pas cher'],
            ['title' => 'CFE ou assurance privee : quel choix pour un expatrie francais ?', 'category' => 'insurance', 'programs' => 'AXA Expat,Cigna Global Health,Allianz Care', 'keywords' => 'cfe vs assurance privee, securite sociale expatrie'],
            ['title' => 'Assurance PVT : comparatif 2026 Canada, Australie, Japon', 'category' => 'insurance', 'programs' => 'Heymondo,World Nomads', 'keywords' => 'assurance pvt comparatif, working holiday visa assurance'],

            // ── BANQUES & TRANSFERTS ────────────────────────────────
            ['title' => 'Top 5 banques en ligne pour expatries en 2026', 'category' => 'finance', 'programs' => 'Wise (TransferWise),Revolut,N26', 'keywords' => 'meilleure banque expatrie 2026, banque en ligne sans frais etranger'],
            ['title' => 'Comparatif transfert argent international : la moins chere en 2026', 'category' => 'finance', 'programs' => 'Wise (TransferWise),Remitly,WorldRemit,Paysend,XE Money Transfer', 'keywords' => 'transfert argent international pas cher, envoyer argent etranger comparatif'],
            ['title' => 'Envoyer de l\'argent en Afrique : top 5 services les moins chers', 'category' => 'finance', 'programs' => 'Remitly,WorldRemit,Wise (TransferWise),Paysend', 'keywords' => 'envoyer argent afrique pas cher, transfert mobile money comparatif'],
            ['title' => 'Carte bancaire pour voyager sans frais : comparatif 2026', 'category' => 'finance', 'programs' => 'Wise (TransferWise),Revolut,N26', 'keywords' => 'carte bancaire voyage sans frais, carte zero frais etranger'],
            ['title' => 'Freelance international : quel compte bancaire choisir ?', 'category' => 'finance', 'programs' => 'Wise (TransferWise),Revolut,N26', 'keywords' => 'compte bancaire freelance international, recevoir paiement etranger'],

            // ── VPN ─────────────────────────────────────────────────
            ['title' => 'Top 3 VPN pour expatries 2026 : regarder la TV francaise a l\'etranger', 'category' => 'vpn', 'programs' => 'NordVPN,ExpressVPN,Surfshark', 'keywords' => 'meilleur vpn expatrie 2026, regarder tv francaise etranger vpn'],
            ['title' => 'VPN pas cher pour digital nomads : comparatif prix et performances', 'category' => 'vpn', 'programs' => 'NordVPN,Surfshark,ExpressVPN', 'keywords' => 'vpn pas cher digital nomad, vpn voyage pas cher'],

            // ── ESIM & TELECOM ──────────────────────────────────────
            ['title' => 'Meilleures eSIM pour voyager en 2026 : comparatif complet', 'category' => 'telecom', 'programs' => 'Airalo (eSIM),Holafly (eSIM),SimOptions', 'keywords' => 'meilleure esim voyage 2026, esim international comparatif'],
            ['title' => 'eSIM vs SIM locale : que choisir pour un expatrie ?', 'category' => 'telecom', 'programs' => 'Airalo (eSIM),Holafly (eSIM)', 'keywords' => 'esim ou sim locale expatrie, forfait telephone etranger'],
            ['title' => 'Top 5 forfaits telephoniques internationaux pour expatries', 'category' => 'telecom', 'programs' => 'Airalo (eSIM),Holafly (eSIM),SimOptions', 'keywords' => 'forfait international expatrie, telephone portable etranger'],

            // ── LOGEMENT & DEMENAGEMENT ──────────────────────────────
            ['title' => 'Comparatif demenagement international 2026 : tarifs et avis', 'category' => 'housing', 'programs' => 'Sirelo,MoveHub', 'keywords' => 'demenagement international comparatif, prix demenagement etranger'],
            ['title' => 'Trouver un logement a distance avant votre expatriation', 'category' => 'housing', 'programs' => 'HousingAnywhere,Spotahome,Booking.com', 'keywords' => 'logement expatrie distance, trouver appartement avant arrivee'],
            ['title' => 'Airbnb longue duree vs location classique pour expatries', 'category' => 'housing', 'programs' => 'Airbnb,HousingAnywhere,Spotahome', 'keywords' => 'airbnb longue duree expatrie, location meublee etranger'],
            ['title' => 'Coliving pour digital nomads : top 10 destinations et plateformes', 'category' => 'housing', 'programs' => 'HousingAnywhere,Spotahome', 'keywords' => 'coliving digital nomad, meilleur coliving monde'],
            ['title' => 'Location voiture longue duree a l\'etranger : comparatif', 'category' => 'housing', 'programs' => 'Rentalcars.com', 'keywords' => 'location voiture expatrie, louer voiture etranger longue duree'],

            // ── FORMATION & LANGUES ─────────────────────────────────
            ['title' => 'Apprendre une langue avant l\'expatriation : top 5 apps 2026', 'category' => 'education', 'programs' => 'Babbel,Preply', 'keywords' => 'apprendre langue expatriation, meilleure app langue 2026'],
            ['title' => 'Cours de langue en ligne avec tuteur natif : comparatif', 'category' => 'education', 'programs' => 'Preply', 'keywords' => 'cours langue tuteur natif, preply italki comparatif'],
            ['title' => 'Reconversion professionnelle a l\'etranger : top formations en ligne', 'category' => 'education', 'programs' => 'Coursera,Udemy', 'keywords' => 'formation en ligne expatrie, reconversion professionnelle etranger'],

            // ── VOYAGE & VOLS ───────────────────────────────────────
            ['title' => 'Comparateur de vols pas chers pour expatries : les meilleurs en 2026', 'category' => 'travel', 'programs' => 'Skyscanner,Kiwi.com', 'keywords' => 'vol pas cher expatrie, billet avion pas cher international'],
            ['title' => 'Ou loger en arrivant dans un nouveau pays : guide pratique', 'category' => 'travel', 'programs' => 'Booking.com,Airbnb,HousingAnywhere', 'keywords' => 'logement temporaire expatrie, ou dormir arrivee nouveau pays'],

            // ── COMMUNAUTE ──────────────────────────────────────────
            ['title' => 'Top 5 reseaux d\'expatries pour rencontrer du monde a l\'etranger', 'category' => 'community', 'programs' => 'Expat.com,InterNations,Meetup', 'keywords' => 'reseau expatrie, rencontrer expatries ville'],

            // ── ADMINISTRATIF ───────────────────────────────────────
            ['title' => 'Signature electronique pour expatries : comparatif outils 2026', 'category' => 'legal', 'programs' => 'HelloSign (Dropbox)', 'keywords' => 'signature electronique expatrie, signer documents distance etranger'],

            // ── SHOPPING & PRATIQUE ──────────────────────────────────
            ['title' => 'Kit de survie expatrie : 20 achats essentiels avant le depart', 'category' => 'shopping', 'programs' => 'Amazon France,Amazon UK', 'keywords' => 'achats expatriation, kit depart expatrie, liste courses demenagement'],
            ['title' => 'Boite postale virtuelle pour expatrie : comparatif services', 'category' => 'other', 'programs' => 'Mailbox (Anytime Mailbox)', 'keywords' => 'boite postale virtuelle expatrie, adresse postale sans domicile fixe'],

            // ── EMPLOI ──────────────────────────────────────────────
            ['title' => 'Trouver un emploi a l\'etranger : top 5 plateformes en 2026', 'category' => 'employment', 'programs' => 'LinkedIn Premium', 'keywords' => 'trouver emploi etranger, site emploi international'],

            // ── MULTI-CATEGORIE (hub articles) ──────────────────────
            ['title' => 'Pack complet expatriation 2026 : les 10 services indispensables', 'category' => 'other', 'programs' => 'Wise (TransferWise),SafetyWing,NordVPN,Airalo (eSIM),Revolut', 'keywords' => 'services indispensables expatrie, outils expatriation 2026'],
            ['title' => 'Digital nomad starter pack : les 8 outils essentiels', 'category' => 'other', 'programs' => 'SafetyWing,Wise (TransferWise),NordVPN,Surfshark,Airalo (eSIM)', 'keywords' => 'outils digital nomad, starter pack nomade numerique'],
            ['title' => 'Retraite a l\'etranger : les 7 services pour bien preparer', 'category' => 'other', 'programs' => 'Wise (TransferWise),Cigna Global Health,AXA Expat,Sirelo', 'keywords' => 'preparer retraite etranger, services retraite expatrie'],
            ['title' => 'Famille expatriee : les 10 services essentiels pour s\'installer', 'category' => 'other', 'programs' => 'Cigna Global Health,HousingAnywhere,Babbel,Booking.com', 'keywords' => 'famille expatriee services, installation famille etranger'],
            ['title' => 'Budget expatriation 2026 : combien coute de s\'installer a l\'etranger', 'category' => 'other', 'programs' => 'Wise (TransferWise),SafetyWing,Sirelo', 'keywords' => 'budget expatriation, combien coute expatrier'],
        ];

        $now = now();

        foreach ($topics as $i => $topic) {
            DB::table('comparatives')->insertOrIgnore([
                'uuid' => (string) Str::uuid(),
                'title' => $topic['title'],
                'slug' => Str::slug($topic['title']),
                'language' => 'fr',
                'country' => null,
                'entities' => json_encode(explode(',', $topic['programs'])),
                'comparison_data' => json_encode([
                    'type' => 'affiliate_comparative',
                    'category' => $topic['category'],
                    'target_keywords' => $topic['keywords'],
                    'affiliate_programs' => $topic['programs'],
                ]),
                'content_html' => null,
                'excerpt' => null,
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

        $this->command?->info("Seeded " . count($topics) . " affiliate comparative topics.");
    }
}
