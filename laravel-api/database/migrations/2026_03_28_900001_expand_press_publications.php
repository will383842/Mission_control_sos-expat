<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $existing = DB::table('press_publications')->pluck('slug')->toArray();

        $pubs = [

            // ── PRESSE JURIDIQUE ──────────────────────────────────────────────
            ['slug' => 'village-justice',       'name' => 'Village de la Justice',        'base_url' => 'https://www.village-justice.com',      'team_url' => 'https://www.village-justice.com/articles/redaction',    'category' => 'presse_juridique', 'email_pattern' => '{first}.{last}', 'email_domain' => 'village-justice.com'],
            ['slug' => 'lemonde-du-droit',       'name' => 'Le Monde du Droit',            'base_url' => 'https://www.lemondedudroit.fr',         'team_url' => 'https://www.lemondedudroit.fr/qui-sommes-nous',         'category' => 'presse_juridique', 'email_pattern' => '{first}.{last}', 'email_domain' => 'lemondedudroit.fr'],
            ['slug' => 'dalloz-actualites',      'name' => 'Dalloz Actualités',            'base_url' => 'https://www.dalloz-actualite.fr',      'team_url' => 'https://www.dalloz-actualite.fr/la-redaction',          'category' => 'presse_juridique', 'email_pattern' => null,             'email_domain' => 'dalloz.fr'],
            ['slug' => 'legavox',                'name' => 'Legavox — Droit & Conseils',   'base_url' => 'https://www.legavox.fr',               'team_url' => null,                                                    'category' => 'presse_juridique', 'email_pattern' => '{first}.{last}', 'email_domain' => 'legavox.fr'],
            ['slug' => 'lexbase',                'name' => 'Lexbase — Information Juridique','base_url'=> 'https://www.lexbase.fr',              'team_url' => 'https://www.lexbase.fr/pages/la-redaction',             'category' => 'presse_juridique', 'email_pattern' => '{first}.{last}', 'email_domain' => 'lexbase.fr'],
            ['slug' => 'doc-du-juriste',         'name' => 'Doc du Juriste',               'base_url' => 'https://www.doc-du-juriste.com',       'team_url' => 'https://www.doc-du-juriste.com/a-propos',               'category' => 'presse_juridique', 'email_pattern' => '{first}.{last}', 'email_domain' => 'doc-du-juriste.com'],
            ['slug' => 'actu-juridique',         'name' => 'Actu-Juridique',               'base_url' => 'https://www.actu-juridique.fr',        'team_url' => 'https://www.actu-juridique.fr/qui-sommes-nous',         'category' => 'presse_juridique', 'email_pattern' => '{first}.{last}', 'email_domain' => 'actu-juridique.fr'],
            ['slug' => 'fiscalonline',           'name' => 'FiscalOnline',                 'base_url' => 'https://www.fiscalonline.com',          'team_url' => 'https://www.fiscalonline.com/redaction',                'category' => 'presse_juridique', 'email_pattern' => '{first}.{last}', 'email_domain' => 'fiscalonline.com'],

            // ── PRESSE RH / MOBILITÉ INTERNATIONALE ──────────────────────────
            ['slug' => 'rhinfo',                 'name' => 'RH Info',                      'base_url' => 'https://www.rhinfo.com',               'team_url' => 'https://www.rhinfo.com/equipe',                         'category' => 'presse_rh_mobilite', 'email_pattern' => '{first}.{last}', 'email_domain' => 'rhinfo.com'],
            ['slug' => 'decision-rh',            'name' => 'Décision RH',                  'base_url' => 'https://www.decision-rh.com',          'team_url' => 'https://www.decision-rh.com/qui-sommes-nous',           'category' => 'presse_rh_mobilite', 'email_pattern' => '{first}.{last}', 'email_domain' => 'decision-rh.com'],
            ['slug' => 'people-management-fr',   'name' => 'People Management France',     'base_url' => 'https://www.people-management.fr',     'team_url' => null,                                                    'category' => 'presse_rh_mobilite', 'email_pattern' => '{first}.{last}', 'email_domain' => 'people-management.fr'],
            ['slug' => 'hrvoice',                'name' => 'HR Voice',                     'base_url' => 'https://www.hrvoice.fr',               'team_url' => 'https://www.hrvoice.fr/a-propos',                       'category' => 'presse_rh_mobilite', 'email_pattern' => '{first}.{last}', 'email_domain' => 'hrvoice.fr'],
            ['slug' => 'cadre-dirigeant-mag',    'name' => 'Cadre & Dirigeant Magazine',   'base_url' => 'https://www.cadre-dirigeant-magazine.com','team_url' => null,                                                 'category' => 'presse_rh_mobilite', 'email_pattern' => '{first}.{last}', 'email_domain' => 'cadre-dirigeant-magazine.com'],
            ['slug' => 'vocatis-mobilite',       'name' => 'Mobilité Internationale',      'base_url' => 'https://www.mobilite-internationale.com','team_url' => null,                                                  'category' => 'presse_rh_mobilite', 'email_pattern' => '{first}.{last}', 'email_domain' => 'mobilite-internationale.com'],
            ['slug' => 'expatria',               'name' => 'Expatria Magazine',            'base_url' => 'https://www.expatria.fr',              'team_url' => 'https://www.expatria.fr/redaction',                     'category' => 'presse_rh_mobilite', 'email_pattern' => '{first}.{last}', 'email_domain' => 'expatria.fr'],

            // ── PRESSE IMMOBILIER / PATRIMOINE ────────────────────────────────
            ['slug' => 'business-immo',          'name' => 'Business Immo',                'base_url' => 'https://www.businessimmo.com',          'team_url' => 'https://www.businessimmo.com/qui-sommes-nous',          'category' => 'presse_immobilier', 'email_pattern' => '{first}.{last}', 'email_domain' => 'businessimmo.com'],
            ['slug' => 'le-moniteur',            'name' => 'Le Moniteur',                  'base_url' => 'https://www.lemoniteur.fr',             'team_url' => 'https://www.lemoniteur.fr/la-redaction',                'category' => 'presse_immobilier', 'email_pattern' => '{first}.{last}', 'email_domain' => 'lemoniteur.fr'],
            ['slug' => 'figaro-immobilier',      'name' => 'Figaro Immobilier',            'base_url' => 'https://immobilier.lefigaro.fr',        'team_url' => null,                                                    'category' => 'presse_immobilier', 'email_pattern' => '{first}.{last}', 'email_domain' => 'lefigaro.fr'],
            ['slug' => 'mieux-vivre-votre-argent','name' => 'Mieux Vivre Votre Argent',   'base_url' => 'https://www.mieuxvivre-votreargent.fr', 'team_url' => 'https://www.mieuxvivre-votreargent.fr/la-redaction',   'category' => 'presse_immobilier', 'email_pattern' => '{first}.{last}', 'email_domain' => 'mieuxvivre-votreargent.fr'],
            ['slug' => 'patrimoine-fr',          'name' => 'Gestion de Fortune',           'base_url' => 'https://www.gestiondefortune.com',     'team_url' => 'https://www.gestiondefortune.com/equipe',               'category' => 'presse_immobilier', 'email_pattern' => '{first}.{last}', 'email_domain' => 'gestiondefortune.com'],
            ['slug' => 'boursorama-editorial',   'name' => 'BoursoBank Infos (editorial)', 'base_url' => 'https://www.boursorama.com',            'team_url' => null,                                                    'category' => 'presse_immobilier', 'email_pattern' => null,             'email_domain' => 'boursorama.com'],

            // ── PRESSE SANTÉ ─────────────────────────────────────────────────
            ['slug' => 'topsante',               'name' => 'Top Santé',                    'base_url' => 'https://www.topsante.com',             'team_url' => 'https://www.topsante.com/la-redaction',                 'category' => 'presse_sante', 'email_pattern' => '{first}.{last}', 'email_domain' => 'topsante.com'],
            ['slug' => 'sante-magazine',         'name' => 'Santé Magazine',               'base_url' => 'https://www.santemagazine.fr',         'team_url' => 'https://www.santemagazine.fr/redaction',                'category' => 'presse_sante', 'email_pattern' => '{first}.{last}', 'email_domain' => 'santemagazine.fr'],
            ['slug' => 'psychologies-magazine',  'name' => 'Psychologies Magazine',        'base_url' => 'https://www.psychologies.com',         'team_url' => 'https://www.psychologies.com/la-redaction',             'category' => 'presse_sante', 'email_pattern' => '{first}.{last}', 'email_domain' => 'psychologies.com'],
            ['slug' => 'allodocteurs',           'name' => 'AlloDocteurs (France 5)',      'base_url' => 'https://www.allodocteurs.fr',          'team_url' => 'https://www.allodocteurs.fr/equipe',                    'category' => 'presse_sante', 'email_pattern' => '{first}.{last}', 'email_domain' => 'allodocteurs.fr'],
            ['slug' => 'doctissimo',             'name' => 'Doctissimo (editorial)',       'base_url' => 'https://www.doctissimo.fr',            'team_url' => 'https://www.doctissimo.fr/la-redaction',                'category' => 'presse_sante', 'email_pattern' => '{first}.{last}', 'email_domain' => 'doctissimo.fr'],

            // ── MAGAZINE LIFESTYLE / FÉMININ ──────────────────────────────────
            ['slug' => 'elle-france',            'name' => 'Elle France',                  'base_url' => 'https://www.elle.fr',                  'team_url' => 'https://www.elle.fr/notre-equipe',                      'category' => 'magazine_feminin', 'email_pattern' => '{first}.{last}', 'email_domain' => 'elle.fr'],
            ['slug' => 'marie-claire-fr',        'name' => 'Marie Claire France',          'base_url' => 'https://www.marieclaire.fr',           'team_url' => 'https://www.marieclaire.fr/la-redaction',               'category' => 'magazine_feminin', 'email_pattern' => '{first}.{last}', 'email_domain' => 'marieclaire.fr'],
            ['slug' => 'femme-actuelle',         'name' => 'Femme Actuelle',               'base_url' => 'https://www.femmeactuelle.fr',         'team_url' => 'https://www.femmeactuelle.fr/redaction',                'category' => 'magazine_feminin', 'email_pattern' => '{first}.{last}', 'email_domain' => 'femmeactuelle.fr'],
            ['slug' => 'cosmopolitan-fr',        'name' => 'Cosmopolitan France',          'base_url' => 'https://www.cosmopolitan.fr',          'team_url' => 'https://www.cosmopolitan.fr/la-redaction',              'category' => 'magazine_feminin', 'email_pattern' => '{first}.{last}', 'email_domain' => 'cosmopolitan.fr'],
            ['slug' => 'vogue-france',           'name' => 'Vogue Paris',                  'base_url' => 'https://www.vogue.fr',                 'team_url' => 'https://www.vogue.fr/qui-sommes-nous',                  'category' => 'magazine_feminin', 'email_pattern' => '{first}.{last}', 'email_domain' => 'vogue.fr'],
            ['slug' => 'grazia-fr',              'name' => 'Grazia France',                'base_url' => 'https://www.grazia.fr',                'team_url' => 'https://www.grazia.fr/equipe',                          'category' => 'magazine_feminin', 'email_pattern' => '{first}.{last}', 'email_domain' => 'grazia.fr'],

            // ── PRESSE CULTURELLE ─────────────────────────────────────────────
            ['slug' => 'telerama',               'name' => 'Télérama',                     'base_url' => 'https://www.telerama.fr',              'team_url' => 'https://www.telerama.fr/la-redaction',                  'category' => 'presse_culturelle', 'email_pattern' => '{first}.{last}', 'email_domain' => 'telerama.fr'],
            ['slug' => 'lesinrocks',             'name' => 'Les Inrockuptibles',           'base_url' => 'https://www.lesinrocks.com',           'team_url' => 'https://www.lesinrocks.com/la-redaction',               'category' => 'presse_culturelle', 'email_pattern' => '{first}.{last}', 'email_domain' => 'lesinrocks.com'],
            ['slug' => 'paris-match',            'name' => 'Paris Match',                  'base_url' => 'https://www.parismatch.com',           'team_url' => 'https://www.parismatch.com/la-redaction',               'category' => 'presse_culturelle', 'email_pattern' => '{first}.{last}', 'email_domain' => 'parismatch.com'],
            ['slug' => 'le-figaro-magazine',     'name' => 'Le Figaro Magazine',           'base_url' => 'https://www.lefigaro.fr/fig-mag',      'team_url' => null,                                                    'category' => 'presse_culturelle', 'email_pattern' => '{first}.{last}', 'email_domain' => 'lefigaro.fr'],
            ['slug' => 'alternatives-economiques','name' => 'Alternatives Économiques',   'base_url' => 'https://www.alternatives-economiques.fr','team_url' => 'https://www.alternatives-economiques.fr/la-redaction',  'category' => 'presse_culturelle', 'email_pattern' => '{first}.{last}', 'email_domain' => 'alternatives-economiques.fr'],
            ['slug' => 'society-magazine',       'name' => 'Society Magazine',             'base_url' => 'https://www.societymagazine.fr',       'team_url' => 'https://www.societymagazine.fr/equipe',                 'category' => 'presse_culturelle', 'email_pattern' => '{first}.{last}', 'email_domain' => 'societymagazine.fr'],

            // ── PRESSE ENVIRONNEMENT / ECOLOGIE ──────────────────────────────
            ['slug' => 'reporterre',             'name' => 'Reporterre',                   'base_url' => 'https://reporterre.net',               'team_url' => 'https://reporterre.net/qui-sommes-nous',                'category' => 'presse_ecologie', 'email_pattern' => '{first}.{last}', 'email_domain' => 'reporterre.net'],
            ['slug' => 'novethic',               'name' => 'Novethic',                     'base_url' => 'https://www.novethic.fr',              'team_url' => 'https://www.novethic.fr/qui-sommes-nous',               'category' => 'presse_ecologie', 'email_pattern' => '{first}.{last}', 'email_domain' => 'novethic.fr'],
            ['slug' => 'basta-mag',              'name' => 'Basta!',                       'base_url' => 'https://basta.media',                  'team_url' => 'https://basta.media/equipe',                            'category' => 'presse_ecologie', 'email_pattern' => '{first}.{last}', 'email_domain' => 'basta.media'],

            // ── SITES EXPAT LOCAUX (par ville/pays) ───────────────────────────
            ['slug' => 'le-petit-journal-london','name' => 'Le Petit Journal — Londres',   'base_url' => 'https://lepetitjournal.com/londres',   'team_url' => 'https://lepetitjournal.com/londres/equipe',             'category' => 'presse_expat', 'email_pattern' => '{first}.{last}', 'email_domain' => 'lepetitjournal.com'],
            ['slug' => 'le-petit-journal-dubai', 'name' => 'Le Petit Journal — Dubaï',    'base_url' => 'https://lepetitjournal.com/dubai',     'team_url' => 'https://lepetitjournal.com/dubai/equipe',               'category' => 'presse_expat', 'email_pattern' => '{first}.{last}', 'email_domain' => 'lepetitjournal.com'],
            ['slug' => 'le-petit-journal-montreal','name' => 'Le Petit Journal — Montréal','base_url' => 'https://lepetitjournal.com/montreal',  'team_url' => 'https://lepetitjournal.com/montreal/equipe',            'category' => 'presse_expat', 'email_pattern' => '{first}.{last}', 'email_domain' => 'lepetitjournal.com'],
            ['slug' => 'le-petit-journal-geneve','name' => 'Le Petit Journal — Genève',   'base_url' => 'https://lepetitjournal.com/geneve',    'team_url' => 'https://lepetitjournal.com/geneve/equipe',              'category' => 'presse_expat', 'email_pattern' => '{first}.{last}', 'email_domain' => 'lepetitjournal.com'],
            ['slug' => 'le-petit-journal-singapour','name' => 'Le Petit Journal — Singapour','base_url' => 'https://lepetitjournal.com/singapour','team_url' => 'https://lepetitjournal.com/singapour/equipe',          'category' => 'presse_expat', 'email_pattern' => '{first}.{last}', 'email_domain' => 'lepetitjournal.com'],
            ['slug' => 'vivre-ailleurs',         'name' => 'Vivre Ailleurs',               'base_url' => 'https://www.vivreailleurs.fr',         'team_url' => 'https://www.vivreailleurs.fr/contact',                  'category' => 'presse_expat', 'email_pattern' => '{first}.{last}', 'email_domain' => 'vivreailleurs.fr'],
            ['slug' => 'expat-blog-editorial',   'name' => 'Expat Blog (editorial)',       'base_url' => 'https://www.expat-blog.com',           'team_url' => null,                                                    'category' => 'presse_expat', 'email_pattern' => '{first}.{last}', 'email_domain' => 'expat-blog.com'],
            ['slug' => 'destination-famille',    'name' => 'Destination Famille',          'base_url' => 'https://www.destination-famille.com',  'team_url' => 'https://www.destination-famille.com/equipe',            'category' => 'presse_expat', 'email_pattern' => '{first}.{last}', 'email_domain' => 'destination-famille.com'],

            // ── PRESSE AFRICAINE FRANCOPHONE ──────────────────────────────────
            ['slug' => 'rfi-afrique',            'name' => 'RFI Afrique',                  'base_url' => 'https://www.rfi.fr/fr/afrique',        'team_url' => null,                                                    'category' => 'radio_internationale', 'email_pattern' => '{first}.{last}', 'email_domain' => 'rfi.fr'],
            ['slug' => 'le-monde-afrique',       'name' => 'Le Monde Afrique',             'base_url' => 'https://www.lemonde.fr/afrique',       'team_url' => null,                                                    'category' => 'presse_expat', 'email_pattern' => '{first}.{last}', 'email_domain' => 'lemonde.fr'],
            ['slug' => 'tout-sur-la-france',     'name' => 'Tout Sur La France',           'base_url' => 'https://www.toutsurlafrancefr.com',    'team_url' => null,                                                    'category' => 'presse_expat', 'email_pattern' => null,             'email_domain' => 'toutsurlafrancefr.com'],

            // ── PRESSE ENTREPRENEURIAT (compléments) ──────────────────────────
            ['slug' => 'lexpansion-lexpress',    'name' => "L'Expansion (L'Express)",      'base_url' => 'https://lexpansion.lexpress.fr',       'team_url' => null,                                                    'category' => 'presse_entrepreneuriat', 'email_pattern' => '{first}.{last}', 'email_domain' => 'lexpress.fr'],
            ['slug' => 'harvard-business-review-fr','name' => 'HBR France',               'base_url' => 'https://www.hbrfrance.fr',             'team_url' => 'https://www.hbrfrance.fr/equipe',                       'category' => 'presse_entrepreneuriat', 'email_pattern' => '{first}.{last}', 'email_domain' => 'hbrfrance.fr'],
            ['slug' => 'bpifrance-le-hub',       'name' => 'Bpifrance Le Hub',             'base_url' => 'https://lehub.bpifrance.fr',           'team_url' => 'https://lehub.bpifrance.fr/equipe',                     'category' => 'presse_entrepreneuriat', 'email_pattern' => '{first}.{last}', 'email_domain' => 'bpifrance.fr'],
            ['slug' => 'les-echos-start',        'name' => 'Les Échos Start',              'base_url' => 'https://start.lesechos.fr',            'team_url' => null,                                                    'category' => 'presse_entrepreneuriat', 'email_pattern' => '{first}.{last}', 'email_domain' => 'lesechos.fr'],
            ['slug' => 'legalstart-blog',        'name' => 'Legalstart Blog',              'base_url' => 'https://www.legalstart.fr/fiches-pratiques', 'team_url' => null,                                              'category' => 'presse_juridique',      'email_pattern' => null,             'email_domain' => 'legalstart.fr'],

            // ── PRESSE VOYAGE (compléments) ───────────────────────────────────
            ['slug' => 'routard-voyager-autrement','name' => "L'Humanité Voyages",        'base_url' => 'https://www.voyager-autrement.com',    'team_url' => null,                                                    'category' => 'presse_voyage', 'email_pattern' => null, 'email_domain' => 'voyager-autrement.com'],
            ['slug' => 'voici-voyage',           'name' => 'Voici Magazine',               'base_url' => 'https://www.voici.fr',                 'team_url' => 'https://www.voici.fr/equipe',                           'category' => 'magazine_feminin', 'email_pattern' => '{first}.{last}', 'email_domain' => 'voici.fr'],
            ['slug' => 'espaces-naturels',       'name' => 'Espaces Naturels Magazine',    'base_url' => 'https://www.espaces-naturels.info',    'team_url' => null,                                                    'category' => 'presse_voyage', 'email_pattern' => null, 'email_domain' => 'espaces-naturels.info'],
            ['slug' => 'voyageons-autrement',    'name' => 'Voyageons-Autrement',          'base_url' => 'https://www.voyageons-autrement.com',  'team_url' => 'https://www.voyageons-autrement.com/equipe',            'category' => 'presse_voyage', 'email_pattern' => '{first}.{last}', 'email_domain' => 'voyageons-autrement.com'],

            // ── THE CONVERSATION FR ───────────────────────────────────────────
            ['slug' => 'the-conversation-fr-expat','name' => 'The Conversation (Société)',  'base_url' => 'https://theconversation.com/fr',     'team_url' => 'https://theconversation.com/fr/team',                   'category' => 'presse_nationale', 'email_pattern' => '{first}.{last}', 'email_domain' => 'theconversation.com'],

        ];

        $inserted = 0;
        foreach ($pubs as $pub) {
            if (in_array($pub['slug'], $existing)) continue;

            DB::table('press_publications')->insert([
                'slug'          => $pub['slug'],
                'name'          => $pub['name'],
                'base_url'      => $pub['base_url'],
                'team_url'      => $pub['team_url'] ?? null,
                'contact_url'   => null,
                'authors_url'   => null,
                'articles_url'  => null,
                'email_pattern' => $pub['email_pattern'] ?? null,
                'email_domain'  => $pub['email_domain'] ?? null,
                'media_type'    => 'online',
                'category'      => $pub['category'],
                'language'      => 'fr',
                'country'       => 'FR',
                'topics'        => '[]',
                'status'        => 'pending',
                'contacts_count' => 0,
                'authors_discovered' => 0,
                'emails_inferred'    => 0,
                'emails_verified'    => 0,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
            $inserted++;
        }

        \Illuminate\Support\Facades\Log::info("Expanded press_publications: +{$inserted} new publications");
    }

    public function down(): void
    {
        // Rollback: remove the slugs added by this migration
        $slugs = [
            'village-justice','lemonde-du-droit','dalloz-actualites','legavox','lexbase','doc-du-juriste',
            'actu-juridique','fiscalonline','rhinfo','decision-rh','people-management-fr','hrvoice',
            'cadre-dirigeant-mag','vocatis-mobilite','expatria','business-immo','le-moniteur',
            'figaro-immobilier','mieux-vivre-votre-argent','patrimoine-fr','boursorama-editorial',
            'topsante','sante-magazine','psychologies-magazine','allodocteurs','doctissimo',
            'elle-france','marie-claire-fr','femme-actuelle','cosmopolitan-fr','vogue-france','grazia-fr',
            'telerama','lesinrocks','paris-match','le-figaro-magazine','alternatives-economiques','society-magazine',
            'reporterre','novethic','basta-mag',
            'le-petit-journal-london','le-petit-journal-dubai','le-petit-journal-montreal',
            'le-petit-journal-geneve','le-petit-journal-singapour','vivre-ailleurs','expat-blog-editorial','destination-famille',
            'rfi-afrique','le-monde-afrique','tout-sur-la-france',
            'lexpansion-lexpress','harvard-business-review-fr','bpifrance-le-hub','les-echos-start','legalstart-blog',
            'routard-voyager-autrement','voici-voyage','espaces-naturels','voyageons-autrement',
            'the-conversation-fr-expat',
        ];
        DB::table('press_publications')->whereIn('slug', $slugs)->delete();
    }
};
