<?php

namespace App\Console\Commands;

use App\Models\PressPublication;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Seed the curated list of French press publications
 * covering entrepreneurship, travel, expatriation, and international topics.
 *
 * Usage: php artisan press:import-publications [--reset]
 */
class ImportPressPublications extends Command
{
    protected $signature = 'press:import-publications {--reset : Drop and re-insert all publications}';
    protected $description = 'Import curated list of French press publications (entrepreneuriat, voyage, expat, TV/radio)';

    /**
     * Curated publications list.
     * media_type: presse_ecrite | web | tv | radio
     * topics: entrepreneuriat | voyage | expatriation | international | business | tech | lifestyle
     */
    private array $publications = [
        // ─── PRESSE ÉCONOMIQUE & ENTREPRENEURIAT ───────────────────────────
        [
            'name'         => 'Capital',
            'base_url'     => 'https://www.capital.fr',
            'team_url'     => 'https://www.capital.fr/la-redaction',
            'contact_url'  => 'https://www.capital.fr/contact',
            'media_type'   => 'presse_ecrite',
            'topics'       => ['business', 'entrepreneuriat', 'international'],
        ],
        [
            'name'         => 'Challenges',
            'base_url'     => 'https://www.challenges.fr',
            'team_url'     => 'https://www.challenges.fr/redaction',
            'contact_url'  => 'https://www.challenges.fr/contact',
            'media_type'   => 'presse_ecrite',
            'topics'       => ['business', 'entrepreneuriat', 'international'],
        ],
        [
            'name'         => 'Forbes France',
            'base_url'     => 'https://www.forbes.fr',
            'team_url'     => 'https://www.forbes.fr/equipe',
            'contact_url'  => 'https://www.forbes.fr/contact',
            'media_type'   => 'web',
            'topics'       => ['business', 'entrepreneuriat', 'tech', 'international'],
        ],
        [
            'name'         => 'La Tribune',
            'base_url'     => 'https://www.latribune.fr',
            'team_url'     => 'https://www.latribune.fr/redaction',
            'contact_url'  => 'https://www.latribune.fr/contact',
            'media_type'   => 'web',
            'topics'       => ['business', 'entrepreneuriat', 'international'],
        ],
        [
            'name'         => 'Les Echos',
            'base_url'     => 'https://www.lesechos.fr',
            'team_url'     => 'https://www.lesechos.fr/redaction',
            'contact_url'  => 'https://www.lesechos.fr/mentions-legales',
            'media_type'   => 'presse_ecrite',
            'topics'       => ['business', 'entrepreneuriat', 'international'],
        ],
        [
            'name'         => "L'Express Entreprises",
            'base_url'     => 'https://lentreprise.lexpress.fr',
            'team_url'     => 'https://www.lexpress.fr/redaction',
            'contact_url'  => 'https://www.lexpress.fr/contact',
            'media_type'   => 'presse_ecrite',
            'topics'       => ['business', 'entrepreneuriat'],
        ],
        [
            'name'         => "L'Usine Nouvelle",
            'base_url'     => 'https://www.usinenouvelle.com',
            'team_url'     => 'https://www.usinenouvelle.com/la-redaction',
            'contact_url'  => 'https://www.usinenouvelle.com/contact',
            'media_type'   => 'presse_ecrite',
            'topics'       => ['business', 'tech', 'entrepreneuriat'],
        ],
        [
            'name'         => 'Maddyness',
            'base_url'     => 'https://www.maddyness.com',
            'team_url'     => 'https://www.maddyness.com/equipe',
            'contact_url'  => 'https://www.maddyness.com/contact',
            'media_type'   => 'web',
            'topics'       => ['entrepreneuriat', 'tech', 'startup'],
        ],
        [
            'name'         => 'FrenchWeb',
            'base_url'     => 'https://www.frenchweb.fr',
            'team_url'     => 'https://www.frenchweb.fr/equipe',
            'contact_url'  => 'https://www.frenchweb.fr/contact',
            'media_type'   => 'web',
            'topics'       => ['tech', 'entrepreneuriat', 'startup'],
        ],
        [
            'name'         => "Chef d'Entreprise",
            'base_url'     => 'https://www.chefdentreprise.com',
            'team_url'     => 'https://www.chefdentreprise.com/a-propos',
            'contact_url'  => 'https://www.chefdentreprise.com/contact',
            'media_type'   => 'web',
            'topics'       => ['entrepreneuriat', 'business'],
        ],
        [
            'name'         => 'Management Magazine',
            'base_url'     => 'https://www.management.fr',
            'team_url'     => 'https://www.management.fr/la-redaction',
            'contact_url'  => 'https://www.management.fr/contact',
            'media_type'   => 'presse_ecrite',
            'topics'       => ['business', 'entrepreneuriat'],
        ],
        [
            'name'         => 'Stratégies',
            'base_url'     => 'https://www.strategies.fr',
            'team_url'     => 'https://www.strategies.fr/la-redaction',
            'contact_url'  => 'https://www.strategies.fr/contact',
            'media_type'   => 'web',
            'topics'       => ['business', 'entrepreneuriat', 'tech'],
        ],
        [
            'name'         => 'LSA Conso',
            'base_url'     => 'https://www.lsa-conso.fr',
            'team_url'     => 'https://www.lsa-conso.fr/equipe-redactionnelle',
            'contact_url'  => 'https://www.lsa-conso.fr/contact',
            'media_type'   => 'presse_ecrite',
            'topics'       => ['business', 'entrepreneuriat'],
        ],
        [
            'name'         => 'CB News',
            'base_url'     => 'https://www.cbnews.fr',
            'team_url'     => 'https://www.cbnews.fr/redaction',
            'contact_url'  => 'https://www.cbnews.fr/contact',
            'media_type'   => 'web',
            'topics'       => ['business', 'tech'],
        ],
        [
            'name'         => 'Influencia',
            'base_url'     => 'https://www.influencia.net',
            'team_url'     => 'https://www.influencia.net/equipe',
            'contact_url'  => 'https://www.influencia.net/contact',
            'media_type'   => 'web',
            'topics'       => ['business', 'entrepreneuriat', 'tech'],
        ],
        [
            'name'         => 'Le Figaro Économie',
            'base_url'     => 'https://www.lefigaro.fr/economie',
            'team_url'     => 'https://www.lefigaro.fr/redaction',
            'contact_url'  => 'https://www.lefigaro.fr/contact',
            'media_type'   => 'presse_ecrite',
            'topics'       => ['business', 'entrepreneuriat', 'international'],
        ],
        [
            'name'         => 'Welcome to the Jungle',
            'base_url'     => 'https://www.welcometothejungle.com',
            'team_url'     => 'https://www.welcometothejungle.com/fr/articles',
            'contact_url'  => 'https://www.welcometothejungle.com/fr/contact',
            'media_type'   => 'web',
            'topics'       => ['entrepreneuriat', 'tech', 'lifestyle'],
        ],
        [
            'name'         => 'Startup & Co',
            'base_url'     => 'https://www.startupandco.fr',
            'team_url'     => 'https://www.startupandco.fr/equipe',
            'contact_url'  => 'https://www.startupandco.fr/contact',
            'media_type'   => 'web',
            'topics'       => ['entrepreneuriat', 'startup'],
        ],
        [
            'name'         => 'Le Journal du Net',
            'base_url'     => 'https://www.journaldunet.com',
            'team_url'     => 'https://www.journaldunet.com/redaction',
            'contact_url'  => 'https://www.journaldunet.com/contact',
            'media_type'   => 'web',
            'topics'       => ['tech', 'business', 'entrepreneuriat'],
        ],
        [
            'name'         => 'Siècle Digital',
            'base_url'     => 'https://siecledigital.fr',
            'team_url'     => 'https://siecledigital.fr/equipe',
            'contact_url'  => 'https://siecledigital.fr/contact',
            'media_type'   => 'web',
            'topics'       => ['tech', 'entrepreneuriat'],
        ],

        // ─── VOYAGE & TOURISME ─────────────────────────────────────────────
        [
            'name'         => 'GEO Magazine',
            'base_url'     => 'https://www.geo.fr',
            'team_url'     => 'https://www.geo.fr/redaction',
            'contact_url'  => 'https://www.geo.fr/contact',
            'media_type'   => 'presse_ecrite',
            'topics'       => ['voyage', 'international', 'lifestyle'],
        ],
        [
            'name'         => 'National Geographic France',
            'base_url'     => 'https://www.nationalgeographic.fr',
            'team_url'     => 'https://www.nationalgeographic.fr/redaction',
            'contact_url'  => 'https://www.nationalgeographic.fr/contact',
            'media_type'   => 'presse_ecrite',
            'topics'       => ['voyage', 'international', 'lifestyle'],
        ],
        [
            'name'         => 'Le Routard',
            'base_url'     => 'https://www.routard.com',
            'team_url'     => 'https://www.routard.com/equipe',
            'contact_url'  => 'https://www.routard.com/contact',
            'media_type'   => 'web',
            'topics'       => ['voyage', 'international', 'expatriation'],
        ],
        [
            'name'         => 'Petit Futé',
            'base_url'     => 'https://www.petitfute.com',
            'team_url'     => 'https://www.petitfute.com/presse',
            'contact_url'  => 'https://www.petitfute.com/contact',
            'media_type'   => 'presse_ecrite',
            'topics'       => ['voyage', 'international', 'expatriation'],
        ],
        [
            'name'         => "L'Écho Touristique",
            'base_url'     => 'https://www.lechotouristique.com',
            'team_url'     => 'https://www.lechotouristique.com/equipe',
            'contact_url'  => 'https://www.lechotouristique.com/contact',
            'media_type'   => 'web',
            'topics'       => ['voyage', 'business'],
        ],
        [
            'name'         => 'Partir Magazine',
            'base_url'     => 'https://www.partir.com',
            'team_url'     => 'https://www.partir.com/editorial',
            'contact_url'  => 'https://www.partir.com/contact',
            'media_type'   => 'presse_ecrite',
            'topics'       => ['voyage', 'international'],
        ],
        [
            'name'         => 'Voyage de Luxe',
            'base_url'     => 'https://www.voyagedeluxe.com',
            'team_url'     => 'https://www.voyagedeluxe.com/equipe',
            'contact_url'  => 'https://www.voyagedeluxe.com/contact',
            'media_type'   => 'web',
            'topics'       => ['voyage', 'lifestyle'],
        ],
        [
            'name'         => 'Le Figaro Voyages',
            'base_url'     => 'https://voyage.lefigaro.fr',
            'team_url'     => 'https://www.lefigaro.fr/redaction',
            'contact_url'  => 'https://voyage.lefigaro.fr/contact',
            'media_type'   => 'presse_ecrite',
            'topics'       => ['voyage', 'international', 'lifestyle'],
        ],
        [
            'name'         => 'Madame Figaro',
            'base_url'     => 'https://madame.lefigaro.fr',
            'team_url'     => 'https://madame.lefigaro.fr/redaction',
            'contact_url'  => 'https://madame.lefigaro.fr/contact',
            'media_type'   => 'presse_ecrite',
            'topics'       => ['voyage', 'lifestyle', 'international'],
        ],
        [
            'name'         => 'Nomade Digital',
            'base_url'     => 'https://www.nomadedigital.fr',
            'team_url'     => 'https://www.nomadedigital.fr/equipe',
            'contact_url'  => 'https://www.nomadedigital.fr/contact',
            'media_type'   => 'web',
            'topics'       => ['voyage', 'entrepreneuriat', 'expatriation'],
        ],
        [
            'name'         => 'Tourmag',
            'base_url'     => 'https://www.tourmag.com',
            'team_url'     => 'https://www.tourmag.com/la-redaction',
            'contact_url'  => 'https://www.tourmag.com/contact',
            'media_type'   => 'web',
            'topics'       => ['voyage', 'business'],
        ],
        [
            'name'         => 'Travel & Leisure France',
            'base_url'     => 'https://www.travelandleisure.fr',
            'team_url'     => 'https://www.travelandleisure.fr/equipe',
            'contact_url'  => 'https://www.travelandleisure.fr/contact',
            'media_type'   => 'presse_ecrite',
            'topics'       => ['voyage', 'lifestyle', 'international'],
        ],
        [
            'name'         => 'Voyages-SNCF Magazine',
            'base_url'     => 'https://www.oui.sncf/magazine',
            'team_url'     => 'https://www.oui.sncf/magazine/equipe',
            'contact_url'  => 'https://www.oui.sncf/magazine/contact',
            'media_type'   => 'web',
            'topics'       => ['voyage', 'lifestyle'],
        ],
        [
            'name'         => 'Globe Trekker / Aventure du Bout du Monde',
            'base_url'     => 'https://www.aventureduboutdumonde.fr',
            'team_url'     => 'https://www.aventureduboutdumonde.fr/equipe',
            'contact_url'  => 'https://www.aventureduboutdumonde.fr/contact',
            'media_type'   => 'presse_ecrite',
            'topics'       => ['voyage', 'international', 'expatriation'],
        ],

        // ─── EXPATRIATION & FRANÇAIS À L'ÉTRANGER ─────────────────────────
        [
            'name'         => 'Le Petit Journal',
            'base_url'     => 'https://lepetitjournal.com',
            'team_url'     => 'https://lepetitjournal.com/la-redaction',
            'contact_url'  => 'https://lepetitjournal.com/contact',
            'media_type'   => 'web',
            'topics'       => ['expatriation', 'international', 'voyage'],
        ],
        [
            'name'         => "Français à l'Étranger",
            'base_url'     => 'https://www.francaisaletranger.fr',
            'team_url'     => 'https://www.francaisaletranger.fr/equipe',
            'contact_url'  => 'https://www.francaisaletranger.fr/contact',
            'media_type'   => 'web',
            'topics'       => ['expatriation', 'international'],
        ],
        [
            'name'         => 'French Morning',
            'base_url'     => 'https://frenchmorning.com',
            'team_url'     => 'https://frenchmorning.com/about',
            'contact_url'  => 'https://frenchmorning.com/contact',
            'media_type'   => 'web',
            'topics'       => ['expatriation', 'international', 'lifestyle'],
        ],
        [
            'name'         => 'Expat.com Magazine',
            'base_url'     => 'https://www.expat.com',
            'team_url'     => 'https://www.expat.com/fr/contact/equipe.php',
            'contact_url'  => 'https://www.expat.com/fr/contact',
            'media_type'   => 'web',
            'topics'       => ['expatriation', 'international', 'voyage'],
        ],
        [
            'name'         => "La Gazette de l'Expat",
            'base_url'     => 'https://www.gazetteexpatriee.fr',
            'team_url'     => 'https://www.gazetteexpatriee.fr/equipe',
            'contact_url'  => 'https://www.gazetteexpatriee.fr/contact',
            'media_type'   => 'web',
            'topics'       => ['expatriation', 'international'],
        ],
        [
            'name'         => "Côté Expat",
            'base_url'     => 'https://www.coteexpat.fr',
            'team_url'     => 'https://www.coteexpat.fr/a-propos',
            'contact_url'  => 'https://www.coteexpat.fr/contact',
            'media_type'   => 'web',
            'topics'       => ['expatriation', 'international', 'lifestyle'],
        ],
        [
            'name'         => 'InterNations Magazine',
            'base_url'     => 'https://www.internations.org',
            'team_url'     => 'https://www.internations.org/about',
            'contact_url'  => 'https://www.internations.org/contact',
            'media_type'   => 'web',
            'topics'       => ['expatriation', 'international', 'lifestyle'],
        ],
        [
            'name'         => 'MFE (Maison des Français de l\'Étranger)',
            'base_url'     => 'https://mfe.org',
            'team_url'     => 'https://mfe.org/qui-sommes-nous',
            'contact_url'  => 'https://mfe.org/contact',
            'media_type'   => 'web',
            'topics'       => ['expatriation', 'international'],
        ],
        [
            'name'         => 'Vivre à l\'Étranger (Le Figaro)',
            'base_url'     => 'https://www.lefigaro.fr/expatriation',
            'team_url'     => 'https://www.lefigaro.fr/redaction',
            'contact_url'  => 'https://www.lefigaro.fr/contact',
            'media_type'   => 'presse_ecrite',
            'topics'       => ['expatriation', 'international', 'business'],
        ],
        [
            'name'         => 'Partir Vivre à l\'Étranger',
            'base_url'     => 'https://www.partirvivrealletranger.com',
            'team_url'     => 'https://www.partirvivrealletranger.com/equipe',
            'contact_url'  => 'https://www.partirvivrealletranger.com/contact',
            'media_type'   => 'web',
            'topics'       => ['expatriation', 'voyage', 'international'],
        ],

        // ─── INTERNATIONAL / GÉOPOLITIQUE ─────────────────────────────────
        [
            'name'         => 'Courrier International',
            'base_url'     => 'https://www.courrierinternational.com',
            'team_url'     => 'https://www.courrierinternational.com/la-redaction',
            'contact_url'  => 'https://www.courrierinternational.com/contact',
            'media_type'   => 'presse_ecrite',
            'topics'       => ['international', 'business', 'voyage'],
        ],
        [
            'name'         => 'Le Monde International',
            'base_url'     => 'https://www.lemonde.fr/international',
            'team_url'     => 'https://www.lemonde.fr/la-redaction',
            'contact_url'  => 'https://www.lemonde.fr/contact',
            'media_type'   => 'presse_ecrite',
            'topics'       => ['international', 'business', 'expatriation'],
        ],
        [
            'name'         => "L'Obs Monde",
            'base_url'     => 'https://www.nouvelobs.com/monde',
            'team_url'     => 'https://www.nouvelobs.com/la-redaction',
            'contact_url'  => 'https://www.nouvelobs.com/contact',
            'media_type'   => 'presse_ecrite',
            'topics'       => ['international', 'business'],
        ],
        [
            'name'         => 'Le Point International',
            'base_url'     => 'https://www.lepoint.fr/monde',
            'team_url'     => 'https://www.lepoint.fr/la-redaction',
            'contact_url'  => 'https://www.lepoint.fr/contact',
            'media_type'   => 'presse_ecrite',
            'topics'       => ['international', 'business', 'expatriation'],
        ],
        [
            'name'         => 'Mondafrique',
            'base_url'     => 'https://mondafrique.com',
            'team_url'     => 'https://mondafrique.com/equipe',
            'contact_url'  => 'https://mondafrique.com/contact',
            'media_type'   => 'web',
            'topics'       => ['international', 'business', 'expatriation'],
        ],
        [
            'name'         => 'The Good Life',
            'base_url'     => 'https://www.thegoodlife.fr',
            'team_url'     => 'https://www.thegoodlife.fr/equipe',
            'contact_url'  => 'https://www.thegoodlife.fr/contact',
            'media_type'   => 'web',
            'topics'       => ['international', 'lifestyle', 'voyage'],
        ],

        // ─── TV & CHAÎNES D'INFO ───────────────────────────────────────────
        [
            'name'         => 'France 24',
            'base_url'     => 'https://www.france24.com',
            'team_url'     => 'https://www.france24.com/fr/equipe',
            'contact_url'  => 'https://www.france24.com/fr/contact',
            'media_type'   => 'tv',
            'topics'       => ['international', 'business', 'voyage', 'expatriation'],
        ],
        [
            'name'         => 'BFM Business',
            'base_url'     => 'https://bfmbusiness.bfmtv.com',
            'team_url'     => 'https://bfmbusiness.bfmtv.com/notre-equipe',
            'contact_url'  => 'https://bfmbusiness.bfmtv.com/contact',
            'media_type'   => 'tv',
            'topics'       => ['business', 'entrepreneuriat', 'international'],
        ],
        [
            'name'         => 'BFM TV',
            'base_url'     => 'https://www.bfmtv.com',
            'team_url'     => 'https://www.bfmtv.com/notre-equipe',
            'contact_url'  => 'https://www.bfmtv.com/contact',
            'media_type'   => 'tv',
            'topics'       => ['international', 'business', 'entrepreneuriat'],
        ],
        [
            'name'         => 'LCI',
            'base_url'     => 'https://www.lci.fr',
            'team_url'     => 'https://www.lci.fr/la-redaction',
            'contact_url'  => 'https://www.lci.fr/contact',
            'media_type'   => 'tv',
            'topics'       => ['international', 'business', 'voyage'],
        ],
        [
            'name'         => 'CNews',
            'base_url'     => 'https://www.cnews.fr',
            'team_url'     => 'https://www.cnews.fr/nos-equipes',
            'contact_url'  => 'https://www.cnews.fr/contact',
            'media_type'   => 'tv',
            'topics'       => ['international', 'business'],
        ],
        [
            'name'         => 'France 5 (Émissions Voyage)',
            'base_url'     => 'https://www.france.tv/france-5',
            'team_url'     => 'https://www.france.tv/france-5/emissions',
            'contact_url'  => 'https://www.france.tv/contact',
            'media_type'   => 'tv',
            'topics'       => ['voyage', 'international', 'expatriation'],
        ],
        [
            'name'         => 'TV5 Monde',
            'base_url'     => 'https://www.tv5monde.com',
            'team_url'     => 'https://www.tv5monde.com/nous-connaitre',
            'contact_url'  => 'https://www.tv5monde.com/contact',
            'media_type'   => 'tv',
            'topics'       => ['international', 'voyage', 'expatriation'],
        ],
        [
            'name'         => 'Arte',
            'base_url'     => 'https://www.arte.tv',
            'team_url'     => 'https://www.arte.tv/fr/arte-info/presse',
            'contact_url'  => 'https://www.arte.tv/fr/contact',
            'media_type'   => 'tv',
            'topics'       => ['international', 'voyage', 'lifestyle'],
        ],

        // ─── RADIO ────────────────────────────────────────────────────────
        [
            'name'         => 'RFI (Radio France Internationale)',
            'base_url'     => 'https://www.rfi.fr',
            'team_url'     => 'https://www.rfi.fr/fr/equipe-redactionnelle',
            'contact_url'  => 'https://www.rfi.fr/fr/contact',
            'media_type'   => 'radio',
            'topics'       => ['international', 'business', 'expatriation', 'voyage'],
        ],
        [
            'name'         => 'France Inter',
            'base_url'     => 'https://www.radiofrance.fr/franceinter',
            'team_url'     => 'https://www.radiofrance.fr/franceinter/la-redaction',
            'contact_url'  => 'https://www.radiofrance.fr/franceinter/contact',
            'media_type'   => 'radio',
            'topics'       => ['international', 'business', 'entrepreneuriat'],
        ],
        [
            'name'         => 'France Culture',
            'base_url'     => 'https://www.radiofrance.fr/franceculture',
            'team_url'     => 'https://www.radiofrance.fr/franceculture/equipe',
            'contact_url'  => 'https://www.radiofrance.fr/franceculture/contact',
            'media_type'   => 'radio',
            'topics'       => ['international', 'voyage', 'lifestyle'],
        ],
        [
            'name'         => 'Europe 1',
            'base_url'     => 'https://www.europe1.fr',
            'team_url'     => 'https://www.europe1.fr/la-redaction',
            'contact_url'  => 'https://www.europe1.fr/contact',
            'media_type'   => 'radio',
            'topics'       => ['business', 'entrepreneuriat', 'international'],
        ],
        [
            'name'         => 'RTL',
            'base_url'     => 'https://www.rtl.fr',
            'team_url'     => 'https://www.rtl.fr/la-redaction',
            'contact_url'  => 'https://www.rtl.fr/contact',
            'media_type'   => 'radio',
            'topics'       => ['business', 'voyage', 'international'],
        ],
        [
            'name'         => 'BFM Business Radio',
            'base_url'     => 'https://bfmbusiness.bfmtv.com/radio',
            'team_url'     => 'https://bfmbusiness.bfmtv.com/radio/equipe',
            'contact_url'  => 'https://bfmbusiness.bfmtv.com/contact',
            'media_type'   => 'radio',
            'topics'       => ['business', 'entrepreneuriat'],
        ],

        // ─── PRESSE RÉGIONALE / COMMUNAUTÉS EXPAT ─────────────────────────
        [
            'name'         => 'Le Courrier des Amériques',
            'base_url'     => 'https://www.lecourrierdesameriques.com',
            'team_url'     => 'https://www.lecourrierdesameriques.com/equipe',
            'contact_url'  => 'https://www.lecourrierdesameriques.com/contact',
            'media_type'   => 'web',
            'topics'       => ['expatriation', 'international'],
        ],
        [
            'name'         => 'Connexion France (UK)',
            'base_url'     => 'https://www.connexionfrance.com',
            'team_url'     => 'https://www.connexionfrance.com/about',
            'contact_url'  => 'https://www.connexionfrance.com/contact',
            'media_type'   => 'presse_ecrite',
            'topics'       => ['expatriation', 'international', 'lifestyle'],
            'country'      => 'Royaume-Uni',
        ],
        [
            'name'         => 'French District (USA)',
            'base_url'     => 'https://www.frenchdistrict.com',
            'team_url'     => 'https://www.frenchdistrict.com/about',
            'contact_url'  => 'https://www.frenchdistrict.com/contact',
            'media_type'   => 'web',
            'topics'       => ['expatriation', 'international', 'voyage'],
            'country'      => 'USA',
        ],
        [
            'name'         => 'Journal des Francophones (Belgique)',
            'base_url'     => 'https://www.journaldesfrancophones.be',
            'team_url'     => 'https://www.journaldesfrancophones.be/equipe',
            'contact_url'  => 'https://www.journaldesfrancophones.be/contact',
            'media_type'   => 'web',
            'topics'       => ['international', 'expatriation'],
            'country'      => 'Belgique',
        ],

        // ─── ANNUAIRES PRESSE / DIRECTORIES ───────────────────────────────
        [
            'name'         => 'Annuaire Journaliste.fr',
            'base_url'     => 'https://annuaire.journaliste.fr',
            'team_url'     => 'https://annuaire.journaliste.fr/liste-des-journalistes',
            'contact_url'  => 'https://annuaire.journaliste.fr/contact',
            'media_type'   => 'web',
            'topics'       => ['entrepreneuriat', 'voyage', 'international', 'expatriation'],
        ],
        [
            'name'         => 'Lanetworkerie Répertoire Médias',
            'base_url'     => 'https://www.lanetworkerie.com',
            'team_url'     => 'https://www.lanetworkerie.com/repertoire-medias',
            'contact_url'  => 'https://www.lanetworkerie.com/contact',
            'media_type'   => 'web',
            'topics'       => ['entrepreneuriat', 'international', 'tech'],
        ],
    ];

    public function handle(): int
    {
        $reset = $this->option('reset');

        if ($reset) {
            PressPublication::truncate();
            $this->info('Existing publications cleared.');
        }

        $inserted = 0;
        $skipped  = 0;

        foreach ($this->publications as $pub) {
            $slug = Str::slug($pub['name']);
            $exists = PressPublication::where('slug', $slug)->exists();

            if ($exists && !$reset) {
                $skipped++;
                continue;
            }

            PressPublication::updateOrCreate(
                ['slug' => $slug],
                [
                    'name'        => $pub['name'],
                    'slug'        => $slug,
                    'base_url'    => $pub['base_url'],
                    'team_url'    => $pub['team_url'] ?? null,
                    'contact_url' => $pub['contact_url'] ?? null,
                    'media_type'  => $pub['media_type'] ?? 'web',
                    'topics'      => $pub['topics'] ?? [],
                    'language'    => $pub['language'] ?? 'fr',
                    'country'     => $pub['country'] ?? 'France',
                    'status'      => 'pending',
                ]
            );
            $inserted++;
        }

        $this->info("Publications: {$inserted} insérées / mise à jour, {$skipped} ignorées (déjà existantes).");
        $this->info("Total en base: " . PressPublication::count());
        return Command::SUCCESS;
    }
}
