<?php

namespace App\Console\Commands;

use App\Models\PressPublication;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Import ALL francophone press publications worldwide.
 * Covers: Afrique de l'Ouest, Afrique Centrale, Maghreb, Océan Indien,
 * Moyen-Orient, Caraïbes, DOM-TOM, Belgique, Suisse, Luxembourg, Canada/Québec,
 * plus presse francophone en ligne et podcasts.
 *
 * Usage: php artisan press:import-francophone [--reset] [--scrape]
 */
class ImportFrancophoneWorldPress extends Command
{
    protected $signature   = 'press:import-francophone
                              {--reset : Truncate press_publications and reimport}
                              {--scrape : Launch scraping after import}';
    protected $description = 'Import 400+ francophone press publications from every French-speaking country worldwide';

    private array $publications = [

        // ══════════════════════════════════════════════════════════════════
        // FRANCE — PRESSE QUOTIDIENNE NATIONALE
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Le Monde', 'base_url' => 'https://www.lemonde.fr', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'France'],
        ['name' => 'Le Figaro', 'base_url' => 'https://www.lefigaro.fr', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business', 'expatriation'], 'country' => 'France'],
        ['name' => 'Libération', 'base_url' => 'https://www.liberation.fr', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'France'],
        ['name' => "L'Humanité", 'base_url' => 'https://www.humanite.fr', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'France'],
        ['name' => '20 Minutes', 'base_url' => 'https://www.20minutes.fr', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'France'],
        ['name' => 'Le Parisien', 'base_url' => 'https://www.leparisien.fr', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'France'],
        ['name' => 'La Croix', 'base_url' => 'https://www.la-croix.com', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'France'],
        ['name' => 'Les Échos', 'base_url' => 'https://www.lesechos.fr', 'media_type' => 'presse_ecrite', 'topics' => ['business', 'entrepreneuriat'], 'country' => 'France'],
        ['name' => 'Aujourd\'hui en France', 'base_url' => 'https://www.leparisien.fr/aujourd-hui-en-france', 'media_type' => 'presse_ecrite', 'topics' => ['international'], 'country' => 'France'],

        // ══════════════════════════════════════════════════════════════════
        // FRANCE — NEWSMAGAZINES & HEBDOMADAIRES
        // ══════════════════════════════════════════════════════════════════
        ['name' => "L'Obs", 'base_url' => 'https://www.nouvelobs.com', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'France'],
        ['name' => "L'Express", 'base_url' => 'https://www.lexpress.fr', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'France'],
        ['name' => 'Le Point', 'base_url' => 'https://www.lepoint.fr', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'France'],
        ['name' => 'Marianne', 'base_url' => 'https://www.marianne.net', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'France'],
        ['name' => 'Valeurs Actuelles', 'base_url' => 'https://www.valeursactuelles.com', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'France'],
        ['name' => 'Le Canard Enchaîné', 'base_url' => 'https://www.lecanardenchaine.fr', 'media_type' => 'presse_ecrite', 'topics' => ['international'], 'country' => 'France'],
        ['name' => 'Mediapart', 'base_url' => 'https://www.mediapart.fr', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'France'],
        ['name' => 'Politis', 'base_url' => 'https://www.politis.fr', 'media_type' => 'presse_ecrite', 'topics' => ['international'], 'country' => 'France'],
        ['name' => 'Courrier International', 'base_url' => 'https://www.courrierinternational.com', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'voyage'], 'country' => 'France'],
        ['name' => 'Télérama', 'base_url' => 'https://www.telerama.fr', 'media_type' => 'presse_ecrite', 'topics' => ['lifestyle', 'voyage'], 'country' => 'France'],
        ['name' => 'Le Journal du Dimanche', 'base_url' => 'https://www.lejdd.fr', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'France'],
        ['name' => 'Paris Match', 'base_url' => 'https://www.parismatch.com', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'lifestyle'], 'country' => 'France'],
        ['name' => 'Society Magazine', 'base_url' => 'https://www.society-magazine.fr', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'lifestyle'], 'country' => 'France'],
        ['name' => 'Charlie Hebdo', 'base_url' => 'https://charliehebdo.fr', 'media_type' => 'presse_ecrite', 'topics' => ['international'], 'country' => 'France'],
        ['name' => 'Alternatives Économiques', 'base_url' => 'https://www.alternatives-economiques.fr', 'media_type' => 'presse_ecrite', 'topics' => ['business', 'international'], 'country' => 'France'],

        // ══════════════════════════════════════════════════════════════════
        // FRANCE — PRESSE ÉCONOMIQUE & BUSINESS
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Capital', 'base_url' => 'https://www.capital.fr', 'media_type' => 'presse_ecrite', 'topics' => ['business', 'entrepreneuriat'], 'country' => 'France'],
        ['name' => 'Challenges', 'base_url' => 'https://www.challenges.fr', 'media_type' => 'presse_ecrite', 'topics' => ['business', 'entrepreneuriat'], 'country' => 'France'],
        ['name' => 'Forbes France', 'base_url' => 'https://www.forbes.fr', 'media_type' => 'web', 'topics' => ['business', 'entrepreneuriat'], 'country' => 'France'],
        ['name' => 'La Tribune', 'base_url' => 'https://www.latribune.fr', 'media_type' => 'web', 'topics' => ['business', 'entrepreneuriat'], 'country' => 'France'],
        ['name' => "L'Usine Nouvelle", 'base_url' => 'https://www.usinenouvelle.com', 'media_type' => 'presse_ecrite', 'topics' => ['business', 'tech'], 'country' => 'France'],
        ['name' => "Chef d'Entreprise", 'base_url' => 'https://www.chefdentreprise.com', 'media_type' => 'web', 'topics' => ['entrepreneuriat', 'business'], 'country' => 'France'],
        ['name' => 'Management Magazine', 'base_url' => 'https://www.management.fr', 'media_type' => 'presse_ecrite', 'topics' => ['business', 'entrepreneuriat'], 'country' => 'France'],
        ['name' => 'Le Revenu', 'base_url' => 'https://www.lerevenu.com', 'media_type' => 'web', 'topics' => ['business'], 'country' => 'France'],
        ['name' => 'Investir Les Échos', 'base_url' => 'https://investir.lesechos.fr', 'media_type' => 'web', 'topics' => ['business'], 'country' => 'France'],
        ['name' => 'Le Journal des Entreprises', 'base_url' => 'https://www.lejournaldesentreprises.com', 'media_type' => 'web', 'topics' => ['business', 'entrepreneuriat'], 'country' => 'France'],
        ['name' => 'LSA Conso', 'base_url' => 'https://www.lsa-conso.fr', 'media_type' => 'presse_ecrite', 'topics' => ['business'], 'country' => 'France'],
        ['name' => 'CB News', 'base_url' => 'https://www.cbnews.fr', 'media_type' => 'web', 'topics' => ['business', 'tech'], 'country' => 'France'],
        ['name' => 'Influencia', 'base_url' => 'https://www.influencia.net', 'media_type' => 'web', 'topics' => ['business', 'tech'], 'country' => 'France'],
        ['name' => 'Stratégies', 'base_url' => 'https://www.strategies.fr', 'media_type' => 'web', 'topics' => ['business', 'entrepreneuriat'], 'country' => 'France'],
        ['name' => 'Le Journal du Net', 'base_url' => 'https://www.journaldunet.com', 'media_type' => 'web', 'topics' => ['tech', 'business'], 'country' => 'France'],
        ['name' => 'L\'Agefi', 'base_url' => 'https://www.agefi.fr', 'media_type' => 'presse_ecrite', 'topics' => ['business'], 'country' => 'France'],
        ['name' => 'Option Finance', 'base_url' => 'https://www.optionfinance.fr', 'media_type' => 'web', 'topics' => ['business'], 'country' => 'France'],
        ['name' => 'Mieux Vivre Votre Argent', 'base_url' => 'https://www.mieuxvivre-votreargent.fr', 'media_type' => 'web', 'topics' => ['business'], 'country' => 'France'],
        ['name' => 'BFM Patrimoine', 'base_url' => 'https://www.patrimoine.bfmtv.com', 'media_type' => 'web', 'topics' => ['business'], 'country' => 'France'],
        ['name' => 'Cafedupatrimoine', 'base_url' => 'https://www.cafedupatrimoine.com', 'media_type' => 'web', 'topics' => ['business'], 'country' => 'France'],
        ['name' => 'MoneyVox', 'base_url' => 'https://www.moneyvox.fr', 'media_type' => 'web', 'topics' => ['business'], 'country' => 'France'],
        ['name' => 'Boursorama Mag', 'base_url' => 'https://www.boursorama.com/patrimoine', 'media_type' => 'web', 'topics' => ['business'], 'country' => 'France'],
        ['name' => 'ToutSurMesFinances', 'base_url' => 'https://www.toutsurmesfigurances.com', 'media_type' => 'web', 'topics' => ['business'], 'country' => 'France'],

        // ══════════════════════════════════════════════════════════════════
        // FRANCE — TECH & STARTUPS
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Frenchweb', 'base_url' => 'https://www.frenchweb.fr', 'media_type' => 'web', 'topics' => ['tech', 'entrepreneuriat'], 'country' => 'France'],
        ['name' => 'Maddyness', 'base_url' => 'https://www.maddyness.com', 'media_type' => 'web', 'topics' => ['entrepreneuriat', 'tech'], 'country' => 'France'],
        ['name' => 'Siècle Digital', 'base_url' => 'https://siecledigital.fr', 'media_type' => 'web', 'topics' => ['tech', 'entrepreneuriat'], 'country' => 'France'],
        ['name' => 'Presse-Citron', 'base_url' => 'https://www.presse-citron.net', 'media_type' => 'web', 'topics' => ['tech'], 'country' => 'France'],
        ['name' => 'Numerama', 'base_url' => 'https://www.numerama.com', 'media_type' => 'web', 'topics' => ['tech'], 'country' => 'France'],
        ['name' => '01net', 'base_url' => 'https://www.01net.com', 'media_type' => 'web', 'topics' => ['tech'], 'country' => 'France'],
        ['name' => 'Clubic', 'base_url' => 'https://www.clubic.com', 'media_type' => 'web', 'topics' => ['tech'], 'country' => 'France'],
        ['name' => 'BPI France Le Hub', 'base_url' => 'https://lehub.bpifrance.fr', 'media_type' => 'web', 'topics' => ['entrepreneuriat', 'business'], 'country' => 'France'],
        ['name' => 'WeDemain', 'base_url' => 'https://www.wedemain.fr', 'media_type' => 'web', 'topics' => ['entrepreneuriat', 'international'], 'country' => 'France'],
        ['name' => 'Futura Sciences', 'base_url' => 'https://www.futura-sciences.com', 'media_type' => 'web', 'topics' => ['tech'], 'country' => 'France'],
        ['name' => 'Next INpact', 'base_url' => 'https://next.ink', 'media_type' => 'web', 'topics' => ['tech'], 'country' => 'France'],
        ['name' => 'ZDNet France', 'base_url' => 'https://www.zdnet.fr', 'media_type' => 'web', 'topics' => ['tech', 'business'], 'country' => 'France'],
        ['name' => 'Le Monde Informatique', 'base_url' => 'https://www.lemondeinformatique.fr', 'media_type' => 'web', 'topics' => ['tech', 'business'], 'country' => 'France'],
        ['name' => 'Silicon.fr', 'base_url' => 'https://www.silicon.fr', 'media_type' => 'web', 'topics' => ['tech', 'business'], 'country' => 'France'],
        ['name' => 'L\'Informaticien', 'base_url' => 'https://www.linformaticien.com', 'media_type' => 'web', 'topics' => ['tech'], 'country' => 'France'],
        ['name' => 'Le Big Data', 'base_url' => 'https://www.lebigdata.fr', 'media_type' => 'web', 'topics' => ['tech'], 'country' => 'France'],
        ['name' => 'Frandroid', 'base_url' => 'https://www.frandroid.com', 'media_type' => 'web', 'topics' => ['tech'], 'country' => 'France'],
        ['name' => 'Les Numériques', 'base_url' => 'https://www.lesnumeriques.com', 'media_type' => 'web', 'topics' => ['tech'], 'country' => 'France'],
        ['name' => 'iPhon.fr', 'base_url' => 'https://www.iphon.fr', 'media_type' => 'web', 'topics' => ['tech'], 'country' => 'France'],
        ['name' => 'MacGeneration', 'base_url' => 'https://www.macgeneration.com', 'media_type' => 'web', 'topics' => ['tech'], 'country' => 'France'],
        ['name' => 'Archimag', 'base_url' => 'https://www.archimag.com', 'media_type' => 'web', 'topics' => ['tech', 'business'], 'country' => 'France'],
        ['name' => 'Usbek & Rica', 'base_url' => 'https://usbeketrica.com', 'media_type' => 'web', 'topics' => ['tech', 'lifestyle'], 'country' => 'France'],
        ['name' => 'La French Tech', 'base_url' => 'https://lafrenchtech.com', 'media_type' => 'web', 'topics' => ['entrepreneuriat', 'tech'], 'country' => 'France'],
        ['name' => 'French Tech Journal', 'base_url' => 'https://frenchtechjournal.com', 'media_type' => 'web', 'topics' => ['entrepreneuriat', 'tech'], 'country' => 'France'],
        ['name' => 'Welcome to the Jungle', 'base_url' => 'https://www.welcometothejungle.com', 'media_type' => 'web', 'topics' => ['entrepreneuriat', 'lifestyle'], 'country' => 'France'],

        // ══════════════════════════════════════════════════════════════════
        // FRANCE — PRESSE RÉGIONALE (PQR — 60+ TITRES)
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Ouest-France', 'base_url' => 'https://www.ouest-france.fr', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'France'],
        ['name' => 'Le Télégramme', 'base_url' => 'https://www.letelegramme.fr', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'France'],
        ['name' => 'La Voix du Nord', 'base_url' => 'https://www.lavoixdunord.fr', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'France'],
        ['name' => 'Sud Ouest', 'base_url' => 'https://www.sudouest.fr', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'France'],
        ['name' => 'Le Progrès', 'base_url' => 'https://www.leprogres.fr', 'media_type' => 'presse_ecrite', 'topics' => ['business'], 'country' => 'France'],
        ['name' => 'La Dépêche du Midi', 'base_url' => 'https://www.ladepeche.fr', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'France'],
        ['name' => 'La Montagne', 'base_url' => 'https://www.lamontagne.fr', 'media_type' => 'presse_ecrite', 'topics' => ['business'], 'country' => 'France'],
        ['name' => "L'Est Républicain", 'base_url' => 'https://www.estrepublicain.fr', 'media_type' => 'presse_ecrite', 'topics' => ['business'], 'country' => 'France'],
        ['name' => 'Le Dauphiné Libéré', 'base_url' => 'https://www.ledauphine.com', 'media_type' => 'presse_ecrite', 'topics' => ['business', 'voyage'], 'country' => 'France'],
        ['name' => 'Nice-Matin', 'base_url' => 'https://www.nicematin.com', 'media_type' => 'presse_ecrite', 'topics' => ['voyage', 'international'], 'country' => 'France'],
        ['name' => 'Var-Matin', 'base_url' => 'https://www.varmatin.com', 'media_type' => 'presse_ecrite', 'topics' => ['voyage'], 'country' => 'France'],
        ['name' => 'La Provence', 'base_url' => 'https://www.laprovence.com', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'France'],
        ['name' => 'Midi Libre', 'base_url' => 'https://www.midilibre.fr', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'France'],
        ['name' => 'L\'Indépendant', 'base_url' => 'https://www.lindependant.fr', 'media_type' => 'presse_ecrite', 'topics' => ['business'], 'country' => 'France'],
        ['name' => 'Centre Presse', 'base_url' => 'https://www.centrepresseaveyron.fr', 'media_type' => 'presse_ecrite', 'topics' => ['business'], 'country' => 'France'],
        ['name' => 'Le Populaire du Centre', 'base_url' => 'https://www.lepopulaire.fr', 'media_type' => 'presse_ecrite', 'topics' => ['business'], 'country' => 'France'],
        ['name' => 'La Nouvelle République', 'base_url' => 'https://www.lanouvellerepublique.fr', 'media_type' => 'presse_ecrite', 'topics' => ['business'], 'country' => 'France'],
        ['name' => 'Le Berry Républicain', 'base_url' => 'https://www.leberry.fr', 'media_type' => 'presse_ecrite', 'topics' => ['business'], 'country' => 'France'],
        ['name' => 'Le Journal du Centre', 'base_url' => 'https://www.lejdc.fr', 'media_type' => 'presse_ecrite', 'topics' => ['business'], 'country' => 'France'],
        ['name' => 'L\'Yonne Républicaine', 'base_url' => 'https://www.lyonne.fr', 'media_type' => 'presse_ecrite', 'topics' => ['business'], 'country' => 'France'],
        ['name' => 'Le Républicain Lorrain', 'base_url' => 'https://www.republicain-lorrain.fr', 'media_type' => 'presse_ecrite', 'topics' => ['business'], 'country' => 'France'],
        ['name' => 'Vosges Matin', 'base_url' => 'https://www.vosgesmatin.fr', 'media_type' => 'presse_ecrite', 'topics' => ['business'], 'country' => 'France'],
        ['name' => 'L\'Alsace', 'base_url' => 'https://www.lalsace.fr', 'media_type' => 'presse_ecrite', 'topics' => ['business', 'international'], 'country' => 'France'],
        ['name' => 'Les Dernières Nouvelles d\'Alsace', 'base_url' => 'https://www.dna.fr', 'media_type' => 'presse_ecrite', 'topics' => ['business', 'international'], 'country' => 'France'],
        ['name' => 'Le Bien Public', 'base_url' => 'https://www.bienpublic.com', 'media_type' => 'presse_ecrite', 'topics' => ['business'], 'country' => 'France'],
        ['name' => 'Le Journal de Saône-et-Loire', 'base_url' => 'https://www.lejsl.com', 'media_type' => 'presse_ecrite', 'topics' => ['business'], 'country' => 'France'],
        ['name' => 'Paris-Normandie', 'base_url' => 'https://www.paris-normandie.fr', 'media_type' => 'presse_ecrite', 'topics' => ['business'], 'country' => 'France'],
        ['name' => 'Le Courrier de l\'Ouest', 'base_url' => 'https://www.courrierdelouest.fr', 'media_type' => 'presse_ecrite', 'topics' => ['business'], 'country' => 'France'],
        ['name' => 'Le Maine Libre', 'base_url' => 'https://www.lemainelibre.fr', 'media_type' => 'presse_ecrite', 'topics' => ['business'], 'country' => 'France'],
        ['name' => 'Presse Océan', 'base_url' => 'https://www.presseocean.fr', 'media_type' => 'presse_ecrite', 'topics' => ['business'], 'country' => 'France'],
        ['name' => 'La Charente Libre', 'base_url' => 'https://www.charentelibre.fr', 'media_type' => 'presse_ecrite', 'topics' => ['business'], 'country' => 'France'],
        ['name' => 'Le Courrier Picard', 'base_url' => 'https://www.courrier-picard.fr', 'media_type' => 'presse_ecrite', 'topics' => ['business'], 'country' => 'France'],
        ['name' => "L'Union (Reims)", 'base_url' => 'https://www.lunion.fr', 'media_type' => 'presse_ecrite', 'topics' => ['business'], 'country' => 'France'],
        ['name' => "L'Ardennais", 'base_url' => 'https://www.lardennais.fr', 'media_type' => 'presse_ecrite', 'topics' => ['business'], 'country' => 'France'],
        ['name' => "L'Est Éclair", 'base_url' => 'https://www.lest-eclair.fr', 'media_type' => 'presse_ecrite', 'topics' => ['business'], 'country' => 'France'],
        ['name' => 'La Manche Libre', 'base_url' => 'https://www.lamanchelibre.fr', 'media_type' => 'presse_ecrite', 'topics' => ['business'], 'country' => 'France'],
        ['name' => 'Le Courrier Cauchois', 'base_url' => 'https://www.lecourriercauchois.fr', 'media_type' => 'presse_ecrite', 'topics' => ['business'], 'country' => 'France'],
        ['name' => 'La Marseillaise', 'base_url' => 'https://www.lamarseillaise.fr', 'media_type' => 'presse_ecrite', 'topics' => ['business'], 'country' => 'France'],
        ['name' => 'Corse Matin', 'base_url' => 'https://www.corsematin.com', 'media_type' => 'presse_ecrite', 'topics' => ['voyage', 'business'], 'country' => 'France'],
        ['name' => 'L\'Écho Républicain', 'base_url' => 'https://www.lechorepublicain.fr', 'media_type' => 'presse_ecrite', 'topics' => ['business'], 'country' => 'France'],

        // ══════════════════════════════════════════════════════════════════
        // FRANCE — TV & CHAÎNES D'INFO
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'France 24', 'base_url' => 'https://www.france24.com/fr', 'media_type' => 'tv', 'topics' => ['international', 'business', 'expatriation'], 'country' => 'France'],
        ['name' => 'BFM TV', 'base_url' => 'https://www.bfmtv.com', 'media_type' => 'tv', 'topics' => ['international', 'business'], 'country' => 'France'],
        ['name' => 'BFM Business', 'base_url' => 'https://bfmbusiness.bfmtv.com', 'media_type' => 'tv', 'topics' => ['business', 'entrepreneuriat'], 'country' => 'France'],
        ['name' => 'LCI', 'base_url' => 'https://www.lci.fr', 'media_type' => 'tv', 'topics' => ['international', 'business'], 'country' => 'France'],
        ['name' => 'CNews', 'base_url' => 'https://www.cnews.fr', 'media_type' => 'tv', 'topics' => ['international', 'business'], 'country' => 'France'],
        ['name' => 'franceinfo TV', 'base_url' => 'https://www.francetvinfo.fr', 'media_type' => 'tv', 'topics' => ['international', 'business'], 'country' => 'France'],
        ['name' => 'TV5 Monde', 'base_url' => 'https://www.tv5monde.com', 'media_type' => 'tv', 'topics' => ['international', 'voyage', 'expatriation'], 'country' => 'France'],
        ['name' => 'Arte', 'base_url' => 'https://www.arte.tv/fr', 'media_type' => 'tv', 'topics' => ['international', 'voyage', 'lifestyle'], 'country' => 'France'],
        ['name' => 'France 2', 'base_url' => 'https://www.france.tv/france-2', 'media_type' => 'tv', 'topics' => ['international', 'voyage'], 'country' => 'France'],
        ['name' => 'France 3', 'base_url' => 'https://www.france.tv/france-3', 'media_type' => 'tv', 'topics' => ['international'], 'country' => 'France'],
        ['name' => 'France 5', 'base_url' => 'https://www.france.tv/france-5', 'media_type' => 'tv', 'topics' => ['voyage', 'lifestyle'], 'country' => 'France'],
        ['name' => 'TF1 Info', 'base_url' => 'https://www.tf1info.fr', 'media_type' => 'tv', 'topics' => ['international', 'business'], 'country' => 'France'],
        ['name' => 'M6 Info', 'base_url' => 'https://www.6play.fr/m6', 'media_type' => 'tv', 'topics' => ['international', 'lifestyle'], 'country' => 'France'],
        ['name' => 'Public Sénat', 'base_url' => 'https://www.publicsenat.fr', 'media_type' => 'tv', 'topics' => ['international', 'business'], 'country' => 'France'],
        ['name' => 'LCP', 'base_url' => 'https://www.lcp.fr', 'media_type' => 'tv', 'topics' => ['international', 'business'], 'country' => 'France'],

        // ══════════════════════════════════════════════════════════════════
        // FRANCE — RADIO
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'RFI', 'base_url' => 'https://www.rfi.fr', 'media_type' => 'radio', 'topics' => ['international', 'business', 'expatriation'], 'country' => 'France'],
        ['name' => 'France Inter', 'base_url' => 'https://www.radiofrance.fr/franceinter', 'media_type' => 'radio', 'topics' => ['international', 'business'], 'country' => 'France'],
        ['name' => 'France Culture', 'base_url' => 'https://www.radiofrance.fr/franceculture', 'media_type' => 'radio', 'topics' => ['international', 'lifestyle'], 'country' => 'France'],
        ['name' => 'France Info Radio', 'base_url' => 'https://www.radiofrance.fr/franceinfo', 'media_type' => 'radio', 'topics' => ['international', 'business'], 'country' => 'France'],
        ['name' => 'Europe 1', 'base_url' => 'https://www.europe1.fr', 'media_type' => 'radio', 'topics' => ['business', 'international'], 'country' => 'France'],
        ['name' => 'RTL', 'base_url' => 'https://www.rtl.fr', 'media_type' => 'radio', 'topics' => ['business', 'international'], 'country' => 'France'],
        ['name' => 'RMC', 'base_url' => 'https://rmc.bfmtv.com', 'media_type' => 'radio', 'topics' => ['business', 'international'], 'country' => 'France'],
        ['name' => 'Sud Radio', 'base_url' => 'https://www.sudradio.fr', 'media_type' => 'radio', 'topics' => ['business'], 'country' => 'France'],
        ['name' => 'Radio Classique', 'base_url' => 'https://www.radioclassique.fr', 'media_type' => 'radio', 'topics' => ['business', 'lifestyle'], 'country' => 'France'],
        ['name' => 'France Bleu', 'base_url' => 'https://www.francebleu.fr', 'media_type' => 'radio', 'topics' => ['international'], 'country' => 'France'],
        ['name' => 'Mouv\'', 'base_url' => 'https://www.radiofrance.fr/mouv', 'media_type' => 'radio', 'topics' => ['lifestyle'], 'country' => 'France'],
        ['name' => 'Nova', 'base_url' => 'https://www.nova.fr', 'media_type' => 'radio', 'topics' => ['lifestyle'], 'country' => 'France'],

        // ══════════════════════════════════════════════════════════════════
        // FRANCE — PRESSE FÉMININE & LIFESTYLE
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'ELLE', 'base_url' => 'https://www.elle.fr', 'media_type' => 'presse_ecrite', 'topics' => ['lifestyle', 'voyage'], 'country' => 'France'],
        ['name' => 'Marie Claire', 'base_url' => 'https://www.marieclaire.fr', 'media_type' => 'presse_ecrite', 'topics' => ['lifestyle', 'voyage'], 'country' => 'France'],
        ['name' => 'Madame Figaro', 'base_url' => 'https://madame.lefigaro.fr', 'media_type' => 'presse_ecrite', 'topics' => ['lifestyle', 'voyage'], 'country' => 'France'],
        ['name' => 'Vogue France', 'base_url' => 'https://www.vogue.fr', 'media_type' => 'presse_ecrite', 'topics' => ['lifestyle', 'voyage'], 'country' => 'France'],
        ['name' => 'Vanity Fair France', 'base_url' => 'https://www.vanityfair.fr', 'media_type' => 'presse_ecrite', 'topics' => ['lifestyle', 'international'], 'country' => 'France'],
        ['name' => 'Grazia France', 'base_url' => 'https://www.grazia.fr', 'media_type' => 'presse_ecrite', 'topics' => ['lifestyle', 'voyage'], 'country' => 'France'],
        ['name' => 'Glamour France', 'base_url' => 'https://www.glamourparis.com', 'media_type' => 'web', 'topics' => ['lifestyle', 'voyage'], 'country' => 'France'],
        ['name' => 'Cosmopolitan France', 'base_url' => 'https://www.cosmopolitan.fr', 'media_type' => 'presse_ecrite', 'topics' => ['lifestyle', 'voyage'], 'country' => 'France'],
        ['name' => 'Femme Actuelle', 'base_url' => 'https://www.femmeactuelle.fr', 'media_type' => 'presse_ecrite', 'topics' => ['lifestyle', 'voyage'], 'country' => 'France'],
        ['name' => 'Gala', 'base_url' => 'https://www.gala.fr', 'media_type' => 'presse_ecrite', 'topics' => ['lifestyle'], 'country' => 'France'],
        ['name' => 'Voici', 'base_url' => 'https://www.voici.fr', 'media_type' => 'presse_ecrite', 'topics' => ['lifestyle'], 'country' => 'France'],
        ['name' => 'Closer', 'base_url' => 'https://www.closermag.fr', 'media_type' => 'presse_ecrite', 'topics' => ['lifestyle'], 'country' => 'France'],
        ['name' => 'Public Magazine', 'base_url' => 'https://www.public.fr', 'media_type' => 'presse_ecrite', 'topics' => ['lifestyle'], 'country' => 'France'],
        ['name' => 'Marie France', 'base_url' => 'https://www.mariefrance.fr', 'media_type' => 'web', 'topics' => ['lifestyle', 'voyage'], 'country' => 'France'],
        ['name' => 'Version Femina', 'base_url' => 'https://www.femina.fr', 'media_type' => 'presse_ecrite', 'topics' => ['lifestyle', 'voyage'], 'country' => 'France'],
        ['name' => 'Avantages', 'base_url' => 'https://www.avantages.fr', 'media_type' => 'presse_ecrite', 'topics' => ['lifestyle'], 'country' => 'France'],
        ['name' => 'Biba Magazine', 'base_url' => 'https://www.bibamagazine.fr', 'media_type' => 'presse_ecrite', 'topics' => ['lifestyle', 'voyage'], 'country' => 'France'],
        ['name' => 'Stylist France', 'base_url' => 'https://www.stylist.fr', 'media_type' => 'web', 'topics' => ['lifestyle'], 'country' => 'France'],
        ['name' => 'GQ France', 'base_url' => 'https://www.gqmagazine.fr', 'media_type' => 'presse_ecrite', 'topics' => ['lifestyle', 'voyage'], 'country' => 'France'],
        ['name' => 'Esquire France', 'base_url' => 'https://www.esquire.fr', 'media_type' => 'web', 'topics' => ['lifestyle'], 'country' => 'France'],
        ['name' => 'L\'Officiel', 'base_url' => 'https://www.lofficiel.com', 'media_type' => 'presse_ecrite', 'topics' => ['lifestyle', 'voyage'], 'country' => 'France'],
        ['name' => 'Numéro Magazine', 'base_url' => 'https://www.numero.com', 'media_type' => 'presse_ecrite', 'topics' => ['lifestyle'], 'country' => 'France'],
        ['name' => 'AD Magazine', 'base_url' => 'https://www.admagazine.fr', 'media_type' => 'presse_ecrite', 'topics' => ['lifestyle', 'voyage'], 'country' => 'France'],
        ['name' => 'The Good Life France', 'base_url' => 'https://www.thegoodlife.fr', 'media_type' => 'web', 'topics' => ['lifestyle', 'voyage'], 'country' => 'France'],
        ['name' => 'NEON Magazine', 'base_url' => 'https://www.neonmag.fr', 'media_type' => 'web', 'topics' => ['lifestyle', 'voyage'], 'country' => 'France'],
        ['name' => 'Konbini', 'base_url' => 'https://www.konbini.com', 'media_type' => 'web', 'topics' => ['lifestyle'], 'country' => 'France'],
        ['name' => 'Brut', 'base_url' => 'https://www.brut.media/fr', 'media_type' => 'web', 'topics' => ['international', 'lifestyle'], 'country' => 'France'],
        ['name' => 'Slate FR', 'base_url' => 'https://www.slate.fr', 'media_type' => 'web', 'topics' => ['international', 'lifestyle'], 'country' => 'France'],
        ['name' => 'Vice France', 'base_url' => 'https://www.vice.com/fr', 'media_type' => 'web', 'topics' => ['lifestyle', 'international'], 'country' => 'France'],
        ['name' => 'Madmoizelle', 'base_url' => 'https://www.madmoizelle.com', 'media_type' => 'web', 'topics' => ['lifestyle'], 'country' => 'France'],
        ['name' => 'Les Inrockuptibles', 'base_url' => 'https://www.lesinrocks.com', 'media_type' => 'presse_ecrite', 'topics' => ['lifestyle', 'international'], 'country' => 'France'],
        ['name' => 'Technikart', 'base_url' => 'https://www.technikart.com', 'media_type' => 'presse_ecrite', 'topics' => ['lifestyle'], 'country' => 'France'],

        // ══════════════════════════════════════════════════════════════════
        // FRANCE — VOYAGE & TOURISME
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'GEO Magazine', 'base_url' => 'https://www.geo.fr', 'media_type' => 'presse_ecrite', 'topics' => ['voyage', 'international'], 'country' => 'France'],
        ['name' => 'National Geographic France', 'base_url' => 'https://www.nationalgeographic.fr', 'media_type' => 'presse_ecrite', 'topics' => ['voyage', 'international'], 'country' => 'France'],
        ['name' => 'Le Routard', 'base_url' => 'https://www.routard.com', 'media_type' => 'web', 'topics' => ['voyage', 'expatriation'], 'country' => 'France'],
        ['name' => 'Petit Futé', 'base_url' => 'https://www.petitfute.com', 'media_type' => 'presse_ecrite', 'topics' => ['voyage', 'expatriation'], 'country' => 'France'],
        ['name' => "L'Écho Touristique", 'base_url' => 'https://www.lechotouristique.com', 'media_type' => 'web', 'topics' => ['voyage', 'business'], 'country' => 'France'],
        ['name' => 'Partir Magazine', 'base_url' => 'https://www.partir.com', 'media_type' => 'presse_ecrite', 'topics' => ['voyage'], 'country' => 'France'],
        ['name' => 'Tourmag', 'base_url' => 'https://www.tourmag.com', 'media_type' => 'web', 'topics' => ['voyage', 'business'], 'country' => 'France'],
        ['name' => 'Quotidien du Tourisme', 'base_url' => 'https://www.quotidiendutourisme.com', 'media_type' => 'web', 'topics' => ['voyage', 'business'], 'country' => 'France'],
        ['name' => 'Easyvoyage', 'base_url' => 'https://www.easyvoyage.com', 'media_type' => 'web', 'topics' => ['voyage'], 'country' => 'France'],
        ['name' => 'Lonely Planet France', 'base_url' => 'https://www.lonelyplanet.fr', 'media_type' => 'web', 'topics' => ['voyage'], 'country' => 'France'],
        ['name' => 'Voyageons Autrement', 'base_url' => 'https://www.voyageons-autrement.com', 'media_type' => 'web', 'topics' => ['voyage'], 'country' => 'France'],
        ['name' => 'A/R Magazine', 'base_url' => 'https://www.ar-magazine.com', 'media_type' => 'presse_ecrite', 'topics' => ['voyage'], 'country' => 'France'],
        ['name' => 'Trek Magazine', 'base_url' => 'https://www.trekmag.com', 'media_type' => 'presse_ecrite', 'topics' => ['voyage'], 'country' => 'France'],
        ['name' => 'Terre Sauvage', 'base_url' => 'https://www.terresauvage.com', 'media_type' => 'presse_ecrite', 'topics' => ['voyage'], 'country' => 'France'],
        ['name' => 'Détours en France', 'base_url' => 'https://www.detoursenfrance.fr', 'media_type' => 'presse_ecrite', 'topics' => ['voyage'], 'country' => 'France'],
        ['name' => 'Grands Reportages', 'base_url' => 'https://www.grandsreportages.com', 'media_type' => 'presse_ecrite', 'topics' => ['voyage'], 'country' => 'France'],
        ['name' => 'Voyages d\'Affaires', 'base_url' => 'https://www.voyages-d-affaires.com', 'media_type' => 'web', 'topics' => ['voyage', 'business'], 'country' => 'France'],
        ['name' => 'Travel On Move', 'base_url' => 'https://www.travelonmove.com', 'media_type' => 'web', 'topics' => ['voyage'], 'country' => 'France'],
        ['name' => 'TravellerMag FR', 'base_url' => 'https://www.travellermag.fr', 'media_type' => 'web', 'topics' => ['voyage', 'lifestyle'], 'country' => 'France'],

        // ══════════════════════════════════════════════════════════════════
        // FRANCE — EXPATRIATION & FRANÇAIS À L'ÉTRANGER
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Le Petit Journal', 'base_url' => 'https://lepetitjournal.com', 'media_type' => 'web', 'topics' => ['expatriation', 'international'], 'country' => 'France'],
        ['name' => "Français à l'Étranger", 'base_url' => 'https://www.francaisaletranger.fr', 'media_type' => 'web', 'topics' => ['expatriation', 'international'], 'country' => 'France'],
        ['name' => 'FemmExpat', 'base_url' => 'https://www.femmexpat.com', 'media_type' => 'web', 'topics' => ['expatriation', 'lifestyle'], 'country' => 'France'],
        ['name' => 'Expat.com Magazine', 'base_url' => 'https://www.expat.com', 'media_type' => 'web', 'topics' => ['expatriation', 'international'], 'country' => 'France'],
        ['name' => 'Vivre à l\'étranger', 'base_url' => 'https://www.vivrealetranger.com', 'media_type' => 'web', 'topics' => ['expatriation', 'voyage'], 'country' => 'France'],
        ['name' => 'MFE (Maison des Français de l\'Étranger)', 'base_url' => 'https://mfe.org', 'media_type' => 'web', 'topics' => ['expatriation', 'international'], 'country' => 'France'],
        ['name' => 'InterNations FR', 'base_url' => 'https://www.internations.org/fr', 'media_type' => 'web', 'topics' => ['expatriation', 'international'], 'country' => 'France'],
        ['name' => 'MondeExpat', 'base_url' => 'https://www.mondeexpat.com', 'media_type' => 'web', 'topics' => ['expatriation', 'voyage'], 'country' => 'France'],
        ['name' => 'Nomade Digital', 'base_url' => 'https://www.nomadedigital.fr', 'media_type' => 'web', 'topics' => ['voyage', 'entrepreneuriat', 'expatriation'], 'country' => 'France'],

        // ══════════════════════════════════════════════════════════════════
        // FRANCE — PRESSE JURIDIQUE
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Le Monde du Droit', 'base_url' => 'https://www.lemondedudroit.fr', 'media_type' => 'web', 'topics' => ['business', 'juridique'], 'country' => 'France'],
        ['name' => 'Dalloz Actualité', 'base_url' => 'https://www.dalloz-actualite.fr', 'media_type' => 'web', 'topics' => ['juridique'], 'country' => 'France'],
        ['name' => 'Village de la Justice', 'base_url' => 'https://www.village-justice.com', 'media_type' => 'web', 'topics' => ['juridique', 'business'], 'country' => 'France'],
        ['name' => 'Actu-Juridique', 'base_url' => 'https://www.actu-juridique.fr', 'media_type' => 'web', 'topics' => ['juridique'], 'country' => 'France'],
        ['name' => 'Le Petit Juriste', 'base_url' => 'https://www.lepetitjuriste.fr', 'media_type' => 'web', 'topics' => ['juridique', 'business'], 'country' => 'France'],
        ['name' => 'Juritravail', 'base_url' => 'https://www.juritravail.com', 'media_type' => 'web', 'topics' => ['juridique', 'business'], 'country' => 'France'],
        ['name' => 'Gazette du Palais', 'base_url' => 'https://www.gazettedupalais.com', 'media_type' => 'presse_ecrite', 'topics' => ['juridique'], 'country' => 'France'],
        ['name' => 'LexisNexis Actualités', 'base_url' => 'https://www.lexisnexis.fr', 'media_type' => 'web', 'topics' => ['juridique', 'business'], 'country' => 'France'],
        ['name' => 'Recueil Dalloz', 'base_url' => 'https://www.dalloz.fr', 'media_type' => 'presse_ecrite', 'topics' => ['juridique'], 'country' => 'France'],
        ['name' => 'Décideurs Magazine', 'base_url' => 'https://www.magazine-decideurs.com', 'media_type' => 'web', 'topics' => ['juridique', 'business'], 'country' => 'France'],
        ['name' => 'Affiches Parisiennes', 'base_url' => 'https://www.affiches-parisiennes.com', 'media_type' => 'presse_ecrite', 'topics' => ['juridique', 'business'], 'country' => 'France'],

        // ══════════════════════════════════════════════════════════════════
        // FRANCE — IMMOBILIER & PATRIMOINE
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Le Particulier', 'base_url' => 'https://leparticulier.lefigaro.fr', 'media_type' => 'web', 'topics' => ['business'], 'country' => 'France'],
        ['name' => 'Le Journal de l\'Agence', 'base_url' => 'https://www.journaldelagence.com', 'media_type' => 'web', 'topics' => ['business'], 'country' => 'France'],
        ['name' => 'Mysweetimmo', 'base_url' => 'https://www.mysweetimmo.com', 'media_type' => 'web', 'topics' => ['business'], 'country' => 'France'],
        ['name' => 'ImmoMatin', 'base_url' => 'https://www.immomatin.com', 'media_type' => 'web', 'topics' => ['business'], 'country' => 'France'],
        ['name' => 'Journal de l\'Immobilier', 'base_url' => 'https://journaldelimmobilier.com', 'media_type' => 'web', 'topics' => ['business'], 'country' => 'France'],
        ['name' => 'Le Moniteur', 'base_url' => 'https://www.lemoniteur.fr', 'media_type' => 'presse_ecrite', 'topics' => ['business'], 'country' => 'France'],
        ['name' => 'Batiactu', 'base_url' => 'https://www.batiactu.com', 'media_type' => 'web', 'topics' => ['business'], 'country' => 'France'],

        // ══════════════════════════════════════════════════════════════════
        // FRANCE — SANTÉ & BIEN-ÊTRE
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Top Santé', 'base_url' => 'https://www.topsante.com', 'media_type' => 'web', 'topics' => ['lifestyle'], 'country' => 'France'],
        ['name' => 'Doctissimo', 'base_url' => 'https://www.doctissimo.fr', 'media_type' => 'web', 'topics' => ['lifestyle'], 'country' => 'France'],
        ['name' => 'Psychologies Magazine', 'base_url' => 'https://www.psychologies.com', 'media_type' => 'presse_ecrite', 'topics' => ['lifestyle'], 'country' => 'France'],
        ['name' => 'Santé Magazine', 'base_url' => 'https://www.santemagazine.fr', 'media_type' => 'web', 'topics' => ['lifestyle'], 'country' => 'France'],
        ['name' => 'Femme Actuelle Santé', 'base_url' => 'https://www.femmeactuelle.fr/sante', 'media_type' => 'web', 'topics' => ['lifestyle'], 'country' => 'France'],
        ['name' => 'Pourquoi Docteur', 'base_url' => 'https://www.pourquoidocteur.fr', 'media_type' => 'web', 'topics' => ['lifestyle'], 'country' => 'France'],
        ['name' => 'Le Quotidien du Médecin', 'base_url' => 'https://www.lequotidiendumedecin.fr', 'media_type' => 'web', 'topics' => ['lifestyle', 'business'], 'country' => 'France'],
        ['name' => 'Egora', 'base_url' => 'https://www.egora.fr', 'media_type' => 'web', 'topics' => ['lifestyle', 'business'], 'country' => 'France'],
        ['name' => 'What\'s Up Doc', 'base_url' => 'https://www.whatsupdoc-lemag.fr', 'media_type' => 'web', 'topics' => ['lifestyle'], 'country' => 'France'],

        // ══════════════════════════════════════════════════════════════════
        // FRANCE — ÉCOLOGIE & ENVIRONNEMENT
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Reporterre', 'base_url' => 'https://reporterre.net', 'media_type' => 'web', 'topics' => ['international', 'lifestyle'], 'country' => 'France'],
        ['name' => 'Novethic', 'base_url' => 'https://www.novethic.fr', 'media_type' => 'web', 'topics' => ['business', 'international'], 'country' => 'France'],
        ['name' => 'Vert (Le Média)', 'base_url' => 'https://vert.eco', 'media_type' => 'web', 'topics' => ['international', 'lifestyle'], 'country' => 'France'],
        ['name' => 'Actu-Environnement', 'base_url' => 'https://www.actu-environnement.com', 'media_type' => 'web', 'topics' => ['business', 'international'], 'country' => 'France'],
        ['name' => 'Natura Sciences', 'base_url' => 'https://www.natura-sciences.com', 'media_type' => 'web', 'topics' => ['international'], 'country' => 'France'],
        ['name' => 'Bon Pote', 'base_url' => 'https://bonpote.com', 'media_type' => 'web', 'topics' => ['international'], 'country' => 'France'],
        ['name' => 'Socialter', 'base_url' => 'https://www.socialter.fr', 'media_type' => 'presse_ecrite', 'topics' => ['business', 'lifestyle'], 'country' => 'France'],

        // ══════════════════════════════════════════════════════════════════
        // FRANCE — GASTRONOMIE & FOOD
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Le Fooding', 'base_url' => 'https://lefooding.com', 'media_type' => 'web', 'topics' => ['lifestyle', 'voyage'], 'country' => 'France'],
        ['name' => 'Cuisine Actuelle', 'base_url' => 'https://www.cuisineactuelle.fr', 'media_type' => 'presse_ecrite', 'topics' => ['lifestyle'], 'country' => 'France'],
        ['name' => 'Régal', 'base_url' => 'https://www.rfrancegal.fr', 'media_type' => 'presse_ecrite', 'topics' => ['lifestyle', 'voyage'], 'country' => 'France'],
        ['name' => 'Fou de Cuisine', 'base_url' => 'https://www.foudecuisine.fr', 'media_type' => 'presse_ecrite', 'topics' => ['lifestyle'], 'country' => 'France'],
        ['name' => 'Atabula', 'base_url' => 'https://www.atabula.com', 'media_type' => 'web', 'topics' => ['lifestyle', 'business'], 'country' => 'France'],
        ['name' => 'Le Point Gastronomie', 'base_url' => 'https://www.lepoint.fr/gastronomie', 'media_type' => 'web', 'topics' => ['lifestyle', 'voyage'], 'country' => 'France'],

        // ══════════════════════════════════════════════════════════════════
        // FRANCE — ÉDUCATION & FORMATION
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'L\'Étudiant', 'base_url' => 'https://www.letudiant.fr', 'media_type' => 'web', 'topics' => ['business', 'international'], 'country' => 'France'],
        ['name' => 'Studyrama', 'base_url' => 'https://www.studyrama.com', 'media_type' => 'web', 'topics' => ['business', 'international'], 'country' => 'France'],
        ['name' => 'Le Monde Campus', 'base_url' => 'https://www.lemonde.fr/campus', 'media_type' => 'web', 'topics' => ['business', 'international'], 'country' => 'France'],
        ['name' => 'AEF Info', 'base_url' => 'https://www.aefinfo.fr', 'media_type' => 'web', 'topics' => ['business'], 'country' => 'France'],
        ['name' => 'EducPros', 'base_url' => 'https://www.letudiant.fr/educpros', 'media_type' => 'web', 'topics' => ['business'], 'country' => 'France'],
        ['name' => 'Courrier Cadres', 'base_url' => 'https://courriercadres.com', 'media_type' => 'web', 'topics' => ['business', 'entrepreneuriat'], 'country' => 'France'],
        ['name' => 'Cadremploi Mag', 'base_url' => 'https://www.cadremploi.fr/editorial', 'media_type' => 'web', 'topics' => ['business'], 'country' => 'France'],

        // ══════════════════════════════════════════════════════════════════
        // FRANCE — RH & MOBILITÉ INTERNATIONALE
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Liaisons Sociales', 'base_url' => 'https://www.liaisons-sociales.fr', 'media_type' => 'presse_ecrite', 'topics' => ['business'], 'country' => 'France'],
        ['name' => 'Entreprise & Carrières', 'base_url' => 'https://www.entreprise-carrieres.com', 'media_type' => 'web', 'topics' => ['business'], 'country' => 'France'],
        ['name' => 'Rebondir', 'base_url' => 'https://www.rebondir.fr', 'media_type' => 'web', 'topics' => ['business', 'expatriation'], 'country' => 'France'],
        ['name' => 'Exclusive RH', 'base_url' => 'https://www.exclusiverh.com', 'media_type' => 'web', 'topics' => ['business'], 'country' => 'France'],
        ['name' => 'Focus RH', 'base_url' => 'https://www.focusrh.com', 'media_type' => 'web', 'topics' => ['business'], 'country' => 'France'],
        ['name' => 'Parlons RH', 'base_url' => 'https://www.parlonsrh.com', 'media_type' => 'web', 'topics' => ['business'], 'country' => 'France'],
        ['name' => 'MyRHline', 'base_url' => 'https://myrhline.com', 'media_type' => 'web', 'topics' => ['business'], 'country' => 'France'],

        // ══════════════════════════════════════════════════════════════════
        // FRANCE — CULTURE & ARTS
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Beaux Arts Magazine', 'base_url' => 'https://www.beauxarts.com', 'media_type' => 'presse_ecrite', 'topics' => ['lifestyle', 'voyage'], 'country' => 'France'],
        ['name' => 'Connaissance des Arts', 'base_url' => 'https://www.connaissancedesarts.com', 'media_type' => 'presse_ecrite', 'topics' => ['lifestyle', 'voyage'], 'country' => 'France'],
        ['name' => 'Philosophie Magazine', 'base_url' => 'https://www.philomag.com', 'media_type' => 'presse_ecrite', 'topics' => ['lifestyle', 'international'], 'country' => 'France'],
        ['name' => 'Sciences Humaines', 'base_url' => 'https://www.scienceshumaines.com', 'media_type' => 'presse_ecrite', 'topics' => ['lifestyle', 'international'], 'country' => 'France'],
        ['name' => 'L\'Histoire', 'base_url' => 'https://www.lhistoire.fr', 'media_type' => 'presse_ecrite', 'topics' => ['international'], 'country' => 'France'],
        ['name' => 'Historia', 'base_url' => 'https://www.historia.fr', 'media_type' => 'presse_ecrite', 'topics' => ['international'], 'country' => 'France'],
        ['name' => 'Le Magazine Littéraire', 'base_url' => 'https://www.magazine-litteraire.com', 'media_type' => 'presse_ecrite', 'topics' => ['lifestyle'], 'country' => 'France'],
        ['name' => 'Première', 'base_url' => 'https://www.premiere.fr', 'media_type' => 'web', 'topics' => ['lifestyle'], 'country' => 'France'],
        ['name' => 'Allociné', 'base_url' => 'https://www.allocine.fr', 'media_type' => 'web', 'topics' => ['lifestyle'], 'country' => 'France'],
        ['name' => 'Les Cahiers du Cinéma', 'base_url' => 'https://www.cahiersducinema.com', 'media_type' => 'presse_ecrite', 'topics' => ['lifestyle'], 'country' => 'France'],

        // ══════════════════════════════════════════════════════════════════
        // FRANCE — SPORT (rubriques voyage/international)
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'L\'Équipe', 'base_url' => 'https://www.lequipe.fr', 'media_type' => 'presse_ecrite', 'topics' => ['lifestyle', 'international'], 'country' => 'France'],
        ['name' => 'So Foot', 'base_url' => 'https://www.sofoot.com', 'media_type' => 'web', 'topics' => ['lifestyle', 'international'], 'country' => 'France'],
        ['name' => 'RMC Sport', 'base_url' => 'https://rmcsport.bfmtv.com', 'media_type' => 'web', 'topics' => ['lifestyle'], 'country' => 'France'],

        // ══════════════════════════════════════════════════════════════════
        // FRANCE — LUXE & PREMIUM
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Le Figaro Madame Voyage', 'base_url' => 'https://madame.lefigaro.fr/voyages', 'media_type' => 'web', 'topics' => ['voyage', 'lifestyle'], 'country' => 'France'],
        ['name' => 'Journal du Luxe', 'base_url' => 'https://journalduluxe.fr', 'media_type' => 'web', 'topics' => ['business', 'lifestyle'], 'country' => 'France'],
        ['name' => 'Luxe Magazine', 'base_url' => 'https://www.luxe-magazine.com', 'media_type' => 'web', 'topics' => ['lifestyle', 'voyage'], 'country' => 'France'],
        ['name' => 'Ideat', 'base_url' => 'https://ideat.thegoodhub.com', 'media_type' => 'presse_ecrite', 'topics' => ['lifestyle', 'voyage'], 'country' => 'France'],
        ['name' => 'Côté Maison', 'base_url' => 'https://www.cotemaison.fr', 'media_type' => 'web', 'topics' => ['lifestyle'], 'country' => 'France'],
        ['name' => 'Maison & Travaux', 'base_url' => 'https://www.maison-travaux.fr', 'media_type' => 'presse_ecrite', 'topics' => ['lifestyle'], 'country' => 'France'],

        // ══════════════════════════════════════════════════════════════════
        // FRANCE — PARENTALITÉ & FAMILLE
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Parents Magazine', 'base_url' => 'https://www.parents.fr', 'media_type' => 'presse_ecrite', 'topics' => ['lifestyle', 'voyage'], 'country' => 'France'],
        ['name' => 'Enfant Magazine', 'base_url' => 'https://www.enfant.com', 'media_type' => 'web', 'topics' => ['lifestyle'], 'country' => 'France'],
        ['name' => 'MagicMaman', 'base_url' => 'https://www.magicmaman.com', 'media_type' => 'web', 'topics' => ['lifestyle', 'voyage'], 'country' => 'France'],
        ['name' => 'Famille Chrétienne', 'base_url' => 'https://www.famillechretienne.fr', 'media_type' => 'presse_ecrite', 'topics' => ['lifestyle', 'international'], 'country' => 'France'],

        // ══════════════════════════════════════════════════════════════════
        // FRANCE — SENIORS & RETRAITE
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Notre Temps', 'base_url' => 'https://www.notretemps.com', 'media_type' => 'presse_ecrite', 'topics' => ['lifestyle', 'voyage', 'expatriation'], 'country' => 'France'],
        ['name' => 'Pleine Vie', 'base_url' => 'https://www.pleinevie.fr', 'media_type' => 'presse_ecrite', 'topics' => ['lifestyle', 'voyage'], 'country' => 'France'],
        ['name' => 'Senior Actu', 'base_url' => 'https://www.senioractu.com', 'media_type' => 'web', 'topics' => ['lifestyle', 'voyage'], 'country' => 'France'],
        ['name' => 'Agevillage', 'base_url' => 'https://www.agevillage.com', 'media_type' => 'web', 'topics' => ['lifestyle'], 'country' => 'France'],

        // ══════════════════════════════════════════════════════════════════
        // FRANCE — PURE PLAYERS & PODCASTS
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Huffington Post FR', 'base_url' => 'https://www.huffingtonpost.fr', 'media_type' => 'web', 'topics' => ['international', 'lifestyle'], 'country' => 'France'],
        ['name' => 'Atlantico', 'base_url' => 'https://atlantico.fr', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'France'],
        ['name' => 'Agoravox', 'base_url' => 'https://www.agoravox.fr', 'media_type' => 'web', 'topics' => ['international'], 'country' => 'France'],
        ['name' => 'Rue89', 'base_url' => 'https://www.nouvelobs.com/rue89', 'media_type' => 'web', 'topics' => ['international', 'lifestyle'], 'country' => 'France'],
        ['name' => 'StreetPress', 'base_url' => 'https://www.streetpress.com', 'media_type' => 'web', 'topics' => ['international', 'lifestyle'], 'country' => 'France'],
        ['name' => 'Basta!', 'base_url' => 'https://basta.media', 'media_type' => 'web', 'topics' => ['international'], 'country' => 'France'],
        ['name' => 'QG Le Média', 'base_url' => 'https://qg.media', 'media_type' => 'web', 'topics' => ['international', 'lifestyle'], 'country' => 'France'],
        ['name' => 'Loopsider', 'base_url' => 'https://www.loopsider.com', 'media_type' => 'web', 'topics' => ['international', 'lifestyle'], 'country' => 'France'],
        ['name' => 'Blast', 'base_url' => 'https://www.blast-info.fr', 'media_type' => 'web', 'topics' => ['international'], 'country' => 'France'],
        ['name' => 'Arrêt sur Images', 'base_url' => 'https://www.arretsurimages.net', 'media_type' => 'web', 'topics' => ['international'], 'country' => 'France'],
        ['name' => 'Les Jours', 'base_url' => 'https://lesjours.fr', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'France'],
        ['name' => 'Brief.me', 'base_url' => 'https://www.brief.me', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'France'],
        ['name' => 'Heidi.news FR', 'base_url' => 'https://www.heidi.news/fr', 'media_type' => 'web', 'topics' => ['international'], 'country' => 'France'],
        ['name' => 'Spalian', 'base_url' => 'https://spalian.com', 'media_type' => 'web', 'topics' => ['business', 'international'], 'country' => 'France'],

        // ══════════════════════════════════════════════════════════════════
        // FRANCE — BLOGS VOYAGE INFLUENTS (Top FR, haut trafic)
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Carnets de Traverse', 'base_url' => 'https://www.carnets-traverse.com', 'media_type' => 'web', 'topics' => ['voyage'], 'country' => 'France'],
        ['name' => 'I-Voyages', 'base_url' => 'https://www.i-voyages.net', 'media_type' => 'web', 'topics' => ['voyage'], 'country' => 'France'],
        ['name' => 'TourduMondiste', 'base_url' => 'https://www.tourdumondiste.com', 'media_type' => 'web', 'topics' => ['voyage', 'expatriation'], 'country' => 'France'],
        ['name' => 'Instinct Voyageur', 'base_url' => 'https://www.instinct-voyageur.fr', 'media_type' => 'web', 'topics' => ['voyage'], 'country' => 'France'],
        ['name' => 'Novo-monde', 'base_url' => 'https://www.novo-monde.com', 'media_type' => 'web', 'topics' => ['voyage'], 'country' => 'France'],
        ['name' => 'OneDayOneTravel', 'base_url' => 'https://www.onedayonetravel.com', 'media_type' => 'web', 'topics' => ['voyage'], 'country' => 'France'],
        ['name' => 'Voyages et Vagabondages', 'base_url' => 'https://www.voyagesetvagabondages.com', 'media_type' => 'web', 'topics' => ['voyage'], 'country' => 'France'],
        ['name' => 'Le Blog de Sarah', 'base_url' => 'https://leblogdesarah.com', 'media_type' => 'web', 'topics' => ['voyage'], 'country' => 'France'],
        ['name' => 'Madame Oreille', 'base_url' => 'https://www.madame-oreille.com', 'media_type' => 'web', 'topics' => ['voyage'], 'country' => 'France'],
        ['name' => 'Un Sac sur le Dos', 'base_url' => 'https://unsacsurledos.com', 'media_type' => 'web', 'topics' => ['voyage'], 'country' => 'France'],
        ['name' => 'Voyager en Photos', 'base_url' => 'https://voyager-en-photos.com', 'media_type' => 'web', 'topics' => ['voyage'], 'country' => 'France'],
        ['name' => 'Blog Voyage Way', 'base_url' => 'https://www.voyageway.com', 'media_type' => 'web', 'topics' => ['voyage'], 'country' => 'France'],
        ['name' => 'Refuse to Hibernate', 'base_url' => 'https://www.refusetohibernate.com', 'media_type' => 'web', 'topics' => ['voyage'], 'country' => 'France'],
        ['name' => 'Vie Nomade', 'base_url' => 'https://www.vienomade.com', 'media_type' => 'web', 'topics' => ['voyage', 'expatriation'], 'country' => 'France'],
        ['name' => 'A-Contresens', 'base_url' => 'https://www.a-contresens.net', 'media_type' => 'web', 'topics' => ['voyage'], 'country' => 'France'],
        ['name' => 'Les Globe Blogueurs', 'base_url' => 'https://www.les-globe-blogueurs.com', 'media_type' => 'web', 'topics' => ['voyage'], 'country' => 'France'],
        ['name' => 'Le Monde est à Nous', 'base_url' => 'https://www.lemondeestanous.net', 'media_type' => 'web', 'topics' => ['voyage'], 'country' => 'France'],
        ['name' => 'WeLoveWorld', 'base_url' => 'https://weloveworld.fr', 'media_type' => 'web', 'topics' => ['voyage', 'lifestyle'], 'country' => 'France'],
        ['name' => 'Voyages de Pieds', 'base_url' => 'https://voyagesdepieds.fr', 'media_type' => 'web', 'topics' => ['voyage'], 'country' => 'France'],
        ['name' => 'Girltrotter', 'base_url' => 'https://girltrotter.com', 'media_type' => 'web', 'topics' => ['voyage', 'lifestyle'], 'country' => 'France'],
        ['name' => 'My Travel Background', 'base_url' => 'https://mytravelbackground.com', 'media_type' => 'web', 'topics' => ['voyage'], 'country' => 'France'],
        ['name' => 'Le Prochain Voyage', 'base_url' => 'https://leprochainvoyage.com', 'media_type' => 'web', 'topics' => ['voyage'], 'country' => 'France'],
        ['name' => 'Hashtag Voyage', 'base_url' => 'https://www.hashtagvoyage.fr', 'media_type' => 'web', 'topics' => ['voyage'], 'country' => 'France'],
        ['name' => 'Blog OK Voyage', 'base_url' => 'https://www.okvoyage.com', 'media_type' => 'web', 'topics' => ['voyage'], 'country' => 'France'],
        ['name' => 'Trace ta Route', 'base_url' => 'https://www.trace-ta-route.com', 'media_type' => 'web', 'topics' => ['voyage'], 'country' => 'France'],
        ['name' => 'Voyages etc', 'base_url' => 'https://www.voyagesetc.fr', 'media_type' => 'web', 'topics' => ['voyage'], 'country' => 'France'],
        ['name' => 'Lucie en Voyage', 'base_url' => 'https://lucieenvoyage.com', 'media_type' => 'web', 'topics' => ['voyage'], 'country' => 'France'],
        ['name' => 'Les Aventuriers Voyageurs', 'base_url' => 'https://lesaventuriersvoyageurs.com', 'media_type' => 'web', 'topics' => ['voyage'], 'country' => 'France'],
        ['name' => 'Le Sac à Dos', 'base_url' => 'https://www.lesacados.com', 'media_type' => 'web', 'topics' => ['voyage'], 'country' => 'France'],
        ['name' => 'Planete3w', 'base_url' => 'https://www.planete3w.fr', 'media_type' => 'web', 'topics' => ['voyage'], 'country' => 'France'],

        // ══════════════════════════════════════════════════════════════════
        // FRANCE — DIGITAL NOMADES & TRAVAIL À DISTANCE
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Nomade Digital FR', 'base_url' => 'https://www.nomadedigital.fr', 'media_type' => 'web', 'topics' => ['expatriation', 'entrepreneuriat', 'voyage'], 'country' => 'France'],
        ['name' => 'Les Nouveaux Travailleurs', 'base_url' => 'https://lesnouveauxtravailleurs.fr', 'media_type' => 'web', 'topics' => ['entrepreneuriat', 'expatriation'], 'country' => 'France'],
        ['name' => 'Remoters', 'base_url' => 'https://www.remoters.net', 'media_type' => 'web', 'topics' => ['entrepreneuriat', 'expatriation', 'voyage'], 'country' => 'France'],
        ['name' => 'Coworkees', 'base_url' => 'https://coworkees.com', 'media_type' => 'web', 'topics' => ['entrepreneuriat', 'voyage'], 'country' => 'France'],
        ['name' => 'Malt Blog', 'base_url' => 'https://blog.malt.fr', 'media_type' => 'web', 'topics' => ['entrepreneuriat', 'business'], 'country' => 'France'],
        ['name' => 'Freelance.info', 'base_url' => 'https://www.free-work.com', 'media_type' => 'web', 'topics' => ['entrepreneuriat', 'business'], 'country' => 'France'],
        ['name' => 'Worklib Blog', 'base_url' => 'https://blog.worklib.com', 'media_type' => 'web', 'topics' => ['entrepreneuriat'], 'country' => 'France'],
        ['name' => 'FrenchWeb Remote', 'base_url' => 'https://www.frenchweb.fr/remote', 'media_type' => 'web', 'topics' => ['entrepreneuriat', 'tech'], 'country' => 'France'],

        // ══════════════════════════════════════════════════════════════════
        // FRANCE — INVESTISSEMENT ÉTRANGER & IMMOBILIER INTERNATIONAL
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Investir à l\'Étranger', 'base_url' => 'https://www.investiraletranger.com', 'media_type' => 'web', 'topics' => ['business', 'expatriation'], 'country' => 'France'],
        ['name' => 'Le Revenu Immobilier', 'base_url' => 'https://www.lerevenu.com/immobilier', 'media_type' => 'web', 'topics' => ['business', 'expatriation'], 'country' => 'France'],
        ['name' => 'My Expat', 'base_url' => 'https://www.myexpat.fr', 'media_type' => 'web', 'topics' => ['business', 'expatriation'], 'country' => 'France'],
        ['name' => 'Expatrimo', 'base_url' => 'https://www.expatrimo.com', 'media_type' => 'web', 'topics' => ['business', 'expatriation'], 'country' => 'France'],
        ['name' => 'International Wealth', 'base_url' => 'https://internationalwealth.info/fr', 'media_type' => 'web', 'topics' => ['business', 'expatriation'], 'country' => 'France'],
        ['name' => 'MeilleursAgents International', 'base_url' => 'https://www.meilleursagents.com/prix-immobilier/international', 'media_type' => 'web', 'topics' => ['business', 'expatriation'], 'country' => 'France'],
        ['name' => 'Immobilier Danger', 'base_url' => 'https://www.immobilier-danger.com', 'media_type' => 'web', 'topics' => ['business'], 'country' => 'France'],
        ['name' => 'Objectif Eco', 'base_url' => 'https://www.objectifeco.com', 'media_type' => 'web', 'topics' => ['business', 'entrepreneuriat'], 'country' => 'France'],
        ['name' => 'Avenue des Investisseurs', 'base_url' => 'https://avenuedesinvestisseurs.fr', 'media_type' => 'web', 'topics' => ['business'], 'country' => 'France'],
        ['name' => 'Café de la Bourse', 'base_url' => 'https://www.cafedelabourse.com', 'media_type' => 'web', 'topics' => ['business'], 'country' => 'France'],

        // ══════════════════════════════════════════════════════════════════
        // FRANCE — ÉTUDIANTS INTERNATIONAUX & MOBILITÉ
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'L\'Étudiant International', 'base_url' => 'https://www.letudiant.fr/etudes/international', 'media_type' => 'web', 'topics' => ['international', 'expatriation'], 'country' => 'France'],
        ['name' => 'Studyrama International', 'base_url' => 'https://www.studyrama.com/international', 'media_type' => 'web', 'topics' => ['international', 'expatriation'], 'country' => 'France'],
        ['name' => 'Campus France', 'base_url' => 'https://www.campusfrance.org', 'media_type' => 'web', 'topics' => ['international', 'expatriation'], 'country' => 'France'],
        ['name' => 'Erasmus+ France', 'base_url' => 'https://agence.erasmusplus.fr', 'media_type' => 'web', 'topics' => ['international', 'expatriation'], 'country' => 'France'],
        ['name' => 'Digischool', 'base_url' => 'https://www.digischool.fr', 'media_type' => 'web', 'topics' => ['international'], 'country' => 'France'],
        ['name' => 'Étudions à l\'Étranger', 'base_url' => 'https://www.etudionsaletranger.fr', 'media_type' => 'web', 'topics' => ['international', 'expatriation', 'voyage'], 'country' => 'France'],
        ['name' => 'myCVfactory Blog', 'base_url' => 'https://www.mycvfactory.com/blog', 'media_type' => 'web', 'topics' => ['business', 'international'], 'country' => 'France'],
        ['name' => 'Student.com FR', 'base_url' => 'https://www.student.com/fr', 'media_type' => 'web', 'topics' => ['international', 'expatriation'], 'country' => 'France'],
        ['name' => 'Generation Voyage', 'base_url' => 'https://generationvoyage.fr', 'media_type' => 'web', 'topics' => ['voyage', 'expatriation'], 'country' => 'France'],

        // ══════════════════════════════════════════════════════════════════
        // FRANCE — ASSURANCE & PROTECTION SOCIALE EXPAT
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Assurances Voyage Blog', 'base_url' => 'https://www.chapkadirect.fr/blog', 'media_type' => 'web', 'topics' => ['voyage', 'expatriation'], 'country' => 'France'],
        ['name' => 'ACS Blog (Assurance Expat)', 'base_url' => 'https://www.acs-ami.com/blog', 'media_type' => 'web', 'topics' => ['expatriation', 'voyage'], 'country' => 'France'],
        ['name' => 'Humanis International', 'base_url' => 'https://humanis.com/international', 'media_type' => 'web', 'topics' => ['expatriation', 'business'], 'country' => 'France'],
        ['name' => 'April International', 'base_url' => 'https://fr.april-international.com/blog', 'media_type' => 'web', 'topics' => ['expatriation', 'voyage'], 'country' => 'France'],
        ['name' => 'Allianz Travel Blog', 'base_url' => 'https://www.allianz-voyage.fr/blog', 'media_type' => 'web', 'topics' => ['voyage'], 'country' => 'France'],
        ['name' => 'CFE (Caisse des Français de l\'Étranger)', 'base_url' => 'https://www.cfe.fr', 'media_type' => 'web', 'topics' => ['expatriation'], 'country' => 'France'],

        // ══════════════════════════════════════════════════════════════════
        // FRANCE — FISCALITÉ INTERNATIONALE & DÉMARCHES EXPAT
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Portail des Français à l\'Étranger', 'base_url' => 'https://www.service-public.fr/particuliers/vosdroits/N120', 'media_type' => 'web', 'topics' => ['expatriation'], 'country' => 'France'],
        ['name' => 'Cleiss', 'base_url' => 'https://www.cleiss.fr', 'media_type' => 'web', 'topics' => ['expatriation', 'business'], 'country' => 'France'],
        ['name' => 'French Radar', 'base_url' => 'https://frenchradar.com', 'media_type' => 'web', 'topics' => ['expatriation', 'business'], 'country' => 'France'],
        ['name' => 'ExFi (Expert Fiscal International)', 'base_url' => 'https://www.exfi.fr', 'media_type' => 'web', 'topics' => ['business', 'expatriation'], 'country' => 'France'],
        ['name' => 'Terre d\'Asile', 'base_url' => 'https://www.france-terre-asile.org', 'media_type' => 'web', 'topics' => ['international', 'expatriation'], 'country' => 'France'],
        ['name' => 'La Cimade', 'base_url' => 'https://www.lacimade.org', 'media_type' => 'web', 'topics' => ['international', 'expatriation'], 'country' => 'France'],

        // ══════════════════════════════════════════════════════════════════
        // FRANCE — RETRAITE À L'ÉTRANGER
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Retraite Sans Frontières', 'base_url' => 'https://retraitesansfrontieres.fr', 'media_type' => 'web', 'topics' => ['expatriation', 'lifestyle'], 'country' => 'France'],
        ['name' => 'S\'expatrier en retraite', 'base_url' => 'https://www.expatriation-retraite.com', 'media_type' => 'web', 'topics' => ['expatriation', 'lifestyle'], 'country' => 'France'],
        ['name' => 'Notre Temps Expatriation', 'base_url' => 'https://www.notretemps.com/retraite/partir-vivre-a-l-etranger', 'media_type' => 'web', 'topics' => ['expatriation', 'lifestyle'], 'country' => 'France'],
        ['name' => 'Capital Retraite Étranger', 'base_url' => 'https://www.capital.fr/retraite', 'media_type' => 'web', 'topics' => ['expatriation', 'business'], 'country' => 'France'],

        // ══════════════════════════════════════════════════════════════════
        // FRANCE — PODCASTS VOYAGE/EXPAT/ENTREPRENEURIAT
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Les Baladeurs (podcast voyage)', 'base_url' => 'https://www.lesbaladeurs.com', 'media_type' => 'web', 'topics' => ['voyage'], 'country' => 'France'],
        ['name' => 'Nomade Digital Podcast', 'base_url' => 'https://nomadedigital.tv', 'media_type' => 'web', 'topics' => ['expatriation', 'entrepreneuriat'], 'country' => 'France'],
        ['name' => 'Voyage en Roue Libre', 'base_url' => 'https://voyageenrouelibre.com', 'media_type' => 'web', 'topics' => ['voyage'], 'country' => 'France'],
        ['name' => 'Génération Do It Yourself', 'base_url' => 'https://www.yourpodcast.fr/gdiy', 'media_type' => 'web', 'topics' => ['entrepreneuriat'], 'country' => 'France'],
        ['name' => 'Le Gratin (podcast)', 'base_url' => 'https://legratin.com', 'media_type' => 'web', 'topics' => ['entrepreneuriat', 'business'], 'country' => 'France'],
        ['name' => 'Inpower (podcast)', 'base_url' => 'https://www.louiseaubery.com/inpower', 'media_type' => 'web', 'topics' => ['lifestyle', 'expatriation'], 'country' => 'France'],
        ['name' => 'Transfert (podcast Slate)', 'base_url' => 'https://www.slate.fr/transfert', 'media_type' => 'web', 'topics' => ['lifestyle'], 'country' => 'France'],

        // ══════════════════════════════════════════════════════════════════
        // FRANCE — VACANCIERS & LOISIRS
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Vacances Pratiques', 'base_url' => 'https://www.vacancespratiques.com', 'media_type' => 'web', 'topics' => ['voyage'], 'country' => 'France'],
        ['name' => 'Cityzeum', 'base_url' => 'https://www.cityzeum.com', 'media_type' => 'web', 'topics' => ['voyage'], 'country' => 'France'],
        ['name' => 'Guide du Routard Forum', 'base_url' => 'https://www.routard.com/forum', 'media_type' => 'web', 'topics' => ['voyage', 'expatriation'], 'country' => 'France'],
        ['name' => 'Partir.com', 'base_url' => 'https://www.partir.com', 'media_type' => 'web', 'topics' => ['voyage'], 'country' => 'France'],
        ['name' => 'Skyscanner FR Blog', 'base_url' => 'https://www.skyscanner.fr/actualites-voyage', 'media_type' => 'web', 'topics' => ['voyage'], 'country' => 'France'],
        ['name' => 'Liligo Mag', 'base_url' => 'https://www.liligo.fr/magazine-voyage', 'media_type' => 'web', 'topics' => ['voyage'], 'country' => 'France'],
        ['name' => 'Lastminute FR Blog', 'base_url' => 'https://www.lastminute.com/fr/blog', 'media_type' => 'web', 'topics' => ['voyage'], 'country' => 'France'],
        ['name' => 'Voyages SNCF Magazine', 'base_url' => 'https://www.oui.sncf/magazine', 'media_type' => 'web', 'topics' => ['voyage'], 'country' => 'France'],
        ['name' => 'Air France Magazine', 'base_url' => 'https://www.airfrancemagazine.com', 'media_type' => 'presse_ecrite', 'topics' => ['voyage', 'lifestyle'], 'country' => 'France'],

        // ══════════════════════════════════════════════════════════════════
        // FRANCE — AGENCES DE PRESSE
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'AFP (Agence France-Presse)', 'base_url' => 'https://www.afp.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'France'],

        // ══════════════════════════════════════════════════════════════════
        // FRANCE — ANNUAIRES DE JOURNALISTES
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Annuaire Journaliste FR', 'base_url' => 'https://annuaire.journaliste.fr', 'media_type' => 'web', 'topics' => ['international', 'business', 'voyage', 'expatriation'], 'country' => 'France'],
        ['name' => 'Presselib Freelances', 'base_url' => 'https://www.presselib.com', 'media_type' => 'web', 'topics' => ['international', 'voyage', 'expatriation'], 'country' => 'France'],
        ['name' => 'Muck Rack France', 'base_url' => 'https://muckrack.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'France'],
        ['name' => 'The Conversation France', 'base_url' => 'https://theconversation.com/fr', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'France'],
        ['name' => 'Babbler', 'base_url' => 'https://www.babbler.fr', 'media_type' => 'web', 'topics' => ['business'], 'country' => 'France'],

        // ══════════════════════════════════════════════════════════════════
        // AFRIQUE DE L'OUEST — SÉNÉGAL
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Le Soleil (Sénégal)', 'base_url' => 'https://lesoleil.sn', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business', 'expatriation'], 'country' => 'Sénégal'],
        ['name' => 'Le Quotidien (Sénégal)', 'base_url' => 'https://lequotidien.sn', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Sénégal'],
        ['name' => 'Sud Quotidien', 'base_url' => 'https://www.sudquotidien.sn', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Sénégal'],
        ['name' => 'Seneweb', 'base_url' => 'https://www.seneweb.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Sénégal'],
        ['name' => 'Dakaractu', 'base_url' => 'https://www.dakaractu.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Sénégal'],
        ['name' => 'Emedia Sénégal', 'base_url' => 'https://emediasn.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Sénégal'],
        ['name' => 'Senego', 'base_url' => 'https://senego.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Sénégal'],
        ['name' => 'RFM Sénégal', 'base_url' => 'https://www.rfm.sn', 'media_type' => 'radio', 'topics' => ['international', 'business'], 'country' => 'Sénégal'],
        ['name' => 'TFM Sénégal', 'base_url' => 'https://www.tfm.sn', 'media_type' => 'tv', 'topics' => ['international', 'business'], 'country' => 'Sénégal'],
        ['name' => 'iGFM', 'base_url' => 'https://www.igfm.sn', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Sénégal'],

        // ══════════════════════════════════════════════════════════════════
        // AFRIQUE DE L'OUEST — CÔTE D'IVOIRE
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Fraternité Matin', 'base_url' => 'https://www.fratmat.info', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business', 'expatriation'], 'country' => "Côte d'Ivoire"],
        ['name' => "L'Intelligent d'Abidjan", 'base_url' => 'https://www.lintelligentdabidjan.info', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => "Côte d'Ivoire"],
        ['name' => 'Abidjan.net', 'base_url' => 'https://news.abidjan.net', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => "Côte d'Ivoire"],
        ['name' => 'Koaci', 'base_url' => 'https://www.koaci.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => "Côte d'Ivoire"],
        ['name' => 'Connectionivoirienne', 'base_url' => 'https://www.connectionivoirienne.net', 'media_type' => 'web', 'topics' => ['international', 'business', 'expatriation'], 'country' => "Côte d'Ivoire"],
        ['name' => 'Le Patriote (CI)', 'base_url' => 'https://www.lepatriote.ci', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => "Côte d'Ivoire"],
        ['name' => 'RTI Info (CI)', 'base_url' => 'https://www.rti.ci', 'media_type' => 'tv', 'topics' => ['international', 'business'], 'country' => "Côte d'Ivoire"],
        ['name' => "L'Expression (CI)", 'base_url' => 'https://www.lexpression.ci', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => "Côte d'Ivoire"],

        // ══════════════════════════════════════════════════════════════════
        // AFRIQUE DE L'OUEST — MALI, BURKINA, GUINÉE, BÉNIN, TOGO, NIGER
        // ══════════════════════════════════════════════════════════════════
        ['name' => "L'Essor (Mali)", 'base_url' => 'https://www.essor.ml', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Mali'],
        ['name' => 'Maliweb', 'base_url' => 'https://www.maliweb.net', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Mali'],
        ['name' => 'Malijet', 'base_url' => 'https://malijet.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Mali'],
        ['name' => 'Journal du Mali', 'base_url' => 'https://www.journaldumali.com', 'media_type' => 'web', 'topics' => ['international', 'business', 'expatriation'], 'country' => 'Mali'],
        ['name' => 'Sidwaya (Burkina Faso)', 'base_url' => 'https://www.sidwaya.info', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Burkina Faso'],
        ['name' => 'LeFaso.net', 'base_url' => 'https://lefaso.net', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Burkina Faso'],
        ['name' => 'Fasozine', 'base_url' => 'https://www.fasozine.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Burkina Faso'],
        ['name' => 'Guinéenews', 'base_url' => 'https://guineenews.org', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Guinée'],
        ['name' => 'Mediaguinée', 'base_url' => 'https://mediaguinee.org', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Guinée'],
        ['name' => 'Aminata.com (Guinée)', 'base_url' => 'https://aminata.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Guinée'],
        ['name' => 'La Nation (Bénin)', 'base_url' => 'https://www.lanationbenin.info', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Bénin'],
        ['name' => 'Bénin Web TV', 'base_url' => 'https://beninwebtv.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Bénin'],
        ['name' => 'La Nouvelle Tribune (Bénin)', 'base_url' => 'https://lanouvelletribune.info', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Bénin'],
        ['name' => 'République Togolaise', 'base_url' => 'https://www.republicoftogo.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Togo'],
        ['name' => 'TogoFirst', 'base_url' => 'https://www.togofirst.com', 'media_type' => 'web', 'topics' => ['business', 'entrepreneuriat'], 'country' => 'Togo'],
        ['name' => 'Togo Actualité', 'base_url' => 'https://www.togoactu.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Togo'],
        ['name' => 'Le Sahel (Niger)', 'base_url' => 'https://www.lesahel.org', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Niger'],
        ['name' => 'ActuNiger', 'base_url' => 'https://www.actuniger.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Niger'],

        // ══════════════════════════════════════════════════════════════════
        // AFRIQUE CENTRALE — CAMEROUN, CONGO, RDC, GABON, TCHAD
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Cameroon Tribune', 'base_url' => 'https://www.cameroon-tribune.cm', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Cameroun'],
        ['name' => 'Journal du Cameroun', 'base_url' => 'https://www.journalducameroun.com', 'media_type' => 'web', 'topics' => ['international', 'business', 'expatriation'], 'country' => 'Cameroun'],
        ['name' => 'Cameroun24', 'base_url' => 'https://www.cameroun24.net', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Cameroun'],
        ['name' => 'Camer.be', 'base_url' => 'https://www.camer.be', 'media_type' => 'web', 'topics' => ['international', 'business', 'expatriation'], 'country' => 'Cameroun'],
        ['name' => 'ActuCameroun', 'base_url' => 'https://actucameroun.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Cameroun'],
        ['name' => 'Les Dépêches de Brazzaville', 'base_url' => 'https://www.adiac-congo.com', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Congo-Brazzaville'],
        ['name' => 'Vox Congo', 'base_url' => 'https://www.voxcongo.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Congo-Brazzaville'],
        ['name' => 'Radio Okapi (RDC)', 'base_url' => 'https://www.radiookapi.net', 'media_type' => 'radio', 'topics' => ['international', 'business'], 'country' => 'RDC'],
        ['name' => 'Actualité.cd (RDC)', 'base_url' => 'https://actualite.cd', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'RDC'],
        ['name' => 'Le Phare (RDC)', 'base_url' => 'https://www.lephare.info', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'RDC'],
        ['name' => 'Desk Eco (RDC)', 'base_url' => 'https://deskeco.com', 'media_type' => 'web', 'topics' => ['business', 'entrepreneuriat'], 'country' => 'RDC'],
        ['name' => '7sur7.cd (RDC)', 'base_url' => 'https://7sur7.cd', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'RDC'],
        ['name' => "L'Union (Gabon)", 'base_url' => 'https://www.union.sonapresse.com', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Gabon'],
        ['name' => 'Gabonreview', 'base_url' => 'https://www.gabonreview.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Gabon'],
        ['name' => 'GabonMediaTime', 'base_url' => 'https://www.gabonmediatime.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Gabon'],
        ['name' => 'Tchadinfos', 'base_url' => 'https://tchadinfos.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Tchad'],
        ['name' => 'Alwihda Info (Tchad)', 'base_url' => 'https://www.alwihdainfo.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Tchad'],
        ['name' => 'RJDH (Centrafrique)', 'base_url' => 'https://www.rjdh.org', 'media_type' => 'web', 'topics' => ['international'], 'country' => 'Centrafrique'],

        // ══════════════════════════════════════════════════════════════════
        // MAGHREB — MAROC, TUNISIE, ALGÉRIE
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Le Matin (Maroc)', 'base_url' => 'https://lematin.ma', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business', 'expatriation'], 'country' => 'Maroc'],
        ['name' => "L'Économiste (Maroc)", 'base_url' => 'https://www.leconomiste.com', 'media_type' => 'presse_ecrite', 'topics' => ['business', 'entrepreneuriat'], 'country' => 'Maroc'],
        ['name' => 'TelQuel (Maroc)', 'base_url' => 'https://telquel.ma', 'media_type' => 'web', 'topics' => ['international', 'business', 'lifestyle'], 'country' => 'Maroc'],
        ['name' => 'Hespress FR (Maroc)', 'base_url' => 'https://fr.hespress.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Maroc'],
        ['name' => 'Médias24 (Maroc)', 'base_url' => 'https://medias24.com', 'media_type' => 'web', 'topics' => ['business', 'entrepreneuriat'], 'country' => 'Maroc'],
        ['name' => 'Le360 (Maroc)', 'base_url' => 'https://fr.le360.ma', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Maroc'],
        ['name' => 'Yabiladi (Maroc)', 'base_url' => 'https://www.yabiladi.com', 'media_type' => 'web', 'topics' => ['international', 'expatriation', 'business'], 'country' => 'Maroc'],
        ['name' => 'Aujourd\'hui le Maroc', 'base_url' => 'https://aujourdhui.ma', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Maroc'],
        ['name' => 'Maroc Diplomatique', 'base_url' => 'https://maroc-diplomatique.net', 'media_type' => 'web', 'topics' => ['international', 'business', 'expatriation'], 'country' => 'Maroc'],
        ['name' => 'La Vie Éco (Maroc)', 'base_url' => 'https://www.lavieeco.com', 'media_type' => 'presse_ecrite', 'topics' => ['business', 'entrepreneuriat'], 'country' => 'Maroc'],
        ['name' => '2M Maroc', 'base_url' => 'https://www.2m.ma', 'media_type' => 'tv', 'topics' => ['international', 'business'], 'country' => 'Maroc'],
        ['name' => 'Medi1 TV (Maroc)', 'base_url' => 'https://www.medi1tv.com', 'media_type' => 'tv', 'topics' => ['international', 'business'], 'country' => 'Maroc'],

        ['name' => 'La Presse de Tunisie', 'base_url' => 'https://lapresse.tn', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business', 'expatriation'], 'country' => 'Tunisie'],
        ['name' => 'Business News Tunisie', 'base_url' => 'https://www.businessnews.com.tn', 'media_type' => 'web', 'topics' => ['business', 'entrepreneuriat'], 'country' => 'Tunisie'],
        ['name' => 'Webmanagercenter (Tunisie)', 'base_url' => 'https://www.webmanagercenter.com', 'media_type' => 'web', 'topics' => ['business', 'entrepreneuriat'], 'country' => 'Tunisie'],
        ['name' => 'Kapitalis (Tunisie)', 'base_url' => 'https://kapitalis.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Tunisie'],
        ['name' => 'Leaders Tunisie', 'base_url' => 'https://www.leaders.com.tn', 'media_type' => 'web', 'topics' => ['business', 'entrepreneuriat', 'expatriation'], 'country' => 'Tunisie'],
        ['name' => 'Réalités Tunisie', 'base_url' => 'https://www.realites.com.tn', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Tunisie'],
        ['name' => 'Mosaïque FM (Tunisie)', 'base_url' => 'https://www.mosaiquefm.net', 'media_type' => 'radio', 'topics' => ['international', 'business'], 'country' => 'Tunisie'],
        ['name' => 'Nawaat (Tunisie)', 'base_url' => 'https://nawaat.org', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Tunisie'],

        ['name' => 'El Watan (Algérie)', 'base_url' => 'https://www.elwatan.com', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Algérie'],
        ['name' => 'Liberté Algérie', 'base_url' => 'https://www.liberte-algerie.com', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Algérie'],
        ['name' => 'Le Soir d\'Algérie', 'base_url' => 'https://www.lesoirdalgerie.com', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Algérie'],
        ['name' => 'TSA Algérie', 'base_url' => 'https://www.tsa-algerie.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Algérie'],
        ['name' => 'Algérie Eco', 'base_url' => 'https://www.algerie-eco.com', 'media_type' => 'web', 'topics' => ['business', 'entrepreneuriat'], 'country' => 'Algérie'],
        ['name' => 'Le Quotidien d\'Oran', 'base_url' => 'https://www.lequotidien-oran.com', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Algérie'],
        ['name' => 'Algérie Presse Service (APS)', 'base_url' => 'https://www.aps.dz', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Algérie'],
        ['name' => 'Maghreb Emergent', 'base_url' => 'https://maghrebemergent.info', 'media_type' => 'web', 'topics' => ['business', 'entrepreneuriat', 'international'], 'country' => 'Algérie'],
        ['name' => 'Interlignes Algérie', 'base_url' => 'https://interlignes.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Algérie'],

        // ══════════════════════════════════════════════════════════════════
        // OCÉAN INDIEN — MADAGASCAR, MAURICE, COMORES, DJIBOUTI
        // ══════════════════════════════════════════════════════════════════
        ['name' => "L'Express de Madagascar", 'base_url' => 'https://www.lexpressmada.com', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Madagascar'],
        ['name' => 'Midi Madagasikara', 'base_url' => 'https://www.midi-madagasikara.mg', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Madagascar'],
        ['name' => 'Newsmada', 'base_url' => 'https://www.newsmada.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Madagascar'],
        ['name' => 'Madagascar Tribune', 'base_url' => 'https://www.madagascar-tribune.com', 'media_type' => 'web', 'topics' => ['international', 'business', 'expatriation'], 'country' => 'Madagascar'],
        ['name' => 'Orange Actu Madagascar', 'base_url' => 'https://actu.orange.mg', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Madagascar'],
        ['name' => 'L\'Express Maurice', 'base_url' => 'https://lexpress.mu', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business', 'expatriation'], 'country' => 'Maurice'],
        ['name' => 'Le Défi Media (Maurice)', 'base_url' => 'https://defimedia.info', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Maurice'],
        ['name' => 'Le Mauricien', 'base_url' => 'https://www.lemauricien.com', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business', 'expatriation'], 'country' => 'Maurice'],
        ['name' => 'Comores Infos', 'base_url' => 'https://www.comoresinfos.net', 'media_type' => 'web', 'topics' => ['international'], 'country' => 'Comores'],
        ['name' => 'La Nation (Djibouti)', 'base_url' => 'https://www.lanation.dj', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Djibouti'],

        // ══════════════════════════════════════════════════════════════════
        // GRANDS LACS — RWANDA, BURUNDI
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'The New Times Rwanda (FR)', 'base_url' => 'https://www.newtimes.co.rw', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Rwanda'],
        ['name' => 'Igihe (Rwanda)', 'base_url' => 'https://fr.igihe.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Rwanda'],
        ['name' => 'Iwacu (Burundi)', 'base_url' => 'https://www.iwacu-burundi.org', 'media_type' => 'web', 'topics' => ['international'], 'country' => 'Burundi'],
        ['name' => 'SOS Médias Burundi', 'base_url' => 'https://www.sosmedias.org', 'media_type' => 'web', 'topics' => ['international'], 'country' => 'Burundi'],

        // ══════════════════════════════════════════════════════════════════
        // MOYEN-ORIENT — LIBAN
        // ══════════════════════════════════════════════════════════════════
        ['name' => "L'Orient-Le Jour (Liban)", 'base_url' => 'https://www.lorientlejour.com', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business', 'expatriation'], 'country' => 'Liban'],
        ['name' => 'L\'Orient Today (Liban)', 'base_url' => 'https://today.lorientlejour.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Liban'],
        ['name' => 'Le Commerce du Levant (Liban)', 'base_url' => 'https://www.lecommercedulevant.com', 'media_type' => 'presse_ecrite', 'topics' => ['business', 'entrepreneuriat'], 'country' => 'Liban'],
        ['name' => 'Ici Beyrouth', 'base_url' => 'https://icibeyrouth.com', 'media_type' => 'web', 'topics' => ['international', 'business', 'expatriation'], 'country' => 'Liban'],
        ['name' => 'Libnanews (Liban)', 'base_url' => 'https://libnanews.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Liban'],
        ['name' => 'MTV Liban', 'base_url' => 'https://www.mtv.com.lb', 'media_type' => 'tv', 'topics' => ['international', 'business'], 'country' => 'Liban'],
        ['name' => 'OLJ Lifestyle', 'base_url' => 'https://www.lorientlejour.com/rubrique/lifestyle', 'media_type' => 'web', 'topics' => ['lifestyle', 'voyage', 'expatriation'], 'country' => 'Liban'],

        // ══════════════════════════════════════════════════════════════════
        // CARAÏBES — HAÏTI
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Le Nouvelliste (Haïti)', 'base_url' => 'https://lenouvelliste.com', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business', 'expatriation'], 'country' => 'Haïti'],
        ['name' => 'Le National (Haïti)', 'base_url' => 'https://lenational.org', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Haïti'],
        ['name' => 'AyiboPost (Haïti)', 'base_url' => 'https://ayibopost.com', 'media_type' => 'web', 'topics' => ['international', 'business', 'lifestyle'], 'country' => 'Haïti'],
        ['name' => 'Haiti Libre', 'base_url' => 'https://www.haitilibre.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Haïti'],
        ['name' => 'Rezo Nòdwès (Haïti)', 'base_url' => 'https://rezonodwes.com', 'media_type' => 'web', 'topics' => ['international'], 'country' => 'Haïti'],

        // ══════════════════════════════════════════════════════════════════
        // DOM-TOM — GUADELOUPE, MARTINIQUE, GUYANE, RÉUNION, MAYOTTE, NC, PF
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'France-Antilles Guadeloupe', 'base_url' => 'https://www.guadeloupe.franceantilles.fr', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'voyage'], 'country' => 'Guadeloupe'],
        ['name' => 'France-Antilles Martinique', 'base_url' => 'https://www.martinique.franceantilles.fr', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'voyage'], 'country' => 'Martinique'],
        ['name' => 'France-Guyane', 'base_url' => 'https://www.franceguyane.fr', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'voyage'], 'country' => 'Guyane'],
        ['name' => 'Le Journal de la Réunion (JIR)', 'base_url' => 'https://www.clicanoo.re', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'voyage', 'expatriation'], 'country' => 'La Réunion'],
        ['name' => 'Linfo.re (La Réunion)', 'base_url' => 'https://www.linfo.re', 'media_type' => 'web', 'topics' => ['international', 'voyage'], 'country' => 'La Réunion'],
        ['name' => 'Zinfos974 (La Réunion)', 'base_url' => 'https://www.zinfos974.com', 'media_type' => 'web', 'topics' => ['international', 'voyage'], 'country' => 'La Réunion'],
        ['name' => 'Le Quotidien de La Réunion', 'base_url' => 'https://www.lequotidien.re', 'media_type' => 'presse_ecrite', 'topics' => ['international'], 'country' => 'La Réunion'],
        ['name' => 'Mayotte Hebdo', 'base_url' => 'https://www.mayottehebdo.com', 'media_type' => 'presse_ecrite', 'topics' => ['international'], 'country' => 'Mayotte'],
        ['name' => 'Les Nouvelles Calédoniennes', 'base_url' => 'https://www.lnc.nc', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'voyage'], 'country' => 'Nouvelle-Calédonie'],
        ['name' => 'Caledonia (NC)', 'base_url' => 'https://www.caledonia.nc', 'media_type' => 'web', 'topics' => ['international'], 'country' => 'Nouvelle-Calédonie'],
        ['name' => 'La Dépêche de Tahiti', 'base_url' => 'https://www.ladepeche.pf', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'voyage'], 'country' => 'Polynésie française'],
        ['name' => 'Tahiti Infos', 'base_url' => 'https://www.tahiti-infos.com', 'media_type' => 'web', 'topics' => ['international', 'voyage'], 'country' => 'Polynésie française'],

        // ══════════════════════════════════════════════════════════════════
        // BELGIQUE (compléments)
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Le Vif (Belgique)', 'base_url' => 'https://www.levif.be', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Belgique'],
        ['name' => 'Moustique (Belgique)', 'base_url' => 'https://www.moustique.be', 'media_type' => 'presse_ecrite', 'topics' => ['lifestyle', 'voyage'], 'country' => 'Belgique'],
        ['name' => 'La DH / Les Sports+ (BE)', 'base_url' => 'https://www.dhnet.be', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Belgique'],
        ['name' => 'L\'Avenir (Belgique)', 'base_url' => 'https://www.lavenir.net', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Belgique'],
        ['name' => 'Metro Belgique FR', 'base_url' => 'https://fr.metrotime.be', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business', 'lifestyle'], 'country' => 'Belgique'],
        ['name' => 'Trends-Tendances (BE)', 'base_url' => 'https://trends.levif.be', 'media_type' => 'web', 'topics' => ['business', 'entrepreneuriat'], 'country' => 'Belgique'],

        // ══════════════════════════════════════════════════════════════════
        // SUISSE (compléments)
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Tribune de Genève', 'base_url' => 'https://www.tdg.ch', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Suisse'],
        ['name' => '24 Heures (Suisse)', 'base_url' => 'https://www.24heures.ch', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Suisse'],
        ['name' => 'Le Matin (Suisse)', 'base_url' => 'https://www.lematin.ch', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Suisse'],
        ['name' => 'Agefi Suisse', 'base_url' => 'https://www.agefi.com', 'media_type' => 'presse_ecrite', 'topics' => ['business', 'entrepreneuriat'], 'country' => 'Suisse'],
        ['name' => 'PME Magazine (Suisse)', 'base_url' => 'https://www.pme.ch', 'media_type' => 'web', 'topics' => ['business', 'entrepreneuriat'], 'country' => 'Suisse'],
        ['name' => 'Bilan (Suisse)', 'base_url' => 'https://www.bilan.ch', 'media_type' => 'presse_ecrite', 'topics' => ['business', 'entrepreneuriat'], 'country' => 'Suisse'],
        ['name' => 'Swissinfo FR', 'base_url' => 'https://www.swissinfo.ch/fre', 'media_type' => 'web', 'topics' => ['international', 'business', 'expatriation'], 'country' => 'Suisse'],

        // ══════════════════════════════════════════════════════════════════
        // LUXEMBOURG
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Le Quotidien (Luxembourg)', 'base_url' => 'https://lequotidien.lu', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Luxembourg'],
        ['name' => 'Paperjam (Luxembourg)', 'base_url' => 'https://paperjam.lu', 'media_type' => 'web', 'topics' => ['business', 'entrepreneuriat'], 'country' => 'Luxembourg'],
        ['name' => "L'Essentiel (Luxembourg)", 'base_url' => 'https://www.lessentiel.lu', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Luxembourg'],
        ['name' => 'Luxemburger Wort FR', 'base_url' => 'https://www.wort.lu/fr', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Luxembourg'],
        ['name' => 'Virgule (Luxembourg)', 'base_url' => 'https://www.virgule.lu', 'media_type' => 'web', 'topics' => ['lifestyle', 'business'], 'country' => 'Luxembourg'],
        ['name' => 'Chronicle.lu (Luxembourg)', 'base_url' => 'https://chronicle.lu', 'media_type' => 'web', 'topics' => ['business', 'expatriation'], 'country' => 'Luxembourg'],

        // ══════════════════════════════════════════════════════════════════
        // CANADA / QUÉBEC (compléments)
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Le Journal de Montréal', 'base_url' => 'https://www.journaldemontreal.com', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Canada'],
        ['name' => 'Le Journal de Québec', 'base_url' => 'https://www.journaldequebec.com', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Canada'],
        ['name' => 'TVA Nouvelles', 'base_url' => 'https://www.tvanouvelles.ca', 'media_type' => 'tv', 'topics' => ['international', 'business'], 'country' => 'Canada'],
        ['name' => 'Le Soleil (Québec)', 'base_url' => 'https://www.lesoleil.com', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Canada'],
        ['name' => 'Le Droit (Ottawa)', 'base_url' => 'https://www.ledroit.com', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Canada'],
        ['name' => 'L\'Acadie Nouvelle (NB)', 'base_url' => 'https://www.acadienouvelle.com', 'media_type' => 'presse_ecrite', 'topics' => ['international'], 'country' => 'Canada'],
        ['name' => 'La Tribune (Sherbrooke)', 'base_url' => 'https://www.latribune.ca', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Canada'],
        ['name' => 'Les Affaires (Québec)', 'base_url' => 'https://www.lesaffaires.com', 'media_type' => 'web', 'topics' => ['business', 'entrepreneuriat'], 'country' => 'Canada'],
        ['name' => 'Protégez-Vous (Québec)', 'base_url' => 'https://www.protegez-vous.ca', 'media_type' => 'web', 'topics' => ['business', 'lifestyle'], 'country' => 'Canada'],
        ['name' => 'L\'Actualité (Québec)', 'base_url' => 'https://lactualite.com', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Canada'],
        ['name' => 'Narcity Québec', 'base_url' => 'https://www.narcity.com/fr', 'media_type' => 'web', 'topics' => ['lifestyle', 'voyage'], 'country' => 'Canada'],

        // ══════════════════════════════════════════════════════════════════
        // MONACO
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Monaco Matin', 'base_url' => 'https://www.monacomatin.mc', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business', 'lifestyle'], 'country' => 'Monaco'],
        ['name' => 'Monaco Tribune', 'base_url' => 'https://www.monaco-tribune.com', 'media_type' => 'web', 'topics' => ['business', 'lifestyle', 'expatriation'], 'country' => 'Monaco'],

        // ══════════════════════════════════════════════════════════════════
        // PRESSE PAN-AFRICAINE & PANFRANCOPHONE
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Jeune Afrique', 'base_url' => 'https://www.jeuneafrique.com', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business', 'expatriation'], 'country' => 'International'],
        ['name' => 'Africanews FR', 'base_url' => 'https://fr.africanews.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'International'],
        ['name' => 'Africa24', 'base_url' => 'https://www.africa24tv.com', 'media_type' => 'tv', 'topics' => ['international', 'business'], 'country' => 'International'],
        ['name' => 'Financial Afrik', 'base_url' => 'https://www.financialafrik.com', 'media_type' => 'web', 'topics' => ['business', 'entrepreneuriat'], 'country' => 'International'],
        ['name' => 'Ecofin Agency', 'base_url' => 'https://www.agenceecofin.com', 'media_type' => 'web', 'topics' => ['business', 'entrepreneuriat'], 'country' => 'International'],
        ['name' => 'La Tribune Afrique', 'base_url' => 'https://afrique.latribune.fr', 'media_type' => 'web', 'topics' => ['business', 'entrepreneuriat'], 'country' => 'International'],
        ['name' => 'Le Point Afrique', 'base_url' => 'https://www.lepoint.fr/afrique', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'International'],
        ['name' => 'Mondafrique', 'base_url' => 'https://mondafrique.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'International'],
        ['name' => 'Africa Intelligence', 'base_url' => 'https://www.africaintelligence.fr', 'media_type' => 'web', 'topics' => ['business', 'international'], 'country' => 'International'],
        ['name' => 'Afrique Magazine', 'base_url' => 'https://www.afriquemagazine.com', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business', 'lifestyle'], 'country' => 'International'],
        ['name' => 'Le Monde Afrique', 'base_url' => 'https://www.lemonde.fr/afrique', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'International'],
        ['name' => 'RFI Afrique', 'base_url' => 'https://www.rfi.fr/fr/afrique', 'media_type' => 'radio', 'topics' => ['international', 'business'], 'country' => 'International'],
        ['name' => 'France 24 Afrique', 'base_url' => 'https://www.france24.com/fr/afrique', 'media_type' => 'tv', 'topics' => ['international', 'business'], 'country' => 'International'],
        ['name' => 'Courrier International Afrique', 'base_url' => 'https://www.courrierinternational.com/continent/afrique', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'International'],
        ['name' => 'Sputnik FR Afrique', 'base_url' => 'https://fr.sputniknews.africa', 'media_type' => 'web', 'topics' => ['international'], 'country' => 'International'],

        // ══════════════════════════════════════════════════════════════════
        // PRESSE EXPAT & VOYAGE FRANCOPHONE (compléments mondiaux)
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Lepetitjournal.com (global)', 'base_url' => 'https://lepetitjournal.com', 'media_type' => 'web', 'topics' => ['expatriation', 'international', 'voyage'], 'country' => 'International'],
        ['name' => 'FemmExpat', 'base_url' => 'https://www.femmexpat.com', 'media_type' => 'web', 'topics' => ['expatriation', 'lifestyle'], 'country' => 'International'],
        ['name' => 'Expat.com Magazine', 'base_url' => 'https://www.expat.com/fr/magazine', 'media_type' => 'web', 'topics' => ['expatriation', 'international'], 'country' => 'International'],
        ['name' => 'French Morning (USA)', 'base_url' => 'https://frenchmorning.com', 'media_type' => 'web', 'topics' => ['expatriation', 'international'], 'country' => 'USA'],
        ['name' => 'French District (USA)', 'base_url' => 'https://www.frenchdistrict.com', 'media_type' => 'web', 'topics' => ['expatriation', 'international'], 'country' => 'USA'],
        ['name' => 'Courrier Australien', 'base_url' => 'https://www.lecourrieraustralien.com', 'media_type' => 'web', 'topics' => ['expatriation', 'voyage'], 'country' => 'Australie'],
        ['name' => 'Le Courrier des Amériques', 'base_url' => 'https://www.lecourrierdesameriques.com', 'media_type' => 'web', 'topics' => ['expatriation', 'international'], 'country' => 'USA'],
        ['name' => 'French Connexion (UK)', 'base_url' => 'https://www.french-connexion.co.uk', 'media_type' => 'web', 'topics' => ['expatriation', 'international'], 'country' => 'Royaume-Uni'],
        ['name' => 'Vivre à Berlin', 'base_url' => 'https://vivreaberlin.com', 'media_type' => 'web', 'topics' => ['expatriation', 'voyage'], 'country' => 'Allemagne'],
        ['name' => 'Vivre à Tokyo', 'base_url' => 'https://vivreatokyo.com', 'media_type' => 'web', 'topics' => ['expatriation', 'voyage'], 'country' => 'Japon'],
        ['name' => 'Vivre au Mexique', 'base_url' => 'https://vivreaumexique.com', 'media_type' => 'web', 'topics' => ['expatriation', 'voyage'], 'country' => 'Mexique'],
        ['name' => 'Vivre en Thaïlande', 'base_url' => 'https://www.vivreenthailande.com', 'media_type' => 'web', 'topics' => ['expatriation', 'voyage'], 'country' => 'Thaïlande'],
        ['name' => 'Lepetitjournal Bangkok', 'base_url' => 'https://lepetitjournal.com/bangkok', 'media_type' => 'web', 'topics' => ['expatriation'], 'country' => 'Thaïlande'],
        ['name' => 'Lepetitjournal Barcelone', 'base_url' => 'https://lepetitjournal.com/barcelone', 'media_type' => 'web', 'topics' => ['expatriation'], 'country' => 'Espagne'],
        ['name' => 'Lepetitjournal Lisbonne', 'base_url' => 'https://lepetitjournal.com/lisbonne', 'media_type' => 'web', 'topics' => ['expatriation'], 'country' => 'Portugal'],
        ['name' => 'Lepetitjournal Londres', 'base_url' => 'https://lepetitjournal.com/londres', 'media_type' => 'web', 'topics' => ['expatriation'], 'country' => 'Royaume-Uni'],
        ['name' => 'Lepetitjournal Casablanca', 'base_url' => 'https://lepetitjournal.com/casablanca', 'media_type' => 'web', 'topics' => ['expatriation'], 'country' => 'Maroc'],
        ['name' => 'Lepetitjournal Dubaï', 'base_url' => 'https://lepetitjournal.com/dubai', 'media_type' => 'web', 'topics' => ['expatriation'], 'country' => 'EAU'],
        ['name' => 'Lepetitjournal Hong Kong', 'base_url' => 'https://lepetitjournal.com/hong-kong', 'media_type' => 'web', 'topics' => ['expatriation'], 'country' => 'Hong Kong'],
        ['name' => 'Lepetitjournal Singapour', 'base_url' => 'https://lepetitjournal.com/singapour', 'media_type' => 'web', 'topics' => ['expatriation'], 'country' => 'Singapour'],
        ['name' => 'Lepetitjournal Berlin', 'base_url' => 'https://lepetitjournal.com/berlin', 'media_type' => 'web', 'topics' => ['expatriation'], 'country' => 'Allemagne'],
        ['name' => 'Lepetitjournal Milan', 'base_url' => 'https://lepetitjournal.com/milan', 'media_type' => 'web', 'topics' => ['expatriation'], 'country' => 'Italie'],
        ['name' => 'Lepetitjournal New York', 'base_url' => 'https://lepetitjournal.com/new-york', 'media_type' => 'web', 'topics' => ['expatriation'], 'country' => 'USA'],
        ['name' => 'Lepetitjournal Montréal', 'base_url' => 'https://lepetitjournal.com/montreal', 'media_type' => 'web', 'topics' => ['expatriation'], 'country' => 'Canada'],
        ['name' => 'Lepetitjournal Sydney', 'base_url' => 'https://lepetitjournal.com/sydney', 'media_type' => 'web', 'topics' => ['expatriation'], 'country' => 'Australie'],

        // ══════════════════════════════════════════════════════════════════
        // PRESSE JURIDIQUE FRANCOPHONE
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Le Monde du Droit', 'base_url' => 'https://www.lemondedudroit.fr', 'media_type' => 'web', 'topics' => ['business', 'juridique'], 'country' => 'France'],
        ['name' => 'Dalloz Actualité', 'base_url' => 'https://www.dalloz-actualite.fr', 'media_type' => 'web', 'topics' => ['juridique'], 'country' => 'France'],
        ['name' => 'Village de la Justice', 'base_url' => 'https://www.village-justice.com', 'media_type' => 'web', 'topics' => ['juridique', 'business'], 'country' => 'France'],
        ['name' => 'Actu-Juridique', 'base_url' => 'https://www.actu-juridique.fr', 'media_type' => 'web', 'topics' => ['juridique'], 'country' => 'France'],
        ['name' => 'Le Petit Juriste', 'base_url' => 'https://www.lepetitjuriste.fr', 'media_type' => 'web', 'topics' => ['juridique', 'business'], 'country' => 'France'],
        ['name' => 'Juritravail', 'base_url' => 'https://www.juritravail.com', 'media_type' => 'web', 'topics' => ['juridique', 'business'], 'country' => 'France'],

        // ══════════════════════════════════════════════════════════════════
        // PRESSE TECH/STARTUP FRANCOPHONE
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Frenchweb', 'base_url' => 'https://www.frenchweb.fr', 'media_type' => 'web', 'topics' => ['tech', 'entrepreneuriat'], 'country' => 'France'],
        ['name' => 'Maddyness', 'base_url' => 'https://www.maddyness.com', 'media_type' => 'web', 'topics' => ['entrepreneuriat', 'tech'], 'country' => 'France'],
        ['name' => 'BPI France Le Hub', 'base_url' => 'https://lehub.bpifrance.fr', 'media_type' => 'web', 'topics' => ['entrepreneuriat', 'business'], 'country' => 'France'],
        ['name' => 'WeDemain', 'base_url' => 'https://www.wedemain.fr', 'media_type' => 'web', 'topics' => ['entrepreneuriat', 'international'], 'country' => 'France'],
        ['name' => 'Siècle Digital', 'base_url' => 'https://siecledigital.fr', 'media_type' => 'web', 'topics' => ['tech', 'entrepreneuriat'], 'country' => 'France'],
        ['name' => 'Presse-Citron', 'base_url' => 'https://www.presse-citron.net', 'media_type' => 'web', 'topics' => ['tech'], 'country' => 'France'],
        ['name' => 'Numerama', 'base_url' => 'https://www.numerama.com', 'media_type' => 'web', 'topics' => ['tech'], 'country' => 'France'],
        ['name' => '01net', 'base_url' => 'https://www.01net.com', 'media_type' => 'web', 'topics' => ['tech'], 'country' => 'France'],
        ['name' => 'Clubic', 'base_url' => 'https://www.clubic.com', 'media_type' => 'web', 'topics' => ['tech'], 'country' => 'France'],

        // ══════════════════════════════════════════════════════════════════
        // PRESSE SANTÉ / BIEN-ÊTRE FRANCOPHONE
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Top Santé', 'base_url' => 'https://www.topsante.com', 'media_type' => 'web', 'topics' => ['lifestyle'], 'country' => 'France'],
        ['name' => 'Doctissimo', 'base_url' => 'https://www.doctissimo.fr', 'media_type' => 'web', 'topics' => ['lifestyle'], 'country' => 'France'],
        ['name' => 'Psychologies Magazine', 'base_url' => 'https://www.psychologies.com', 'media_type' => 'presse_ecrite', 'topics' => ['lifestyle'], 'country' => 'France'],
        ['name' => 'Santé Magazine', 'base_url' => 'https://www.santemagazine.fr', 'media_type' => 'web', 'topics' => ['lifestyle'], 'country' => 'France'],

        // ══════════════════════════════════════════════════════════════════
        // AGENCES DE PRESSE FRANCOPHONES
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'AFP (Agence France-Presse)', 'base_url' => 'https://www.afp.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'France'],
        ['name' => 'MAP (Maghreb Arabe Presse)', 'base_url' => 'https://www.mapnews.ma', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Maroc'],
        ['name' => 'TAP (Tunis Afrique Presse)', 'base_url' => 'https://www.tap.info.tn', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Tunisie'],
        ['name' => 'Belga (Belgique)', 'base_url' => 'https://www.belga.be', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Belgique'],
        ['name' => 'ATS/Keystone (Suisse)', 'base_url' => 'https://www.keystone-sda.ch', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Suisse'],
        ['name' => 'PANA (Panapress Afrique)', 'base_url' => 'https://www.panapress.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'International'],
    ];

    public function handle(): int
    {
        $reset   = $this->option('reset');
        $doScrape = $this->option('scrape');

        if ($reset) {
            $this->warn('⚠ This will truncate ALL publications. Proceed? (existing contacts are preserved)');
            // In non-interactive mode, just proceed
        }

        $inserted = 0;
        $skipped  = 0;

        foreach ($this->publications as $pub) {
            $slug = Str::slug($pub['name']);

            $domain = parse_url($pub['base_url'], PHP_URL_HOST) ?? '';
            $domain = preg_replace('/^www\./', '', $domain);

            // Check duplicate by slug or domain
            $exists = PressPublication::where('slug', $slug)
                ->orWhere('base_url', 'LIKE', "%{$domain}%")
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            $base = rtrim($pub['base_url'], '/');

            PressPublication::create([
                'name'        => $pub['name'],
                'slug'        => $slug,
                'base_url'    => $base,
                'team_url'    => $base . '/equipe',
                'contact_url' => $base . '/contact',
                'media_type'  => $pub['media_type'] ?? 'web',
                'topics'      => $pub['topics'] ?? [],
                'language'    => 'fr',
                'country'     => $pub['country'] ?? 'France',
                'status'      => 'pending',
            ]);
            $inserted++;
        }

        $total = PressPublication::count();
        $this->info("Import francophone mondial terminé:");
        $this->line("  Nouvelles: {$inserted}");
        $this->line("  Ignorées (déjà existantes): {$skipped}");
        $this->line("  Total en base: {$total}");
        $this->newLine();

        // Stats par pays
        $byCountry = PressPublication::selectRaw("country, COUNT(*) as n")
            ->groupBy('country')
            ->orderByDesc('n')
            ->pluck('n', 'country');

        $this->info('Par pays:');
        foreach ($byCountry as $country => $n) {
            $this->line("  [{$country}] {$n}");
        }

        // Launch scraping if requested
        if ($doScrape && $inserted > 0) {
            $this->newLine();
            $this->info("Lancement du scraping pour {$inserted} nouvelles publications...");
            $this->call('press:discover', ['--category' => 'all', '--scrape' => true]);
        }

        return Command::SUCCESS;
    }
}
