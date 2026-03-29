<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WikidataService — Récupère les ambassades et consulats depuis Wikidata (SPARQL).
 *
 * Couverture : 195 pays (tous les membres de l'ONU + Kosovo, Palestine, Taïwan)
 * Langues    : FR, EN, ES, AR, DE, PT
 * Sources    : https://query.wikidata.org/sparql
 */
class WikidataService
{
    const SPARQL_ENDPOINT = 'https://query.wikidata.org/sparql';
    const USER_AGENT      = 'MissionControlSosExpat/1.0 (contact@sos-expat.com)';

    // ── ISO 3166-1 alpha-2 → Wikidata QID ────────────────────────────────────

    const COUNTRY_QID = [
        'AF' => 'Q889',  'AL' => 'Q222',  'DZ' => 'Q262',  'AD' => 'Q228',  'AO' => 'Q916',
        'AG' => 'Q781',  'AR' => 'Q414',  'AM' => 'Q399',  'AU' => 'Q408',  'AT' => 'Q40',
        'AZ' => 'Q227',  'BS' => 'Q778',  'BH' => 'Q398',  'BD' => 'Q902',  'BB' => 'Q244',
        'BY' => 'Q184',  'BE' => 'Q31',   'BZ' => 'Q242',  'BJ' => 'Q962',  'BT' => 'Q917',
        'BO' => 'Q750',  'BA' => 'Q225',  'BW' => 'Q963',  'BR' => 'Q155',  'BN' => 'Q921',
        'BG' => 'Q219',  'BF' => 'Q965',  'BI' => 'Q967',  'CV' => 'Q1011', 'KH' => 'Q424',
        'CM' => 'Q1009', 'CA' => 'Q16',   'CF' => 'Q929',  'TD' => 'Q657',  'CL' => 'Q298',
        'CN' => 'Q148',  'CO' => 'Q739',  'KM' => 'Q970',  'CG' => 'Q971',  'CD' => 'Q974',
        'CR' => 'Q800',  'HR' => 'Q224',  'CU' => 'Q241',  'CY' => 'Q229',  'CZ' => 'Q213',
        'DK' => 'Q35',   'DJ' => 'Q977',  'DM' => 'Q784',  'DO' => 'Q786',  'EC' => 'Q736',
        'EG' => 'Q79',   'SV' => 'Q792',  'GQ' => 'Q983',  'ER' => 'Q986',  'EE' => 'Q191',
        'SZ' => 'Q1050', 'ET' => 'Q115',  'FJ' => 'Q712',  'FI' => 'Q33',   'FR' => 'Q142',
        'GA' => 'Q1000', 'GM' => 'Q1005', 'GE' => 'Q230',  'DE' => 'Q183',  'GH' => 'Q117',
        'GR' => 'Q41',   'GD' => 'Q769',  'GT' => 'Q774',  'GN' => 'Q1006', 'GW' => 'Q1007',
        'GY' => 'Q734',  'HT' => 'Q790',  'HN' => 'Q783',  'HU' => 'Q28',   'IS' => 'Q189',
        'IN' => 'Q668',  'ID' => 'Q252',  'IR' => 'Q794',  'IQ' => 'Q796',  'IE' => 'Q27',
        'IL' => 'Q801',  'IT' => 'Q38',   'JM' => 'Q766',  'JP' => 'Q17',   'JO' => 'Q810',
        'KZ' => 'Q232',  'KE' => 'Q114',  'KI' => 'Q710',  'KP' => 'Q423',  'KR' => 'Q884',
        'KW' => 'Q817',  'KG' => 'Q813',  'LA' => 'Q819',  'LV' => 'Q211',  'LB' => 'Q822',
        'LS' => 'Q1013', 'LR' => 'Q1014', 'LY' => 'Q1016', 'LI' => 'Q347',  'LT' => 'Q37',
        'LU' => 'Q32',   'MG' => 'Q1019', 'MW' => 'Q1020', 'MY' => 'Q833',  'MV' => 'Q826',
        'ML' => 'Q912',  'MT' => 'Q233',  'MH' => 'Q709',  'MR' => 'Q1025', 'MU' => 'Q1027',
        'MX' => 'Q96',   'FM' => 'Q702',  'MD' => 'Q217',  'MC' => 'Q235',  'MN' => 'Q711',
        'ME' => 'Q236',  'MA' => 'Q1028', 'MZ' => 'Q1029', 'MM' => 'Q836',  'NA' => 'Q1030',
        'NR' => 'Q697',  'NP' => 'Q837',  'NL' => 'Q55',   'NZ' => 'Q664',  'NI' => 'Q811',
        'NE' => 'Q1032', 'NG' => 'Q1033', 'MK' => 'Q221',  'NO' => 'Q20',   'OM' => 'Q842',
        'PK' => 'Q843',  'PW' => 'Q695',  'PA' => 'Q804',  'PG' => 'Q691',  'PY' => 'Q733',
        'PE' => 'Q419',  'PH' => 'Q928',  'PL' => 'Q36',   'PT' => 'Q45',   'QA' => 'Q846',
        'RO' => 'Q218',  'RU' => 'Q159',  'RW' => 'Q1037', 'KN' => 'Q763',  'LC' => 'Q760',
        'VC' => 'Q757',  'WS' => 'Q683',  'SM' => 'Q238',  'ST' => 'Q1039', 'SA' => 'Q851',
        'SN' => 'Q1041', 'RS' => 'Q403',  'SC' => 'Q1042', 'SL' => 'Q1044', 'SG' => 'Q334',
        'SK' => 'Q214',  'SI' => 'Q215',  'SB' => 'Q685',  'SO' => 'Q1045', 'ZA' => 'Q258',
        'SS' => 'Q958',  'ES' => 'Q29',   'LK' => 'Q854',  'SD' => 'Q1049', 'SR' => 'Q730',
        'SE' => 'Q34',   'CH' => 'Q39',   'SY' => 'Q858',  'TW' => 'Q865',  'TJ' => 'Q863',
        'TZ' => 'Q924',  'TH' => 'Q869',  'TL' => 'Q574',  'TG' => 'Q945',  'TO' => 'Q678',
        'TT' => 'Q754',  'TN' => 'Q948',  'TR' => 'Q43',   'TM' => 'Q874',  'TV' => 'Q672',
        'UG' => 'Q1036', 'UA' => 'Q212',  'AE' => 'Q878',  'GB' => 'Q145',  'US' => 'Q30',
        'UY' => 'Q77',   'UZ' => 'Q265',  'VU' => 'Q686',  'VE' => 'Q717',  'VN' => 'Q881',
        'YE' => 'Q805',  'ZM' => 'Q953',  'ZW' => 'Q954',  'CI' => 'Q1008', 'PS' => 'Q219060',
        'XK' => 'Q1246',
    ];

    // ── Noms des pays en français ─────────────────────────────────────────────

    const COUNTRY_NAMES_FR = [
        'AF' => 'Afghanistan',          'AL' => 'Albanie',               'DZ' => 'Algérie',
        'AD' => 'Andorre',              'AO' => 'Angola',                'AG' => 'Antigua-et-Barbuda',
        'AR' => 'Argentine',            'AM' => 'Arménie',               'AU' => 'Australie',
        'AT' => 'Autriche',             'AZ' => 'Azerbaïdjan',           'BS' => 'Bahamas',
        'BH' => 'Bahreïn',              'BD' => 'Bangladesh',            'BB' => 'Barbade',
        'BY' => 'Biélorussie',          'BE' => 'Belgique',              'BZ' => 'Belize',
        'BJ' => 'Bénin',               'BT' => 'Bhoutan',               'BO' => 'Bolivie',
        'BA' => 'Bosnie-Herzégovine',   'BW' => 'Botswana',              'BR' => 'Brésil',
        'BN' => 'Brunéi',              'BG' => 'Bulgarie',              'BF' => 'Burkina Faso',
        'BI' => 'Burundi',              'CV' => 'Cap-Vert',              'KH' => 'Cambodge',
        'CM' => 'Cameroun',             'CA' => 'Canada',                'CF' => 'Rép. centrafricaine',
        'TD' => 'Tchad',               'CL' => 'Chili',                 'CN' => 'Chine',
        'CO' => 'Colombie',             'KM' => 'Comores',               'CG' => 'Congo',
        'CD' => 'RD Congo',             'CR' => 'Costa Rica',            'HR' => 'Croatie',
        'CU' => 'Cuba',                'CY' => 'Chypre',                'CZ' => 'Tchéquie',
        'DK' => 'Danemark',             'DJ' => 'Djibouti',              'DM' => 'Dominique',
        'DO' => 'Rép. dominicaine',     'EC' => 'Équateur',              'EG' => 'Égypte',
        'SV' => 'El Salvador',          'GQ' => 'Guinée équatoriale',    'ER' => 'Érythrée',
        'EE' => 'Estonie',              'SZ' => 'Eswatini',              'ET' => 'Éthiopie',
        'FJ' => 'Fidji',               'FI' => 'Finlande',              'FR' => 'France',
        'GA' => 'Gabon',               'GM' => 'Gambie',                'GE' => 'Géorgie',
        'DE' => 'Allemagne',            'GH' => 'Ghana',                 'GR' => 'Grèce',
        'GD' => 'Grenade',             'GT' => 'Guatemala',             'GN' => 'Guinée',
        'GW' => 'Guinée-Bissau',        'GY' => 'Guyana',                'HT' => 'Haïti',
        'HN' => 'Honduras',             'HU' => 'Hongrie',               'IS' => 'Islande',
        'IN' => 'Inde',                'ID' => 'Indonésie',             'IR' => 'Iran',
        'IQ' => 'Irak',                'IE' => 'Irlande',               'IL' => 'Israël',
        'IT' => 'Italie',              'JM' => 'Jamaïque',              'JP' => 'Japon',
        'JO' => 'Jordanie',             'KZ' => 'Kazakhstan',            'KE' => 'Kenya',
        'KI' => 'Kiribati',             'KP' => 'Corée du Nord',         'KR' => 'Corée du Sud',
        'KW' => 'Koweït',              'KG' => 'Kirghizstan',           'LA' => 'Laos',
        'LV' => 'Lettonie',             'LB' => 'Liban',                 'LS' => 'Lesotho',
        'LR' => 'Libéria',             'LY' => 'Libye',                 'LI' => 'Liechtenstein',
        'LT' => 'Lituanie',             'LU' => 'Luxembourg',            'MG' => 'Madagascar',
        'MW' => 'Malawi',              'MY' => 'Malaisie',              'MV' => 'Maldives',
        'ML' => 'Mali',                'MT' => 'Malte',                 'MH' => 'Îles Marshall',
        'MR' => 'Mauritanie',           'MU' => 'Maurice',               'MX' => 'Mexique',
        'FM' => 'Micronésie',           'MD' => 'Moldavie',              'MC' => 'Monaco',
        'MN' => 'Mongolie',             'ME' => 'Monténégro',            'MA' => 'Maroc',
        'MZ' => 'Mozambique',           'MM' => 'Myanmar',               'NA' => 'Namibie',
        'NR' => 'Nauru',               'NP' => 'Népal',                 'NL' => 'Pays-Bas',
        'NZ' => 'Nouvelle-Zélande',     'NI' => 'Nicaragua',             'NE' => 'Niger',
        'NG' => 'Nigeria',              'MK' => 'Macédoine du Nord',     'NO' => 'Norvège',
        'OM' => 'Oman',                'PK' => 'Pakistan',              'PW' => 'Palaos',
        'PA' => 'Panama',              'PG' => 'Papouasie-Nvlle-Guinée', 'PY' => 'Paraguay',
        'PE' => 'Pérou',               'PH' => 'Philippines',           'PL' => 'Pologne',
        'PT' => 'Portugal',             'QA' => 'Qatar',                 'RO' => 'Roumanie',
        'RU' => 'Russie',              'RW' => 'Rwanda',                'KN' => 'Saint-Christophe',
        'LC' => 'Sainte-Lucie',         'VC' => 'Saint-Vincent',         'WS' => 'Samoa',
        'SM' => 'Saint-Marin',          'ST' => 'Sao Tomé-et-Principe',  'SA' => 'Arabie saoudite',
        'SN' => 'Sénégal',             'RS' => 'Serbie',                'SC' => 'Seychelles',
        'SL' => 'Sierra Leone',         'SG' => 'Singapour',             'SK' => 'Slovaquie',
        'SI' => 'Slovénie',             'SB' => 'Îles Salomon',          'SO' => 'Somalie',
        'ZA' => 'Afrique du Sud',       'SS' => 'Soudan du Sud',         'ES' => 'Espagne',
        'LK' => 'Sri Lanka',            'SD' => 'Soudan',                'SR' => 'Suriname',
        'SE' => 'Suède',               'CH' => 'Suisse',                'SY' => 'Syrie',
        'TW' => 'Taïwan',              'TJ' => 'Tadjikistan',           'TZ' => 'Tanzanie',
        'TH' => 'Thaïlande',            'TL' => 'Timor oriental',        'TG' => 'Togo',
        'TO' => 'Tonga',               'TT' => 'Trinité-et-Tobago',     'TN' => 'Tunisie',
        'TR' => 'Turquie',              'TM' => 'Turkménistan',          'TV' => 'Tuvalu',
        'UG' => 'Ouganda',              'UA' => 'Ukraine',               'AE' => 'Émirats arabes unis',
        'GB' => 'Royaume-Uni',          'US' => 'États-Unis',            'UY' => 'Uruguay',
        'UZ' => 'Ouzbékistan',          'VU' => 'Vanuatu',               'VE' => 'Venezuela',
        'VN' => 'Viêt Nam',             'YE' => 'Yémen',                 'ZM' => 'Zambie',
        'ZW' => 'Zimbabwe',             'CI' => "Côte d'Ivoire",         'PS' => 'Palestine',
        'XK' => 'Kosovo',
    ];

    // ── Mapping ISO → Continent ───────────────────────────────────────────────

    const COUNTRY_CONTINENT = [
        // Europe
        'AL' => 'europe', 'AD' => 'europe', 'AM' => 'europe', 'AT' => 'europe', 'AZ' => 'europe',
        'BY' => 'europe', 'BE' => 'europe', 'BA' => 'europe', 'BG' => 'europe', 'HR' => 'europe',
        'CY' => 'europe', 'CZ' => 'europe', 'DK' => 'europe', 'EE' => 'europe', 'FI' => 'europe',
        'FR' => 'europe', 'GE' => 'europe', 'DE' => 'europe', 'GR' => 'europe', 'HU' => 'europe',
        'IS' => 'europe', 'IE' => 'europe', 'IT' => 'europe', 'XK' => 'europe', 'LV' => 'europe',
        'LI' => 'europe', 'LT' => 'europe', 'LU' => 'europe', 'MT' => 'europe', 'MD' => 'europe',
        'MC' => 'europe', 'ME' => 'europe', 'MK' => 'europe', 'NL' => 'europe', 'NO' => 'europe',
        'PL' => 'europe', 'PT' => 'europe', 'RO' => 'europe', 'RU' => 'europe', 'SM' => 'europe',
        'RS' => 'europe', 'SK' => 'europe', 'SI' => 'europe', 'ES' => 'europe', 'SE' => 'europe',
        'CH' => 'europe', 'UA' => 'europe', 'GB' => 'europe',
        // Afrique
        'DZ' => 'afrique', 'AO' => 'afrique', 'BJ' => 'afrique', 'BW' => 'afrique', 'BF' => 'afrique',
        'BI' => 'afrique', 'CV' => 'afrique', 'CM' => 'afrique', 'CF' => 'afrique', 'TD' => 'afrique',
        'KM' => 'afrique', 'CG' => 'afrique', 'CD' => 'afrique', 'DJ' => 'afrique', 'EG' => 'afrique',
        'GQ' => 'afrique', 'ER' => 'afrique', 'SZ' => 'afrique', 'ET' => 'afrique', 'GA' => 'afrique',
        'GM' => 'afrique', 'GH' => 'afrique', 'GN' => 'afrique', 'GW' => 'afrique', 'CI' => 'afrique',
        'KE' => 'afrique', 'LS' => 'afrique', 'LR' => 'afrique', 'LY' => 'afrique', 'MG' => 'afrique',
        'MW' => 'afrique', 'ML' => 'afrique', 'MR' => 'afrique', 'MU' => 'afrique', 'MA' => 'afrique',
        'MZ' => 'afrique', 'NA' => 'afrique', 'NE' => 'afrique', 'NG' => 'afrique', 'RW' => 'afrique',
        'ST' => 'afrique', 'SN' => 'afrique', 'SC' => 'afrique', 'SL' => 'afrique', 'SO' => 'afrique',
        'ZA' => 'afrique', 'SS' => 'afrique', 'SD' => 'afrique', 'TZ' => 'afrique', 'TG' => 'afrique',
        'TN' => 'afrique', 'UG' => 'afrique', 'ZM' => 'afrique', 'ZW' => 'afrique',
        // Asie
        'AF' => 'asie', 'BH' => 'asie', 'BD' => 'asie', 'BT' => 'asie', 'BN' => 'asie',
        'KH' => 'asie', 'CN' => 'asie', 'IN' => 'asie', 'ID' => 'asie', 'IR' => 'asie',
        'IQ' => 'asie', 'IL' => 'asie', 'JP' => 'asie', 'JO' => 'asie', 'KZ' => 'asie',
        'KP' => 'asie', 'KR' => 'asie', 'KW' => 'asie', 'KG' => 'asie', 'LA' => 'asie',
        'LB' => 'asie', 'MY' => 'asie', 'MV' => 'asie', 'MN' => 'asie', 'MM' => 'asie',
        'NP' => 'asie', 'OM' => 'asie', 'PK' => 'asie', 'PS' => 'asie', 'PH' => 'asie',
        'QA' => 'asie', 'SA' => 'asie', 'SG' => 'asie', 'LK' => 'asie', 'SY' => 'asie',
        'TW' => 'asie', 'TJ' => 'asie', 'TH' => 'asie', 'TL' => 'asie', 'TR' => 'asie',
        'TM' => 'asie', 'AE' => 'asie', 'UZ' => 'asie', 'VN' => 'asie', 'YE' => 'asie',
        // Amérique du Nord + Caraïbes
        'AG' => 'amerique-nord', 'BS' => 'amerique-nord', 'BB' => 'amerique-nord', 'BZ' => 'amerique-nord',
        'CA' => 'amerique-nord', 'CR' => 'amerique-nord', 'CU' => 'amerique-nord', 'DM' => 'amerique-nord',
        'DO' => 'amerique-nord', 'SV' => 'amerique-nord', 'GD' => 'amerique-nord', 'GT' => 'amerique-nord',
        'HT' => 'amerique-nord', 'HN' => 'amerique-nord', 'JM' => 'amerique-nord', 'MX' => 'amerique-nord',
        'NI' => 'amerique-nord', 'PA' => 'amerique-nord', 'KN' => 'amerique-nord', 'LC' => 'amerique-nord',
        'VC' => 'amerique-nord', 'TT' => 'amerique-nord', 'US' => 'amerique-nord',
        // Amérique du Sud
        'AR' => 'amerique-sud', 'BO' => 'amerique-sud', 'BR' => 'amerique-sud', 'CL' => 'amerique-sud',
        'CO' => 'amerique-sud', 'EC' => 'amerique-sud', 'GY' => 'amerique-sud', 'PY' => 'amerique-sud',
        'PE' => 'amerique-sud', 'SR' => 'amerique-sud', 'UY' => 'amerique-sud', 'VE' => 'amerique-sud',
        // Océanie
        'AU' => 'oceanie', 'FJ' => 'oceanie', 'KI' => 'oceanie', 'MH' => 'oceanie', 'FM' => 'oceanie',
        'NR' => 'oceanie', 'NZ' => 'oceanie', 'PW' => 'oceanie', 'PG' => 'oceanie', 'WS' => 'oceanie',
        'SB' => 'oceanie', 'TO' => 'oceanie', 'TV' => 'oceanie', 'VU' => 'oceanie',
    ];

    // ── Méthodes publiques ────────────────────────────────────────────────────

    /**
     * Récupère toutes les ambassades/consulats opérés par un pays donné.
     *
     * @param string $nationalityIso  Code ISO 3166-1 alpha-2 (ex. 'DE')
     * @return array  Bindings SPARQL bruts
     * @throws \InvalidArgumentException si le code ISO n'est pas dans la carte
     * @throws \RuntimeException si la requête Wikidata échoue
     */
    public function getEmbassiesByNationality(string $nationalityIso): array
    {
        $qid = self::COUNTRY_QID[$nationalityIso] ?? null;
        if (!$qid) {
            throw new \InvalidArgumentException("Pas de QID Wikidata pour : {$nationalityIso}");
        }

        // Types Wikidata couverts :
        //   Q3917681 = ambassade, Q134830 = consulat, Q7843791 = consulat général
        //   Q4898743 = haute-commission (Commonwealth), Q16917 = mission diplomatique
        // Langues récupérées : fr, en, es, ar, de, pt, zh (→ ch dans le projet), hi, ru
        $query = <<<SPARQL
SELECT DISTINCT ?embassy
  ?labelFr ?labelEn ?labelEs ?labelAr ?labelDe ?labelPt ?labelZh ?labelHi ?labelRu
  ?hostCountry ?hostCountryCode ?hostCountryLabelFr
  ?website ?coord ?streetAddress ?phone ?email
WHERE {
  VALUES ?embassyType { wd:Q3917681 wd:Q134830 wd:Q7843791 wd:Q4898743 wd:Q16917 }
  ?embassy wdt:P31 ?embassyType .
  ?embassy wdt:P137 wd:{$qid} .
  ?embassy wdt:P17 ?hostCountry .
  ?hostCountry wdt:P297 ?hostCountryCode .

  OPTIONAL { ?embassy rdfs:label ?labelFr FILTER(LANG(?labelFr) = "fr") }
  OPTIONAL { ?embassy rdfs:label ?labelEn FILTER(LANG(?labelEn) = "en") }
  OPTIONAL { ?embassy rdfs:label ?labelEs FILTER(LANG(?labelEs) = "es") }
  OPTIONAL { ?embassy rdfs:label ?labelAr FILTER(LANG(?labelAr) = "ar") }
  OPTIONAL { ?embassy rdfs:label ?labelDe FILTER(LANG(?labelDe) = "de") }
  OPTIONAL { ?embassy rdfs:label ?labelPt FILTER(LANG(?labelPt) = "pt") }
  OPTIONAL { ?embassy rdfs:label ?labelZh FILTER(LANG(?labelZh) = "zh") }
  OPTIONAL { ?embassy rdfs:label ?labelHi FILTER(LANG(?labelHi) = "hi") }
  OPTIONAL { ?embassy rdfs:label ?labelRu FILTER(LANG(?labelRu) = "ru") }
  OPTIONAL { ?hostCountry rdfs:label ?hostCountryLabelFr FILTER(LANG(?hostCountryLabelFr) = "fr") }
  OPTIONAL { ?embassy wdt:P856 ?website }
  OPTIONAL { ?embassy wdt:P625 ?coord }
  OPTIONAL { ?embassy wdt:P6375 ?streetAddress }
  OPTIONAL { ?embassy wdt:P1329 ?phone }
  OPTIONAL { ?embassy wdt:P968 ?email }
}
SPARQL;

        return $this->executeSparql($query);
    }

    /**
     * Normalise les bindings SPARQL bruts en tableau prêt pour CountryDirectory::upsert().
     */
    public function normalizeEmbassies(array $bindings, string $nationalityIso): array
    {
        $nationalityName = self::COUNTRY_NAMES_FR[$nationalityIso] ?? $nationalityIso;
        $seen = [];
        $embassies = [];

        foreach ($bindings as $row) {
            $get = fn(string $k) => $row[$k]['value'] ?? null;

            $hostCode = strtoupper($get('hostCountryCode') ?? '');
            if (strlen($hostCode) !== 2) continue;
            if ($hostCode === $nationalityIso)  continue; // pas d'ambassade chez soi

            $url = $get('website');
            if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) continue;

            // Déduplique par (hostCode, url)
            $key = $hostCode . '|' . $url;
            if (isset($seen[$key])) continue;
            $seen[$key] = true;

            $labelFr = $get('labelFr');
            $labelEn = $get('labelEn');

            $hostName = self::COUNTRY_NAMES_FR[$hostCode]
                ?? $get('hostCountryLabelFr')
                ?? $hostCode;

            $defaultTitle = $labelFr
                ?? $labelEn
                ?? "Ambassade de {$nationalityName} en {$hostName}";

            // Traductions dans les 9 langues du projet SOS Expat
            // Note : le projet utilise "ch" pour le chinois (code Wikidata : "zh")
            $translations = [];
            if ($labelEn && $labelEn !== $defaultTitle) $translations['en'] = ['title' => $labelEn];
            if ($get('labelEs'))  $translations['es'] = ['title' => $get('labelEs')];
            if ($get('labelAr'))  $translations['ar'] = ['title' => $get('labelAr')];
            if ($get('labelDe'))  $translations['de'] = ['title' => $get('labelDe')];
            if ($get('labelPt'))  $translations['pt'] = ['title' => $get('labelPt')];
            if ($get('labelZh'))  $translations['ch'] = ['title' => $get('labelZh')]; // "ch" = chinois dans le projet
            if ($get('labelHi'))  $translations['hi'] = ['title' => $get('labelHi')];
            if ($get('labelRu'))  $translations['ru'] = ['title' => $get('labelRu')];

            // Coordonnées GPS ("Point(lon lat)")
            $lat = null; $lon = null;
            if ($coord = $get('coord')) {
                if (preg_match('/Point\(([+-]?\d+\.?\d*)\s+([+-]?\d+\.?\d*)\)/', $coord, $m)) {
                    $lon = (float) $m[1];
                    $lat = (float) $m[2];
                }
            }

            $domain = parse_url($url, PHP_URL_HOST) ?: '';
            $domain = preg_replace('/^www\./', '', $domain);
            $continent = self::COUNTRY_CONTINENT[$hostCode] ?? 'autre';
            $slug = $this->makeSlug($hostName);

            $embassies[] = [
                'country_code'     => $hostCode,
                'country_name'     => $hostName,
                'country_slug'     => $slug,
                'continent'        => $continent,
                'nationality_code' => $nationalityIso,
                'nationality_name' => $nationalityName,
                'category'         => 'ambassade',
                'sub_category'     => 'ambassade',
                'title'            => $defaultTitle,
                'url'              => $url,
                'domain'           => $domain,
                'description'      => null,
                'language'         => 'fr',
                'translations'     => !empty($translations) ? $translations : null, // array — le model cast 'array' encode lui-même
                'address'          => $get('streetAddress'),
                'city'             => null,
                'phone'            => $get('phone'),
                'phone_emergency'  => null,
                'email'            => $get('email'),
                'opening_hours'    => null,
                'latitude'         => $lat,
                'longitude'        => $lon,
                'emergency_number' => null,
                'trust_score'      => 92,
                'is_official'      => true,
                'is_active'        => true,
                'anchor_text'      => strtolower("ambassade de {$nationalityName} en {$hostName}"),
                'rel_attribute'    => 'noopener',
            ];
        }

        return $embassies;
    }

    /**
     * Exécute une requête SPARQL sur le endpoint Wikidata.
     *
     * @throws \RuntimeException en cas d'erreur HTTP
     */
    public function executeSparql(string $query): array
    {
        $response = Http::withHeaders([
            'User-Agent' => self::USER_AGENT,
            'Accept'     => 'application/sparql-results+json',
        ])->timeout(90)->get(self::SPARQL_ENDPOINT, [
            'query'  => $query,
            'format' => 'json',
        ]);

        if (!$response->successful()) {
            Log::error('Wikidata SPARQL error', [
                'status' => $response->status(),
                'body'   => substr($response->body(), 0, 500),
            ]);
            throw new \RuntimeException("Wikidata SPARQL HTTP {$response->status()}");
        }

        return $response->json('results.bindings', []);
    }

    /**
     * Retourne tous les codes ISO supportés (avec QID connu).
     */
    public static function getSupportedIsoCodes(): array
    {
        return array_keys(self::COUNTRY_QID);
    }

    // ── Helpers privés ────────────────────────────────────────────────────────

    private function makeSlug(string $name): string
    {
        $map = [
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'à' => 'a', 'â' => 'a', 'ä' => 'a',
            'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ö' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', "'" => '-', "'" => '-',
        ];
        $name = strtolower(strtr($name, $map));
        $name = preg_replace('/[^a-z0-9\-]/', '-', $name);
        $name = preg_replace('/-+/', '-', $name);
        return trim($name, '-');
    }
}
