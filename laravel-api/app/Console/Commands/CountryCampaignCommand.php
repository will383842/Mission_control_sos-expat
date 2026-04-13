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
 * Country Campaign — Generate 240 articles for one country (200 SEO topical + 40 brand SOS-Expat.com),
 * then move to the next country. Covers all 197 countries (priority queue first, then random).
 *
 * Strategy: Topical Authority SEO 2026. Google ranks sites higher when they have
 * comprehensive coverage of a topic (country). 240 diverse articles per country
 * (200 SEO across guides, articles juridiques, pratiques, pain points, comparatifs, Q/R,
 * tutoriels, lifestyle, statistiques, sante, fiscalite, education, famille, outreach,
 * temoignages — plus 40 brand SOS-Expat.com articles : 12 brand-info, 8 brand-conversion,
 * and 20 brand pain-solution covering a wide variety of expat problems and how the
 * platform solves them) build a topical cluster that outranks sites with fewer articles
 * per country, while maximizing brand-keyword coverage on Google for "SOS-Expat".
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

    protected $description = 'Generate a complete country content cluster (240 articles: 200 SEO + 40 brand SOS-Expat.com)';

    /**
     * Top 6 expat cities per country for city guides. Covers ~80 of the most
     * relevant expat destinations. For countries not listed here, the city guide
     * topics fall back to generic placeholders ("la capitale", etc.).
     */
    private const TOP_CITIES = [
        // ── PRIORITY 11 + main expat hubs (existing 30) ──
        'TH' => ['Bangkok', 'Chiang Mai', 'Phuket', 'Pattaya', 'Krabi', 'Hua Hin'],
        'US' => ['New York', 'Miami', 'Los Angeles', 'San Francisco', 'Austin', 'Chicago'],
        'VN' => ['Ho Chi Minh-Ville', 'Hanoi', 'Da Nang', 'Hoi An', 'Nha Trang', 'Hue'],
        'SG' => ['Singapour Centre', 'Orchard', 'Marina Bay', 'Sentosa', 'Tiong Bahru', 'Tanjong Pagar'],
        'PT' => ['Lisbonne', 'Porto', 'Algarve', 'Madere', 'Cascais', 'Coimbra'],
        'ES' => ['Barcelone', 'Madrid', 'Valence', 'Malaga', 'Seville', 'Palma de Majorque'],
        'ID' => ['Bali', 'Jakarta', 'Yogyakarta', 'Ubud', 'Canggu', 'Bandung'],
        'MX' => ['Mexico', 'Playa del Carmen', 'Guadalajara', 'Tulum', 'Merida', 'Oaxaca'],
        'MA' => ['Casablanca', 'Marrakech', 'Rabat', 'Tanger', 'Agadir', 'Essaouira'],
        'AE' => ['Dubai', 'Abu Dhabi', 'Sharjah', 'Al Ain', 'Ras Al Khaimah', 'Ajman'],
        'JP' => ['Tokyo', 'Osaka', 'Kyoto', 'Fukuoka', 'Yokohama', 'Sapporo'],
        'DE' => ['Berlin', 'Munich', 'Francfort', 'Hambourg', 'Cologne', 'Stuttgart'],
        'GB' => ['Londres', 'Manchester', 'Edimbourg', 'Bristol', 'Birmingham', 'Glasgow'],
        'CA' => ['Montreal', 'Toronto', 'Vancouver', 'Calgary', 'Ottawa', 'Quebec'],
        'AU' => ['Sydney', 'Melbourne', 'Brisbane', 'Perth', 'Adelaide', 'Gold Coast'],
        'BR' => ['Sao Paulo', 'Rio de Janeiro', 'Florianopolis', 'Curitiba', 'Salvador', 'Belo Horizonte'],
        'CO' => ['Medellin', 'Bogota', 'Cartagena', 'Cali', 'Santa Marta', 'Barranquilla'],
        'CR' => ['San Jose', 'Tamarindo', 'Puerto Viejo', 'Manuel Antonio', 'Monteverde', 'Jaco'],
        'GR' => ['Athenes', 'Thessalonique', 'Crete', 'Mykonos', 'Santorin', 'Corfou'],
        'HR' => ['Zagreb', 'Split', 'Dubrovnik', 'Rijeka', 'Pula', 'Zadar'],
        'IT' => ['Rome', 'Milan', 'Florence', 'Naples', 'Bologne', 'Turin'],
        'NL' => ['Amsterdam', 'Rotterdam', 'La Haye', 'Utrecht', 'Eindhoven', 'Groningue'],
        'BE' => ['Bruxelles', 'Anvers', 'Gand', 'Bruges', 'Liege', 'Louvain'],
        'CH' => ['Geneve', 'Zurich', 'Lausanne', 'Bale', 'Berne', 'Lugano'],
        'TR' => ['Istanbul', 'Antalya', 'Izmir', 'Ankara', 'Bodrum', 'Fethiye'],
        'PH' => ['Manille', 'Cebu', 'Davao', 'Boracay', 'Siargao', 'Palawan'],
        'MY' => ['Kuala Lumpur', 'Penang', 'Johor Bahru', 'Kota Kinabalu', 'Langkawi', 'Malacca'],
        'KH' => ['Phnom Penh', 'Siem Reap', 'Sihanoukville', 'Battambang', 'Kep', 'Kampot'],
        'IN' => ['Mumbai', 'Bangalore', 'Goa', 'New Delhi', 'Pondichery', 'Pune'],
        'PL' => ['Varsovie', 'Cracovie', 'Wroclaw', 'Gdansk', 'Poznan', 'Lodz'],

        // ── EUROPE (additional hubs) ──
        'FR' => ['Paris', 'Lyon', 'Marseille', 'Bordeaux', 'Nice', 'Toulouse'],
        'AT' => ['Vienne', 'Salzbourg', 'Innsbruck', 'Graz', 'Linz', 'Klagenfurt'],
        'IE' => ['Dublin', 'Cork', 'Galway', 'Limerick', 'Waterford', 'Killarney'],
        'SE' => ['Stockholm', 'Goteborg', 'Malmo', 'Uppsala', 'Linkoping', 'Helsingborg'],
        'NO' => ['Oslo', 'Bergen', 'Trondheim', 'Stavanger', 'Tromso', 'Kristiansand'],
        'DK' => ['Copenhague', 'Aarhus', 'Odense', 'Aalborg', 'Esbjerg', 'Helsingor'],
        'FI' => ['Helsinki', 'Tampere', 'Turku', 'Espoo', 'Oulu', 'Rovaniemi'],
        'IS' => ['Reykjavik', 'Akureyri', 'Hafnarfjordur', 'Keflavik', 'Selfoss', 'Vik'],
        'CZ' => ['Prague', 'Brno', 'Ostrava', 'Plzen', 'Cesky Krumlov', 'Karlovy Vary'],
        'HU' => ['Budapest', 'Debrecen', 'Szeged', 'Pecs', 'Gyor', 'Miskolc'],
        'RO' => ['Bucarest', 'Cluj-Napoca', 'Brasov', 'Timisoara', 'Sibiu', 'Iasi'],
        'BG' => ['Sofia', 'Plovdiv', 'Varna', 'Bourgas', 'Veliko Tarnovo', 'Bansko'],
        'EE' => ['Tallinn', 'Tartu', 'Parnu', 'Narva', 'Viljandi', 'Kuressaare'],
        'LV' => ['Riga', 'Liepaja', 'Daugavpils', 'Jurmala', 'Ventspils', 'Cesis'],
        'LT' => ['Vilnius', 'Kaunas', 'Klaipeda', 'Siauliai', 'Panevezys', 'Trakai'],
        'SI' => ['Ljubljana', 'Maribor', 'Bled', 'Piran', 'Koper', 'Celje'],
        'SK' => ['Bratislava', 'Kosice', 'Banska Bystrica', 'Zilina', 'Nitra', 'Poprad'],
        'LU' => ['Luxembourg-Ville', 'Esch-sur-Alzette', 'Differdange', 'Dudelange', 'Ettelbruck', 'Diekirch'],
        'MT' => ['La Valette', 'Sliema', 'Saint-Julien', 'Mdina', 'Gozo', 'Mellieha'],
        'CY' => ['Nicosie', 'Limassol', 'Larnaca', 'Paphos', 'Ayia Napa', 'Famagouste'],
        'RU' => ['Moscou', 'Saint-Petersbourg', 'Sotchi', 'Kazan', 'Ekaterinbourg', 'Vladivostok'],
        'UA' => ['Kiev', 'Lviv', 'Odessa', 'Kharkiv', 'Dnipro', 'Tchernivtsi'],

        // ── ASIE (additional hubs) ──
        'CN' => ['Pekin', 'Shanghai', 'Shenzhen', 'Guangzhou', 'Chengdu', 'Hangzhou'],
        'HK' => ['Central', 'Kowloon', 'Causeway Bay', 'Tsim Sha Tsui', 'Mong Kok', 'Wan Chai'],
        'TW' => ['Taipei', 'Kaohsiung', 'Taichung', 'Tainan', 'Hsinchu', 'Hualien'],
        'KR' => ['Seoul', 'Busan', 'Incheon', 'Daegu', 'Jeju', 'Gwangju'],
        'IL' => ['Tel Aviv', 'Jerusalem', 'Haifa', 'Eilat', 'Netanya', 'Herzliya'],
        'LB' => ['Beyrouth', 'Byblos', 'Tripoli', 'Tyr', 'Saida', 'Baalbek'],
        'JO' => ['Amman', 'Petra', 'Aqaba', 'Madaba', 'Jerash', 'Wadi Rum'],
        'SA' => ['Riyad', 'Djeddah', 'La Mecque', 'Medine', 'Dammam', 'Khobar'],
        'QA' => ['Doha', 'Al Wakrah', 'Al Khor', 'Lusail', 'Al Rayyan', 'Mesaieed'],
        'BH' => ['Manama', 'Riffa', 'Muharraq', 'Hamad Town', 'Isa Town', 'Sitra'],
        'OM' => ['Mascate', 'Salalah', 'Sohar', 'Nizwa', 'Sur', 'Khasab'],
        'KW' => ['Koweit-Ville', 'Hawalli', 'Salmiya', 'Farwaniya', 'Mahboula', 'Ahmadi'],
        'PK' => ['Karachi', 'Lahore', 'Islamabad', 'Rawalpindi', 'Peshawar', 'Faisalabad'],
        'BD' => ['Dacca', 'Chittagong', 'Sylhet', 'Khulna', 'Rajshahi', 'Cox\'s Bazar'],
        'LK' => ['Colombo', 'Kandy', 'Galle', 'Negombo', 'Jaffna', 'Trincomalee'],
        'NP' => ['Katmandou', 'Pokhara', 'Lalitpur', 'Bhaktapur', 'Chitwan', 'Lumbini'],
        'MM' => ['Yangon', 'Mandalay', 'Bagan', 'Naypyidaw', 'Inle Lake', 'Taunggyi'],
        'LA' => ['Vientiane', 'Luang Prabang', 'Pakse', 'Vang Vieng', 'Savannakhet', 'Champasak'],

        // ── AMERIQUES (additional hubs) ──
        'AR' => ['Buenos Aires', 'Cordoba', 'Mendoza', 'Bariloche', 'Rosario', 'Salta'],
        'CL' => ['Santiago', 'Valparaiso', 'Vina del Mar', 'Concepcion', 'La Serena', 'Pucon'],
        'PE' => ['Lima', 'Cusco', 'Arequipa', 'Trujillo', 'Iquitos', 'Puno'],
        'EC' => ['Quito', 'Guayaquil', 'Cuenca', 'Banos', 'Galapagos', 'Otavalo'],
        'BO' => ['La Paz', 'Santa Cruz', 'Cochabamba', 'Sucre', 'Potosi', 'Uyuni'],
        'PY' => ['Asuncion', 'Ciudad del Este', 'Encarnacion', 'San Lorenzo', 'Luque', 'Lambare'],
        'UY' => ['Montevideo', 'Punta del Este', 'Colonia del Sacramento', 'Maldonado', 'Salto', 'Paysandu'],
        'PA' => ['Panama City', 'Boquete', 'Bocas del Toro', 'David', 'Coronado', 'El Valle'],
        'GT' => ['Guatemala City', 'Antigua', 'Atitlan', 'Quetzaltenango', 'Tikal', 'Flores'],
        'NI' => ['Managua', 'Granada', 'Leon', 'San Juan del Sur', 'Ometepe', 'Esteli'],
        'DO' => ['Saint-Domingue', 'Punta Cana', 'Santiago', 'Puerto Plata', 'Las Terrenas', 'Samana'],
        'CU' => ['La Havane', 'Varadero', 'Trinidad', 'Vinales', 'Santiago de Cuba', 'Cienfuegos'],
        'JM' => ['Kingston', 'Montego Bay', 'Negril', 'Ocho Rios', 'Port Antonio', 'Falmouth'],

        // ── AFRIQUE (additional hubs) ──
        'EG' => ['Le Caire', 'Alexandrie', 'Hurghada', 'Charm el-Cheikh', 'Louxor', 'Assouan'],
        'TN' => ['Tunis', 'Sousse', 'Hammamet', 'Djerba', 'Sfax', 'Monastir'],
        'DZ' => ['Alger', 'Oran', 'Constantine', 'Annaba', 'Tlemcen', 'Setif'],
        'SN' => ['Dakar', 'Saint-Louis', 'Thies', 'Ziguinchor', 'Saly', 'Mbour'],
        'CI' => ['Abidjan', 'Yamoussoukro', 'Bouake', 'San-Pedro', 'Korhogo', 'Grand-Bassam'],
        'KE' => ['Nairobi', 'Mombasa', 'Kisumu', 'Nakuru', 'Malindi', 'Lamu'],
        'TZ' => ['Dar es Salaam', 'Zanzibar', 'Arusha', 'Mwanza', 'Dodoma', 'Kilimandjaro'],
        'ZA' => ['Le Cap', 'Johannesbourg', 'Durban', 'Pretoria', 'Port Elizabeth', 'Stellenbosch'],
        'NG' => ['Lagos', 'Abuja', 'Port Harcourt', 'Kano', 'Ibadan', 'Calabar'],
        'GH' => ['Accra', 'Kumasi', 'Cape Coast', 'Tamale', 'Takoradi', 'Ho'],
        'CM' => ['Yaounde', 'Douala', 'Bafoussam', 'Limbe', 'Kribi', 'Garoua'],
        'MU' => ['Port Louis', 'Grand Baie', 'Flic en Flac', 'Curepipe', 'Quatre Bornes', 'Belle Mare'],
        'MG' => ['Antananarivo', 'Nosy Be', 'Antsirabe', 'Tulear', 'Diego Suarez', 'Sainte Marie'],
        'RW' => ['Kigali', 'Butare', 'Gisenyi', 'Ruhengeri', 'Cyangugu', 'Kibuye'],
        'ET' => ['Addis-Abeba', 'Lalibela', 'Gondar', 'Bahir Dar', 'Axoum', 'Harar'],

        // ── OCEANIE (additional hubs) ──
        'NZ' => ['Auckland', 'Wellington', 'Christchurch', 'Queenstown', 'Rotorua', 'Dunedin'],
        'FJ' => ['Suva', 'Nadi', 'Lautoka', 'Denarau', 'Coral Coast', 'Mamanuca'],
    ];

    /**
     * Content plan template: 220 articles per country (200 SEO topical + 20 brand SOS-Expat.com),
     * diversified by type and intent.
     * {country} and {country_name} are replaced at runtime.
     */
    public function getContentPlan(string $countryCode, string $countryName): array
    {
        $year = date('Y');
        $cities = self::TOP_CITIES[$countryCode] ?? ['la capitale', 'la deuxieme ville', 'la troisieme ville', 'la quatrieme ville', 'la cinquieme ville', 'la sixieme ville'];
        // French prepositions: "en Thailande", "au Japon", "aux Etats-Unis"
        $en = (self::COUNTRY_PREP[$countryCode] ?? 'en') . ' ' . $countryName; // "en Thailande"
        $de = (self::COUNTRY_DE_PREP[$countryCode] ?? 'de') . ' ' . $countryName; // "de Thailande"
        // Fix: "a Singapour" → "à Singapour"
        if (str_starts_with($en, 'a ')) {
            $en = 'à ' . mb_substr($en, 2);
        }

        return [
            // ══════════════════════════════════════════════════════════════════
            // PARTIE 1 — SEO TOPICAL CLUSTER (200 articles)
            // ══════════════════════════════════════════════════════════════════

            // ── FICHE PAYS / STATISTICS (2) — Data-driven country factsheets ──
            ['type' => 'statistics', 'intent' => 'informational', 'topic' => "{$countryName} : superficie, population, langues, monnaie, economie et chiffres cles ({$year})"],
            ['type' => 'statistics', 'intent' => 'informational', 'topic' => "{$countryName} en chiffres : indicateurs sociaux, economiques et politiques ({$year})"],

            // ── PILLAR CONTENT / GUIDES (10) — Foundation of the cluster ──
            ['type' => 'guide', 'intent' => 'informational', 'topic' => "S'expatrier {$en} : guide complet {$year}"],
            ['type' => 'guide', 'intent' => 'informational', 'topic' => "Cout de la vie {$en} en {$year} : budget detaille"],
            ['type' => 'guide', 'intent' => 'informational', 'topic' => "Visa et permis de sejour {$en} : toutes les options {$year}"],
            ['type' => 'guide', 'intent' => 'informational', 'topic' => "Systeme de sante {$en} : guide complet pour expatries ({$year})"],
            ['type' => 'guide', 'intent' => 'informational', 'topic' => "Travailler {$en} : marche de l'emploi et opportunites ({$year})"],
            ['type' => 'guide', 'intent' => 'informational', 'topic' => "S'installer {$en} : checklist complete des 3 premiers mois ({$year})"],
            ['type' => 'guide', 'intent' => 'informational', 'topic' => "Vivre {$en} : guide ultime pour les nouveaux arrivants ({$year})"],
            ['type' => 'guide', 'intent' => 'informational', 'topic' => "Investir {$en} : opportunites, fiscalite et risques ({$year})"],
            ['type' => 'guide', 'intent' => 'informational', 'topic' => "Prendre sa retraite {$en} : guide patrimoine, sante et qualite de vie ({$year})"],
            ['type' => 'guide', 'intent' => 'informational', 'topic' => "Emigration {$en} : conditions, etapes et delais ({$year})"],

            // ── GUIDES VILLE (6) — Top 6 cities per country ──
            ['type' => 'guide_city', 'intent' => 'informational', 'topic' => "Vivre a {$cities[0]} en tant qu'expatrie : guide complet ({$year})"],
            ['type' => 'guide_city', 'intent' => 'informational', 'topic' => "Vivre a {$cities[1]} en tant qu'expatrie : guide complet ({$year})"],
            ['type' => 'guide_city', 'intent' => 'informational', 'topic' => "Vivre a {$cities[2]} en tant qu'expatrie : guide complet ({$year})"],
            ['type' => 'guide_city', 'intent' => 'informational', 'topic' => "Vivre a {$cities[3]} en tant qu'expatrie : guide complet ({$year})"],
            ['type' => 'guide_city', 'intent' => 'informational', 'topic' => "Vivre a {$cities[4]} en tant qu'expatrie : guide complet ({$year})"],
            ['type' => 'guide_city', 'intent' => 'informational', 'topic' => "Vivre a {$cities[5]} en tant qu'expatrie : guide complet ({$year})"],

            // ── ARTICLES JURIDIQUES (22) — High conversion, legal topics ──
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
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Naturalisation {$en} : conditions, demarches et delais ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Mariage {$en} pour les etrangers : conditions et reconnaissance internationale ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "PACS et union civile {$en} : ce que les expatries doivent savoir ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Litiges commerciaux {$en} : recours et tribunaux competents ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Propriete intellectuelle {$en} : proteger une marque ou un brevet ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Bail commercial {$en} : droits et obligations des locataires etrangers ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Travailler en freelance {$en} : statut juridique et fiscal ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Discrimination au travail {$en} : recours legaux pour les expatries ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Resilier un bail {$en} : delais, conditions et penalites ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Litige avec l'administration {$en} : recours et procedures ({$year})"],

            // ── ARTICLES PRATIQUES (20) — Daily life topics ──
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
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Faire ses courses {$en} : marches, supermarches et prix ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Cuisiner {$en} : ingredients introuvables et alternatives locales ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Sport et fitness {$en} : salles, clubs et activites de plein air ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Voyager a l'interieur {$de} : trains, vols domestiques et road trips ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Recevoir des colis {$en} : douanes, taxes et delais ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Garder ses revenus {$en} : transferts internationaux sans frais caches ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Trouver une nounou ou aide a domicile {$en} : prix et legalite ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Banque mobile vs banque traditionnelle {$en} : que choisir ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Acheter une voiture {$en} : neuve, occasion, immatriculation ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Louer un logement meuble {$en} : plateformes et arnaques ({$year})"],

            // ── PAIN POINTS / URGENCES (18) — Highest conversion ──
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
            ['type' => 'pain_point', 'intent' => 'urgency', 'topic' => "Visa expire {$en} : risques et procedure de regularisation ({$year})"],
            ['type' => 'pain_point', 'intent' => 'urgency', 'topic' => "Carte bancaire bloquee {$en} : reactivation et solutions de secours ({$year})"],
            ['type' => 'pain_point', 'intent' => 'urgency', 'topic' => "Deces d'un proche {$en} : rapatriement, demarches et heritage ({$year})"],
            ['type' => 'pain_point', 'intent' => 'urgency', 'topic' => "Vol d'identite {$en} : protection et signalement ({$year})"],
            ['type' => 'pain_point', 'intent' => 'urgency', 'topic' => "Conflit avec un employeur {$en} : licenciement abusif et recours ({$year})"],
            ['type' => 'pain_point', 'intent' => 'urgency', 'topic' => "Enlevement parental international {$en} : que faire ({$year})"],
            ['type' => 'pain_point', 'intent' => 'urgency', 'topic' => "Refus d'entree {$en} a la frontiere : droits et recours ({$year})"],
            ['type' => 'pain_point', 'intent' => 'urgency', 'topic' => "Fraude immobiliere {$en} : detecter et porter plainte ({$year})"],

            // ── COMPARATIFS (17) — Commercial investigation intent ──
            ['type' => 'comparative', 'intent' => 'commercial_investigation', 'topic' => "Meilleure assurance sante {$en} pour expatries : comparatif {$year}"],
            ['type' => 'comparative', 'intent' => 'commercial_investigation', 'topic' => "Meilleure banque {$en} pour expatries : comparatif {$year}"],
            ['type' => 'comparative', 'intent' => 'commercial_investigation', 'topic' => "Meilleur forfait telephone {$en} : comparatif operateurs {$year}"],
            ['type' => 'comparative', 'intent' => 'commercial_investigation', 'topic' => "Transfert d'argent vers {$countryName} : Wise vs Revolut vs banque ({$year})"],
            ['type' => 'comparative', 'intent' => 'commercial_investigation', 'topic' => "VPN {$en} : quel service choisir ? ({$year})"],
            ['type' => 'comparative', 'intent' => 'commercial_investigation', 'topic' => "Meilleur espace coworking {$en} : comparatif {$year}"],
            ['type' => 'comparative', 'intent' => 'commercial_investigation', 'topic' => "Demenageurs internationaux vers {$countryName} : comparatif prix et avis ({$year})"],
            ['type' => 'comparative', 'intent' => 'commercial_investigation', 'topic' => "Ecoles internationales {$en} : comparatif et classement ({$year})"],
            ['type' => 'comparative', 'intent' => 'commercial_investigation', 'topic' => "Meilleure assurance habitation {$en} pour expatries : comparatif {$year}"],
            ['type' => 'comparative', 'intent' => 'commercial_investigation', 'topic' => "Carte de credit internationale {$en} : comparatif frais et avantages ({$year})"],
            ['type' => 'comparative', 'intent' => 'commercial_investigation', 'topic' => "Plateformes de location longue duree {$en} : comparatif ({$year})"],
            ['type' => 'comparative', 'intent' => 'commercial_investigation', 'topic' => "Cours de langue {$en} : ecoles, apps et tarifs compares ({$year})"],
            ['type' => 'comparative', 'intent' => 'commercial_investigation', 'topic' => "Assurance auto {$en} : comparatif des meilleurs assureurs pour etrangers ({$year})"],
            ['type' => 'comparative', 'intent' => 'commercial_investigation', 'topic' => "Plans d'epargne retraite {$en} : comparatif des solutions pour expatries ({$year})"],
            ['type' => 'comparative', 'intent' => 'commercial_investigation', 'topic' => "Avocats francophones {$en} : criteres de choix et tarifs moyens ({$year})"],
            ['type' => 'comparative', 'intent' => 'commercial_investigation', 'topic' => "Operateurs internet fibre {$en} : comparatif debit, prix, fiabilite ({$year})"],
            ['type' => 'comparative', 'intent' => 'commercial_investigation', 'topic' => "Cliniques privees {$en} : comparatif tarifs et specialites ({$year})"],

            // ── Q/R — QUESTIONS GOOGLE (35) — Featured snippets ──
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
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Comment ouvrir un compte bancaire {$en} sans residence ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Faut-il un permis de conduire international {$en} ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Comment se faire soigner {$en} en tant qu'etranger ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Quelle est la meilleure ville pour vivre {$en} ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Comment scolariser ses enfants {$en} ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Combien de temps peut-on rester {$en} sans visa ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Comment trouver du travail {$en} en tant qu'etranger ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Quels sont les impots a payer {$en} pour un expatrie ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Quel est le cout d'un logement {$en} ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Quels documents pour louer un logement {$en} ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Comment fonctionne la securite sociale {$en} ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Peut-on ramener son chien {$en} ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Comment s'inscrire au consulat de son pays {$en} ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Quel est le climat {$en} toute l'annee ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Quelle langue parler {$en} pour s'integrer ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Faut-il declarer ses revenus etrangers {$en} ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Comment obtenir la residence permanente {$en} ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Quels droits pour une famille {$en} ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Peut-on conduire {$en} avec son permis francais ou europeen ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Quels sont les meilleurs hopitaux {$en} ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Comment trouver une ecole francophone {$en} ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Combien coute une consultation medicale {$en} ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Comment fonctionne le marche immobilier {$en} ? ({$year})"],
            ['type' => 'qa', 'intent' => 'informational', 'topic' => "Quels sont les pieges a eviter quand on s'installe {$en} ? ({$year})"],

            // ── TUTORIELS (14) — Step-by-step, how-to schema ──
            ['type' => 'tutorial', 'intent' => 'transactional', 'topic' => "Comment obtenir un visa pour {$countryName} etape par etape ({$year})"],
            ['type' => 'tutorial', 'intent' => 'transactional', 'topic' => "Demenager {$en} : checklist complete ({$year})"],
            ['type' => 'tutorial', 'intent' => 'transactional', 'topic' => "Ouvrir un compte bancaire {$en} en ligne : tutoriel ({$year})"],
            ['type' => 'tutorial', 'intent' => 'transactional', 'topic' => "S'inscrire au consulat {$en} : demarche pas a pas ({$year})"],
            ['type' => 'tutorial', 'intent' => 'transactional', 'topic' => "Trouver un avocat {$en} : ou chercher et combien ca coute ({$year})"],
            ['type' => 'tutorial', 'intent' => 'transactional', 'topic' => "Passer le permis de conduire {$en} : tutoriel complet ({$year})"],
            ['type' => 'tutorial', 'intent' => 'transactional', 'topic' => "Souscrire une assurance sante {$en} : guide pas a pas ({$year})"],
            ['type' => 'tutorial', 'intent' => 'transactional', 'topic' => "Declaration d'impots {$en} : guide pour expatries ({$year})"],
            ['type' => 'tutorial', 'intent' => 'transactional', 'topic' => "Faire reconnaitre un diplome {$en} : procedure et delais ({$year})"],
            ['type' => 'tutorial', 'intent' => 'transactional', 'topic' => "Inscrire son enfant a l'ecole {$en} : etapes administratives ({$year})"],
            ['type' => 'tutorial', 'intent' => 'transactional', 'topic' => "Renouveler son passeport depuis {$countryName} : tutoriel ({$year})"],
            ['type' => 'tutorial', 'intent' => 'transactional', 'topic' => "Importer ses meubles {$en} : douane et logistique etape par etape ({$year})"],
            ['type' => 'tutorial', 'intent' => 'transactional', 'topic' => "Creer une SARL ou equivalent {$en} : tutoriel pour expatries ({$year})"],
            ['type' => 'tutorial', 'intent' => 'transactional', 'topic' => "Acheter un telephone et obtenir un numero {$en} : etape par etape ({$year})"],

            // ── DIGITAL NOMAD / LIFESTYLE (12) — Growing segment ──
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Digital nomad {$en} : visa, coworking et cout de vie ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Retraite {$en} : visa, fiscalite et qualite de vie ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Etudier {$en} : universites, bourses et vie etudiante ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Gastronomie {$en} : plats typiques et ou manger ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Benevolat {$en} : associations et missions pour expatries ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Apprendre la langue locale {$en} : ecoles et methodes ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Festivals et traditions {$en} : calendrier annuel a ne pas manquer ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Plages et nature {$en} : top destinations weekend ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Vie nocturne {$en} : sortir, bars et restaurants ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Rencontrer l'amour {$en} : applications et culture des rendez-vous ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Vivre minimalisme {$en} : simplifier sa vie d'expatrie ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Voyager autour {$de} : escapades faciles et vols low-cost ({$year})"],

            // ── STATISTIQUES (12) — Data-driven authority ──
            ['type' => 'statistics', 'intent' => 'informational', 'topic' => "Population expatriee {$en} : chiffres et tendances ({$year})"],
            ['type' => 'statistics', 'intent' => 'informational', 'topic' => "Cout de la vie {$en} en chiffres : loyer, courses, transport ({$year})"],
            ['type' => 'statistics', 'intent' => 'informational', 'topic' => "Salaires moyens {$en} par secteur ({$year})"],
            ['type' => 'statistics', 'intent' => 'informational', 'topic' => "Prix de l'immobilier {$en} : achat et location ({$year})"],
            ['type' => 'statistics', 'intent' => 'informational', 'topic' => "Criminalite et securite {$en} : statistiques ({$year})"],
            ['type' => 'statistics', 'intent' => 'informational', 'topic' => "Qualite de vie {$en} : classement et indicateurs ({$year})"],
            ['type' => 'statistics', 'intent' => 'informational', 'topic' => "Inflation {$en} : evolution sur 5 ans et impact pour les expatries ({$year})"],
            ['type' => 'statistics', 'intent' => 'informational', 'topic' => "Indice du bonheur {$en} : ce que disent les chiffres ({$year})"],
            ['type' => 'statistics', 'intent' => 'informational', 'topic' => "Esperance de vie et indicateurs sante {$en} ({$year})"],
            ['type' => 'statistics', 'intent' => 'informational', 'topic' => "Niveau d'education et systeme scolaire {$en} en chiffres ({$year})"],
            ['type' => 'statistics', 'intent' => 'informational', 'topic' => "Croissance economique et PIB {$en} : tendances et perspectives ({$year})"],
            ['type' => 'statistics', 'intent' => 'informational', 'topic' => "Mobilite internationale {$en} : flux migratoires et nationalites majoritaires ({$year})"],

            // ── OUTREACH (6) — Affiliate recruitment (inchange) ──
            ['type' => 'outreach', 'intent' => 'informational', 'topic' => "Devenir chatter SOS-Expat {$en} : aider les expatries et gagner de l'argent ({$year})"],
            ['type' => 'outreach', 'intent' => 'informational', 'topic' => "Influenceur expatriation {$en} : rejoindre le programme SOS-Expat ({$year})"],
            ['type' => 'outreach', 'intent' => 'informational', 'topic' => "Blogueur voyage {$en} : monetiser votre blog avec SOS-Expat ({$year})"],
            ['type' => 'outreach', 'intent' => 'informational', 'topic' => "Admin de groupe expat {$en} : monetiser votre communaute ({$year})"],
            ['type' => 'outreach', 'intent' => 'informational', 'topic' => "Avocat {$en} : devenir partenaire SOS-Expat ({$year})"],
            ['type' => 'outreach', 'intent' => 'informational', 'topic' => "Expat {$en} : aidez d'autres expatries et gagnez des commissions ({$year})"],

            // ── TEMOIGNAGES (8) — Social proof ──
            ['type' => 'testimonial', 'intent' => 'informational', 'topic' => "Temoignage : mon expatriation {$en}, les debuts ({$year})"],
            ['type' => 'testimonial', 'intent' => 'informational', 'topic' => "Temoignage : travailler {$en} en tant qu'etranger ({$year})"],
            ['type' => 'testimonial', 'intent' => 'informational', 'topic' => "Temoignage : s'installer en famille en {$countryName} ({$year})"],
            ['type' => 'testimonial', 'intent' => 'informational', 'topic' => "Temoignage : digital nomad {$en}, avantages et difficultes ({$year})"],
            ['type' => 'testimonial', 'intent' => 'informational', 'topic' => "Temoignage : retraitee {$en}, mon quotidien ({$year})"],
            ['type' => 'testimonial', 'intent' => 'informational', 'topic' => "Temoignage : entrepreneuse {$en}, creer son business {$year}"],
            ['type' => 'testimonial', 'intent' => 'informational', 'topic' => "Temoignage : etudiant {$en}, mon experience universitaire ({$year})"],
            ['type' => 'testimonial', 'intent' => 'informational', 'topic' => "Temoignage : couple mixte {$en}, les defis du quotidien ({$year})"],

            // ── 🆕 SANTE & ASSURANCE DETAILLEE (5) ──
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Pharmacies et medicaments {$en} : equivalents et acces pour expatries ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Sante mentale {$en} : psychologues francophones et hotlines d'aide ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Maternite {$en} : suivi de grossesse et accouchement pour expatriees ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Vaccinations enfants {$en} : calendrier officiel et equivalences internationales ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Couverture dentaire et optique {$en} : combien ca coute pour un expatrie ({$year})"],

            // ── 🆕 FISCALITE & COMPTABILITE (5) ──
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Convention fiscale entre {$countryName} et la France : eviter la double imposition ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Optimisation fiscale {$en} pour les expatries : strategies legales ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Crypto-monnaies et fiscalite {$en} : ce que les expatries doivent declarer ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "TVA et taxes locales {$en} : guide pour entrepreneurs etrangers ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Trouver un comptable francophone {$en} : tarifs et services ({$year})"],

            // ── 🆕 EDUCATION & UNIVERSITES (4) ──
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Top universites {$en} : classement, frais et conditions d'admission ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Bourses d'etudes {$en} pour etudiants etrangers : guide complet ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Equivalences de diplomes {$en} : faire reconnaitre son cursus francais ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Crèches et garderies {$en} : modes de garde et tarifs pour expatries ({$year})"],

            // ── 🆕 FAMILLE & COUPLE EXPAT (4) ──
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Reunir sa famille {$en} : visa conjoint, enfants et parents ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Enfants expatries {$en} : reussir l'integration scolaire et culturelle ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Couple expatrie {$en} : preserver son union loin de chez soi ({$year})"],
            ['type' => 'article', 'intent' => 'informational', 'topic' => "Adoption internationale {$en} : conditions, demarches et delais ({$year})"],

            // ══════════════════════════════════════════════════════════════════
            // PARTIE 2 — BRAND CONTENT SOS-EXPAT.COM (20 articles)
            // ══════════════════════════════════════════════════════════════════

            // ── BRAND-INFO (12) — Informational, brand-focused ──
            ['type' => 'brand_content', 'intent' => 'informational', 'topic' => "SOS-Expat.com {$en} : qui sommes-nous, mission et histoire ({$year})"],
            ['type' => 'brand_content', 'intent' => 'informational', 'topic' => "SOS-Expat.com {$en} : comment fonctionne la mise en relation avec un avocat en 5 minutes ({$year})"],
            ['type' => 'brand_content', 'intent' => 'informational', 'topic' => "SOS-Expat.com {$en} : tarifs detailles des consultations juridiques et expertises ({$year})"],
            ['type' => 'brand_content', 'intent' => 'informational', 'topic' => "SOS-Expat.com {$en} : nos garanties, satisfaction et politique de remboursement ({$year})"],
            ['type' => 'brand_content', 'intent' => 'informational', 'topic' => "SOS-Expat.com {$en} : securite des paiements et confidentialite des appels ({$year})"],
            ['type' => 'brand_content', 'intent' => 'informational', 'topic' => "SOS-Expat.com {$en} : disponibilite 24h/24, 7j/7 et couverture des 197 pays ({$year})"],
            ['type' => 'brand_content', 'intent' => 'informational', 'topic' => "SOS-Expat.com {$en} : 9 langues supportees et expert francophone {$en} ({$year})"],
            ['type' => 'brand_content', 'intent' => 'informational', 'topic' => "SOS-Expat.com {$en} : avis clients verifies et temoignages d'expatries ({$year})"],
            ['type' => 'brand_content', 'intent' => 'informational', 'topic' => "SOS-Expat.com {$en} : equipe d'avocats partenaires et processus de verification ({$year})"],
            ['type' => 'brand_content', 'intent' => 'informational', 'topic' => "SOS-Expat.com {$en} : difference entre avocat et expert SOS-Expat — quel choix selon votre besoin ({$year})"],
            ['type' => 'brand_content', 'intent' => 'informational', 'topic' => "SOS-Expat.com {$en} : assistant IA juridique gratuit, comment l'utiliser ({$year})"],
            ['type' => 'brand_content', 'intent' => 'informational', 'topic' => "SOS-Expat.com {$en} : programme d'affiliation et comment devenir partenaire ({$year})"],

            // ── BRAND-CONVERSION (8) — Commercial investigation + urgency ──
            ['type' => 'brand_content', 'intent' => 'commercial_investigation', 'topic' => "SOS-Expat.com vs avocat local {$en} : comparatif prix, delai et langue ({$year})"],
            ['type' => 'brand_content', 'intent' => 'commercial_investigation', 'topic' => "SOS-Expat.com vs assurance expatries {$en} : quelles differences ({$year})"],
            ['type' => 'brand_content', 'intent' => 'commercial_investigation', 'topic' => "SOS-Expat.com vs ambassade {$en} : qui contacter selon votre urgence ({$year})"],
            ['type' => 'brand_content', 'intent' => 'urgency', 'topic' => "Urgence juridique {$en} : pourquoi appeler SOS-Expat.com en premier ({$year})"],
            ['type' => 'brand_content', 'intent' => 'urgency', 'topic' => "Arrestation ou garde a vue {$en} : SOS-Expat.com vous met en relation immediate avec un avocat ({$year})"],
            ['type' => 'brand_content', 'intent' => 'commercial_investigation', 'topic' => "Visa refuse {$en} : comment SOS-Expat.com peut vous aider a faire appel ({$year})"],
            ['type' => 'brand_content', 'intent' => 'commercial_investigation', 'topic' => "Litige avec un employeur {$en} : etapes et accompagnement SOS-Expat.com ({$year})"],
            ['type' => 'brand_content', 'intent' => 'urgency', 'topic' => "Hospitalisation d'urgence {$en} : assistance SOS-Expat.com pour les demarches administratives ({$year})"],

            // ══════════════════════════════════════════════════════════════════
            // PARTIE 3 — BRAND PAIN-SOLUTION SOS-EXPAT.COM (20 articles)
            // Format : "Probleme X {en} : la solution avec SOS-Expat.com"
            // Couvre une multitude de problemes typiques d'expatriation
            // ══════════════════════════════════════════════════════════════════
            ['type' => 'brand_content', 'intent' => 'urgency', 'topic' => "Visa expire {$en} et risque d'expulsion : la solution avec SOS-Expat.com ({$year})"],
            ['type' => 'brand_content', 'intent' => 'urgency', 'topic' => "Conflit avec votre proprietaire {$en} (caution, expulsion, charges) : la solution SOS-Expat.com ({$year})"],
            ['type' => 'brand_content', 'intent' => 'urgency', 'topic' => "Refus de soins ou litige medical {$en} : SOS-Expat.com vous accompagne ({$year})"],
            ['type' => 'brand_content', 'intent' => 'commercial_investigation', 'topic' => "Discrimination a l'embauche {$en} : vos recours legaux avec SOS-Expat.com ({$year})"],
            ['type' => 'brand_content', 'intent' => 'commercial_investigation', 'topic' => "Diplome francais non reconnu {$en} : la solution SOS-Expat.com pour faire valoir vos droits ({$year})"],
            ['type' => 'brand_content', 'intent' => 'urgency', 'topic' => "Urgence medicale sans assurance {$en} : que faire et qui appeler — SOS-Expat.com ({$year})"],
            ['type' => 'brand_content', 'intent' => 'commercial_investigation', 'topic' => "Litige avec une ecole internationale {$en} (frais, exclusion) : la solution SOS-Expat.com ({$year})"],
            ['type' => 'brand_content', 'intent' => 'urgency', 'topic' => "Conflit familial transfrontalier {$en} : SOS-Expat.com et nos avocats specialises ({$year})"],
            ['type' => 'brand_content', 'intent' => 'urgency', 'topic' => "Garde d'enfant apres divorce international {$en} : assistance immediate SOS-Expat.com ({$year})"],
            ['type' => 'brand_content', 'intent' => 'urgency', 'topic' => "Deces d'un proche {$en} (rapatriement, succession) : SOS-Expat.com vous guide ({$year})"],
            ['type' => 'brand_content', 'intent' => 'urgency', 'topic' => "Arnaque immobiliere {$en} : porter plainte avec SOS-Expat.com ({$year})"],
            ['type' => 'brand_content', 'intent' => 'urgency', 'topic' => "Vol d'identite ou cyberattaque {$en} : la solution avec SOS-Expat.com ({$year})"],
            ['type' => 'brand_content', 'intent' => 'urgency', 'topic' => "Compte bancaire bloque {$en} : debloquer la situation avec SOS-Expat.com ({$year})"],
            ['type' => 'brand_content', 'intent' => 'commercial_investigation', 'topic' => "Litige fiscal {$en} (double imposition, redressement) : SOS-Expat.com et nos experts fiscaux ({$year})"],
            ['type' => 'brand_content', 'intent' => 'urgency', 'topic' => "Refus d'entree a la frontiere {$en} : reagir en urgence avec SOS-Expat.com ({$year})"],
            ['type' => 'brand_content', 'intent' => 'urgency', 'topic' => "Accident de la route avec un local {$en} : assistance SOS-Expat.com ({$year})"],
            ['type' => 'brand_content', 'intent' => 'commercial_investigation', 'topic' => "Litige successoral entre pays {$en} : la solution avec SOS-Expat.com ({$year})"],
            ['type' => 'brand_content', 'intent' => 'commercial_investigation', 'topic' => "Faillite personnelle ou commerciale {$en} : SOS-Expat.com et nos avocats partenaires ({$year})"],
            ['type' => 'brand_content', 'intent' => 'commercial_investigation', 'topic' => "Harcelement au travail {$en} : porter plainte avec l'aide de SOS-Expat.com ({$year})"],
            ['type' => 'brand_content', 'intent' => 'urgency', 'topic' => "Enfant disparu ou enleve {$en} : assistance immediate SOS-Expat.com ({$year})"],
        ];
        // SEO total (200): statistics(2) + guide(10) + guide_city(6) + juridique(22) + pratique(20)
        //                + pain_point(18) + comparatif(17) + qa(35) + tutorial(14) + lifestyle(12)
        //                + statistics_data(12) + outreach(6) + testimonial(8) + sante(5)
        //                + fiscalite(5) + education(4) + famille(4) = 200
        // Brand SOS-Expat.com (40): brand-info(12) + brand-conversion(8) + brand-pain-solution(20) = 40
        // GRAND TOTAL: 240 articles per country
    }

    /**
     * Country order for auto mode — 197 sovereign states + main territories.
     * The first 11 codes are the user-defined PRIORITY queue (Asia + Pacific + Americas),
     * the rest follow alphabetically by ISO code (the actual queue order in DB
     * is set by the migration that shuffles the non-priority countries).
     */
    public const COUNTRY_ORDER = [
        // ── PRIORITY 11 (user-defined order) ──
        'TH' => 'Thailande',
        'VN' => 'Vietnam',
        'SG' => 'Singapour',
        'MY' => 'Malaisie',
        'PH' => 'Philippines',
        'JP' => 'Japon',
        'AU' => 'Australie',
        'MX' => 'Mexique',
        'BR' => 'Bresil',
        'CR' => 'Costa Rica',
        'US' => 'Etats-Unis',

        // ── EUROPE (other) ──
        'AL' => 'Albanie',           'AD' => 'Andorre',          'AT' => 'Autriche',
        'BE' => 'Belgique',          'BA' => 'Bosnie-Herzegovine','BG' => 'Bulgarie',
        'HR' => 'Croatie',           'CY' => 'Chypre',           'CZ' => 'Republique tcheque',
        'DK' => 'Danemark',          'EE' => 'Estonie',          'FI' => 'Finlande',
        'FR' => 'France',            'DE' => 'Allemagne',        'GR' => 'Grece',
        'HU' => 'Hongrie',           'IS' => 'Islande',          'IE' => 'Irlande',
        'IT' => 'Italie',            'XK' => 'Kosovo',           'LV' => 'Lettonie',
        'LI' => 'Liechtenstein',     'LT' => 'Lituanie',         'LU' => 'Luxembourg',
        'MT' => 'Malte',             'MD' => 'Moldavie',         'MC' => 'Monaco',
        'ME' => 'Montenegro',        'NL' => 'Pays-Bas',         'MK' => 'Macedoine du Nord',
        'NO' => 'Norvege',           'PL' => 'Pologne',          'PT' => 'Portugal',
        'RO' => 'Roumanie',          'RU' => 'Russie',           'SM' => 'Saint-Marin',
        'RS' => 'Serbie',            'SK' => 'Slovaquie',        'SI' => 'Slovenie',
        'ES' => 'Espagne',           'SE' => 'Suede',            'CH' => 'Suisse',
        'UA' => 'Ukraine',           'GB' => 'Royaume-Uni',      'VA' => 'Vatican',
        'BY' => 'Bielorussie',

        // ── ASIE (other) ──
        'AF' => 'Afghanistan',       'AM' => 'Armenie',          'AZ' => 'Azerbaidjan',
        'BH' => 'Bahrein',           'BD' => 'Bangladesh',       'BT' => 'Bhoutan',
        'BN' => 'Brunei',            'KH' => 'Cambodge',         'CN' => 'Chine',
        'GE' => 'Georgie',           'HK' => 'Hong Kong',        'IN' => 'Inde',
        'ID' => 'Indonesie',         'IR' => 'Iran',             'IQ' => 'Irak',
        'IL' => 'Israel',            'JO' => 'Jordanie',         'KZ' => 'Kazakhstan',
        'KP' => 'Coree du Nord',     'KR' => 'Coree du Sud',     'KW' => 'Koweit',
        'KG' => 'Kirghizistan',      'LA' => 'Laos',             'LB' => 'Liban',
        'MO' => 'Macao',             'MV' => 'Maldives',         'MN' => 'Mongolie',
        'MM' => 'Birmanie',          'NP' => 'Nepal',            'OM' => 'Oman',
        'PK' => 'Pakistan',          'PS' => 'Palestine',        'QA' => 'Qatar',
        'SA' => 'Arabie saoudite',   'LK' => 'Sri Lanka',        'SY' => 'Syrie',
        'TW' => 'Taiwan',            'TJ' => 'Tadjikistan',      'TL' => 'Timor oriental',
        'TR' => 'Turquie',           'TM' => 'Turkmenistan',     'AE' => 'Emirats arabes unis',
        'UZ' => 'Ouzbekistan',       'YE' => 'Yemen',

        // ── AMERIQUES (other) ──
        'AG' => 'Antigua-et-Barbuda','AR' => 'Argentine',        'BS' => 'Bahamas',
        'BB' => 'Barbade',           'BZ' => 'Belize',           'BO' => 'Bolivie',
        'CA' => 'Canada',            'CL' => 'Chili',            'CO' => 'Colombie',
        'CU' => 'Cuba',              'DM' => 'Dominique',        'DO' => 'Republique dominicaine',
        'EC' => 'Equateur',          'SV' => 'El Salvador',      'GD' => 'Grenade',
        'GT' => 'Guatemala',         'GY' => 'Guyana',           'HT' => 'Haiti',
        'HN' => 'Honduras',          'JM' => 'Jamaique',         'NI' => 'Nicaragua',
        'PA' => 'Panama',            'PY' => 'Paraguay',         'PE' => 'Perou',
        'KN' => 'Saint-Christophe-et-Nieves', 'LC' => 'Sainte-Lucie',
        'VC' => 'Saint-Vincent-et-les-Grenadines',
        'SR' => 'Suriname',          'TT' => 'Trinite-et-Tobago','UY' => 'Uruguay',
        'VE' => 'Venezuela',

        // ── AFRIQUE ──
        'DZ' => 'Algerie',           'AO' => 'Angola',           'BJ' => 'Benin',
        'BW' => 'Botswana',          'BF' => 'Burkina Faso',     'BI' => 'Burundi',
        'CM' => 'Cameroun',          'CV' => 'Cap-Vert',         'CF' => 'Centrafrique',
        'TD' => 'Tchad',             'KM' => 'Comores',          'CD' => 'Republique democratique du Congo',
        'CG' => 'Congo',             'CI' => 'Cote d\'Ivoire',   'DJ' => 'Djibouti',
        'EG' => 'Egypte',            'GQ' => 'Guinee equatoriale','ER' => 'Erythree',
        'SZ' => 'Eswatini',          'ET' => 'Ethiopie',         'GA' => 'Gabon',
        'GM' => 'Gambie',            'GH' => 'Ghana',            'GN' => 'Guinee',
        'GW' => 'Guinee-Bissau',     'KE' => 'Kenya',            'LS' => 'Lesotho',
        'LR' => 'Liberia',           'LY' => 'Libye',            'MG' => 'Madagascar',
        'MW' => 'Malawi',            'ML' => 'Mali',             'MR' => 'Mauritanie',
        'MU' => 'Maurice',           'MA' => 'Maroc',            'MZ' => 'Mozambique',
        'NA' => 'Namibie',           'NE' => 'Niger',            'NG' => 'Nigeria',
        'RW' => 'Rwanda',            'ST' => 'Sao Tome-et-Principe','SN' => 'Senegal',
        'SC' => 'Seychelles',        'SL' => 'Sierra Leone',     'SO' => 'Somalie',
        'ZA' => 'Afrique du Sud',    'SS' => 'Soudan du Sud',    'SD' => 'Soudan',
        'TZ' => 'Tanzanie',          'TG' => 'Togo',             'TN' => 'Tunisie',
        'UG' => 'Ouganda',           'ZM' => 'Zambie',           'ZW' => 'Zimbabwe',

        // ── OCEANIE ──
        'FJ' => 'Fidji',             'KI' => 'Kiribati',         'MH' => 'Iles Marshall',
        'FM' => 'Micronesie',        'NR' => 'Nauru',            'NZ' => 'Nouvelle-Zelande',
        'PW' => 'Palaos',            'PG' => 'Papouasie-Nouvelle-Guinee',
        'WS' => 'Samoa',             'SB' => 'Iles Salomon',     'TO' => 'Tonga',
        'TV' => 'Tuvalu',            'VU' => 'Vanuatu',
    ];

    /**
     * French prepositions for countries: "en" (feminine/vowel), "au" (masc sing),
     * "aux" (plural), "a" (islands/cities). Used in templates: "S'expatrier {prep} {country}".
     * Defaults to "en" for any country not listed here.
     */
    private const COUNTRY_PREP = [
        // Priority 11
        'TH' => 'en',  'VN' => 'au',  'SG' => 'a',   'MY' => 'en',  'PH' => 'aux',
        'JP' => 'au',  'AU' => 'en',  'MX' => 'au',  'BR' => 'au',  'CR' => 'au',  'US' => 'aux',
        // Europe
        'AL' => 'en',  'AD' => 'en',  'AT' => 'en',  'BE' => 'en',  'BA' => 'en',
        'BG' => 'en',  'HR' => 'en',  'CY' => 'a',   'CZ' => 'en',  'DK' => 'au',
        'EE' => 'en',  'FI' => 'en',  'FR' => 'en',  'DE' => 'en',  'GR' => 'en',
        'HU' => 'en',  'IS' => 'en',  'IE' => 'en',  'IT' => 'en',  'XK' => 'au',
        'LV' => 'en',  'LI' => 'au',  'LT' => 'en',  'LU' => 'au',  'MT' => 'a',
        'MD' => 'en',  'MC' => 'a',   'ME' => 'au',  'NL' => 'aux', 'MK' => 'en',
        'NO' => 'en',  'PL' => 'en',  'PT' => 'au',  'RO' => 'en',  'RU' => 'en',
        'SM' => 'a',   'RS' => 'en',  'SK' => 'en',  'SI' => 'en',  'ES' => 'en',
        'SE' => 'en',  'CH' => 'en',  'UA' => 'en',  'GB' => 'au',  'VA' => 'au',  'BY' => 'en',
        // Asie
        'AF' => 'en',  'AM' => 'en',  'AZ' => 'en',  'BH' => 'a',   'BD' => 'au',
        'BT' => 'au',  'BN' => 'au',  'KH' => 'au',  'CN' => 'en',  'GE' => 'en',
        'HK' => 'a',   'IN' => 'en',  'ID' => 'en',  'IR' => 'en',  'IQ' => 'en',
        'IL' => 'en',  'JO' => 'en',  'KZ' => 'au',  'KP' => 'en',  'KR' => 'en',
        'KW' => 'au',  'KG' => 'au',  'LA' => 'au',  'LB' => 'au',  'MO' => 'a',
        'MV' => 'aux', 'MN' => 'en',  'MM' => 'en',  'NP' => 'au',  'OM' => 'a',
        'PK' => 'au',  'PS' => 'en',  'QA' => 'au',  'SA' => 'en',  'LK' => 'au',
        'SY' => 'en',  'TW' => 'a',   'TJ' => 'au',  'TL' => 'au',  'TR' => 'en',
        'TM' => 'au',  'AE' => 'aux', 'UZ' => 'en',  'YE' => 'au',
        // Ameriques
        'AG' => 'a',   'AR' => 'en',  'BS' => 'aux', 'BB' => 'a',   'BZ' => 'au',
        'BO' => 'en',  'CA' => 'au',  'CL' => 'au',  'CO' => 'en',  'CU' => 'a',
        'DM' => 'a',   'DO' => 'en',  'EC' => 'en',  'SV' => 'au',  'GD' => 'a',
        'GT' => 'au',  'GY' => 'au',  'HT' => 'en',  'HN' => 'au',  'JM' => 'a',
        'NI' => 'au',  'PA' => 'au',  'PY' => 'au',  'PE' => 'au',  'KN' => 'a',
        'LC' => 'a',   'VC' => 'a',   'SR' => 'au',  'TT' => 'a',   'UY' => 'en',  'VE' => 'au',
        // Afrique
        'DZ' => 'en',  'AO' => 'en',  'BJ' => 'au',  'BW' => 'au',  'BF' => 'au',
        'BI' => 'au',  'CM' => 'au',  'CV' => 'au',  'CF' => 'en',  'TD' => 'au',
        'KM' => 'aux', 'CD' => 'en',  'CG' => 'au',  'CI' => 'en',  'DJ' => 'a',
        'EG' => 'en',  'GQ' => 'en',  'ER' => 'en',  'SZ' => 'en',  'ET' => 'en',
        'GA' => 'au',  'GM' => 'en',  'GH' => 'au',  'GN' => 'en',  'GW' => 'en',
        'KE' => 'au',  'LS' => 'au',  'LR' => 'au',  'LY' => 'en',  'MG' => 'a',
        'MW' => 'au',  'ML' => 'au',  'MR' => 'en',  'MU' => 'a',   'MA' => 'au',
        'MZ' => 'au',  'NA' => 'en',  'NE' => 'au',  'NG' => 'au',  'RW' => 'au',
        'ST' => 'a',   'SN' => 'au',  'SC' => 'aux', 'SL' => 'en',  'SO' => 'en',
        'ZA' => 'en',  'SS' => 'au',  'SD' => 'au',  'TZ' => 'en',  'TG' => 'au',
        'TN' => 'en',  'UG' => 'en',  'ZM' => 'en',  'ZW' => 'au',
        // Oceanie
        'FJ' => 'aux', 'KI' => 'aux', 'MH' => 'aux', 'FM' => 'en',  'NR' => 'a',
        'NZ' => 'en',  'PW' => 'aux', 'PG' => 'en',  'WS' => 'aux', 'SB' => 'aux',
        'TO' => 'au',  'TV' => 'a',   'VU' => 'au',
    ];

    /**
     * French preposition "de" variants: "de" (vowel/fem), "du" (masc), "des" (plural),
     * "d'" (vowel-starting). Used in templates: "Expulsion {de_prep} {country}".
     * Defaults to "de" for any country not listed here.
     */
    private const COUNTRY_DE_PREP = [
        // Priority 11
        'TH' => 'de',  'VN' => 'du',  'SG' => 'de',  'MY' => 'de',  'PH' => 'des',
        'JP' => 'du',  'AU' => "d'",  'MX' => 'du',  'BR' => 'du',  'CR' => 'du',  'US' => 'des',
        // Europe
        'AL' => "d'",  'AD' => "d'",  'AT' => "d'",  'BE' => 'de',  'BA' => 'de',
        'BG' => 'de',  'HR' => 'de',  'CY' => 'de',  'CZ' => 'de',  'DK' => 'du',
        'EE' => "d'",  'FI' => 'de',  'FR' => 'de',  'DE' => "d'",  'GR' => 'de',
        'HU' => 'de',  'IS' => "d'",  'IE' => "d'",  'IT' => "d'",  'XK' => 'du',
        'LV' => 'de',  'LI' => 'du',  'LT' => 'de',  'LU' => 'du',  'MT' => 'de',
        'MD' => 'de',  'MC' => 'de',  'ME' => 'du',  'NL' => 'des', 'MK' => 'de',
        'NO' => 'de',  'PL' => 'de',  'PT' => 'du',  'RO' => 'de',  'RU' => 'de',
        'SM' => 'de',  'RS' => 'de',  'SK' => 'de',  'SI' => 'de',  'ES' => "d'",
        'SE' => 'de',  'CH' => 'de',  'UA' => "d'",  'GB' => 'du',  'VA' => 'du',  'BY' => 'de',
        // Asie
        'AF' => "d'",  'AM' => "d'",  'AZ' => "d'",  'BH' => 'de',  'BD' => 'du',
        'BT' => 'du',  'BN' => 'du',  'KH' => 'du',  'CN' => 'de',  'GE' => 'de',
        'HK' => 'de',  'IN' => "d'",  'ID' => "d'",  'IR' => "d'",  'IQ' => "d'",
        'IL' => "d'",  'JO' => 'de',  'KZ' => 'du',  'KP' => 'de',  'KR' => 'de',
        'KW' => 'du',  'KG' => 'du',  'LA' => 'du',  'LB' => 'du',  'MO' => 'de',
        'MV' => 'des', 'MN' => 'de',  'MM' => 'de',  'NP' => 'du',  'OM' => "d'",
        'PK' => 'du',  'PS' => 'de',  'QA' => 'du',  'SA' => "d'",  'LK' => 'du',
        'SY' => 'de',  'TW' => 'de',  'TJ' => 'du',  'TL' => 'du',  'TR' => 'de',
        'TM' => 'du',  'AE' => 'des', 'UZ' => "d'",  'YE' => 'du',
        // Ameriques
        'AG' => "d'",  'AR' => "d'",  'BS' => 'des', 'BB' => 'de',  'BZ' => 'du',
        'BO' => 'de',  'CA' => 'du',  'CL' => 'du',  'CO' => 'de',  'CU' => 'de',
        'DM' => 'de',  'DO' => 'de',  'EC' => "d'",  'SV' => 'du',  'GD' => 'de',
        'GT' => 'du',  'GY' => 'du',  'HT' => "d'",  'HN' => 'du',  'JM' => 'de',
        'NI' => 'du',  'PA' => 'du',  'PY' => 'du',  'PE' => 'du',  'KN' => 'de',
        'LC' => 'de',  'VC' => 'de',  'SR' => 'du',  'TT' => 'de',  'UY' => "d'",  'VE' => 'du',
        // Afrique
        'DZ' => "d'",  'AO' => "d'",  'BJ' => 'du',  'BW' => 'du',  'BF' => 'du',
        'BI' => 'du',  'CM' => 'du',  'CV' => 'du',  'CF' => 'de',  'TD' => 'du',
        'KM' => 'des', 'CD' => 'de',  'CG' => 'du',  'CI' => 'de',  'DJ' => 'de',
        'EG' => "d'",  'GQ' => 'de',  'ER' => "d'",  'SZ' => "d'",  'ET' => "d'",
        'GA' => 'du',  'GM' => 'de',  'GH' => 'du',  'GN' => 'de',  'GW' => 'de',
        'KE' => 'du',  'LS' => 'du',  'LR' => 'du',  'LY' => 'de',  'MG' => 'de',
        'MW' => 'du',  'ML' => 'du',  'MR' => 'de',  'MU' => 'de',  'MA' => 'du',
        'MZ' => 'du',  'NA' => 'de',  'NE' => 'du',  'NG' => 'du',  'RW' => 'du',
        'ST' => 'de',  'SN' => 'du',  'SC' => 'des', 'SL' => 'de',  'SO' => 'de',
        'ZA' => "d'",  'SS' => 'du',  'SD' => 'du',  'TZ' => 'de',  'TG' => 'du',
        'TN' => 'de',  'UG' => "d'",  'ZM' => 'de',  'ZW' => 'du',
        // Oceanie
        'FJ' => 'des', 'KI' => 'des', 'MH' => 'des', 'FM' => 'de',  'NR' => 'de',
        'NZ' => 'de',  'PW' => 'des', 'PG' => 'de',  'WS' => 'des', 'SB' => 'des',
        'TO' => 'des', 'TV' => 'de',  'VU' => 'du',
    ];

    /**
     * Get campaign threshold from DB config.
     */
    private function getThreshold(): int
    {
        $config = DB::table('content_orchestrator_config')->first();
        return (int) ($config->campaign_articles_per_country ?? 240);
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
        $existingArticles = GeneratedArticle::where('country', $countryCode)
            ->where('language', 'fr')
            ->whereIn('status', ['generating', 'review', 'published', 'approved'])
            ->select(['title', 'content_type'])
            ->get()
            ->map(fn ($a) => [
                'title' => $a->title,
                'type' => $a->content_type,
                'keywords' => $this->extractDedupKeywords($a->title, $countryName),
            ])
            ->toArray();

        $existingCount = count($existingArticles);
        $this->info("Existing articles for {$countryCode}: {$existingCount}");

        // Filter out already-generated topics using content_type-aware semantic dedup.
        // Two articles with the SAME content_type are considered duplicates if they share
        // >= 2 core keywords. Articles with DIFFERENT content_type need >= 4 keywords overlap
        // to be considered duplicates — this prevents false positives between e.g. a Q/R
        // ("Faut-il un permis de conduire ?") and a long article ("Permis de conduire :
        // obtention et conversion") that target different intents and formats.
        $toGenerate = [];
        foreach ($plan as $item) {
            $topicKeywords = $this->extractDedupKeywords($item['topic'], $countryName);
            $isDuplicate = false;
            foreach ($existingArticles as $existing) {
                $overlap = count(array_intersect($topicKeywords, $existing['keywords']));
                $sameType = ($item['type'] === $existing['type']);
                $threshold = $sameType ? 2 : 4;
                if ($overlap >= $threshold) {
                    $isDuplicate = true;
                    $this->line("  [SKIP] \"{$item['topic']}\" — overlaps with existing {$existing['type']} (overlap={$overlap}, threshold={$threshold})");
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
    public function extractDedupKeywords(string $text, string $countryName): array
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
    public function stripAccents(string $str): string
    {
        $transliterator = \Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC');
        return $transliterator ? $transliterator->transliterate($str) : $str;
    }

    /**
     * Extract keywords from a topic string.
     *
     * Topics are typically shaped like:
     *   "Cout de la vie en Thaïlande en 2026 : budget detaille"
     *   "Systeme de sante en Thaïlande : guide complet"
     *
     * We want the keyword to be the SUBJECT (left of the colon) rewritten
     * around the country, NOT the descriptor suffix (right of the colon).
     * The legacy implementation stripped the country then kept everything,
     * which made "en [descriptor]" leak into the primary keyword and
     * cascade into broken meta_titles downstream.
     */
    public function extractKeywords(string $topic, string $countryName): array
    {
        // 1. Strip year tokens ("(2026)", "2026")
        $clean = preg_replace('/\(\d{4}\)|\d{4}/', '', $topic);

        // 2. Take only the SUBJECT (left of the first colon). This drops the
        //    article-type descriptor that was polluting the keyword (e.g.
        //    "budget detaille", "guide complet", "toutes les options").
        if (mb_strpos($clean, ':') !== false) {
            $clean = mb_substr($clean, 0, mb_strpos($clean, ':'));
        }

        // 3. Strip expatriation boilerplate, keep the country name this time
        //    — it's what anchors the SEO keyword geographically.
        $clean = str_ireplace(
            [
                'en tant qu\'expatrie',
                'en tant qu\'expatrié',
                'en tant qu\'etranger',
                'en tant qu\'étranger',
                'pour les expatries',
                'pour les expatriés',
                'pour expatries',
                'pour expatriés',
            ],
            '',
            $clean,
        );

        // 4. Normalize whitespace
        $clean = preg_replace('/\s+/', ' ', trim($clean));

        // 5. Ensure the country name is present in the primary keyword
        //    (case-insensitive). If the subject doesn't mention it
        //    explicitly, append it so "cout de la vie" becomes
        //    "cout de la vie thailande".
        $primary = mb_strtolower($clean);
        $lowerCountry = mb_strtolower($countryName);
        if ($primary === '' || mb_stripos($primary, $lowerCountry) === false) {
            $primary = trim($primary . ' ' . $lowerCountry);
        }

        // 6. Long-tail variant: "[country] [subject]" ordering
        $withCountry = $lowerCountry . ' ' . mb_strtolower($clean);

        return array_values(array_filter([
            $primary,
            $withCountry,
            $lowerCountry . ' expatrie',
        ], fn ($v) => is_string($v) && $v !== ''));
    }
}
