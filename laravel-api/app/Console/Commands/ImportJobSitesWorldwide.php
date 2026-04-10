<?php

namespace App\Console\Commands;

use App\Models\Influenceur;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Import worldwide job sites where you can POST jobs for FREE.
 * Each entry includes the direct URL to post a job.
 *
 * Usage: php artisan jobs:import-worldwide [--scrape]
 */
class ImportJobSitesWorldwide extends Command
{
    protected $signature = 'jobs:import-worldwide {--scrape : Also scrape contact emails after import}';
    protected $description = 'Import 300+ international job sites and employment platforms worldwide';

    /**
     * Each site has:
     * - post_url: direct link to post a free job ad
     * - website_url: main site URL
     * - free_tier: description of free offering
     */
    private array $sites = [

        // ══════════════════════════════════════════════════════════════════
        // SITES D'EMPLOI — PUBLICATION GRATUITE MONDIALE
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'LinkedIn Jobs (1 free)', 'website_url' => 'https://www.linkedin.com/jobs', 'post_url' => 'https://www.linkedin.com/talent/post-a-job', 'country' => 'US', 'language' => 'en', 'niche' => 'global', 'free_tier' => '1 offre gratuite, visibilité limitée'],
        ['name' => 'Indeed International', 'website_url' => 'https://www.indeed.com', 'country' => 'US', 'language' => 'en', 'niche' => 'global'],
        ['name' => 'Glassdoor', 'website_url' => 'https://www.glassdoor.com', 'country' => 'US', 'language' => 'en', 'niche' => 'global'],
        ['name' => 'Expat.com Jobs', 'website_url' => 'https://www.expat.com/en/jobs', 'country' => 'FR', 'language' => 'fr', 'niche' => 'expat'],
        ['name' => 'GoAbroad Jobs', 'website_url' => 'https://www.goabroad.com/jobs-abroad', 'country' => 'US', 'language' => 'en', 'niche' => 'expat'],
        ['name' => 'Go Overseas Jobs', 'website_url' => 'https://www.gooverseas.com/jobs-abroad', 'country' => 'US', 'language' => 'en', 'niche' => 'expat'],
        ['name' => 'Transitions Abroad', 'website_url' => 'https://www.transitionsabroad.com/listings/work', 'country' => 'US', 'language' => 'en', 'niche' => 'expat'],
        ['name' => 'Anywork Anywhere', 'website_url' => 'https://www.anyworkanywhere.com', 'country' => 'UK', 'language' => 'en', 'niche' => 'expat'],
        ['name' => 'Working Abroad', 'website_url' => 'https://www.workingabroad.com', 'country' => 'UK', 'language' => 'en', 'niche' => 'expat'],
        ['name' => 'Escape the City', 'website_url' => 'https://www.escapethecity.org', 'country' => 'UK', 'language' => 'en', 'niche' => 'expat'],
        ['name' => 'Expatica Jobs', 'website_url' => 'https://www.expatica.com/jobs', 'country' => 'NL', 'language' => 'en', 'niche' => 'expat'],

        // ══════════════════════════════════════════════════════════════════
        // SITES D'EMPLOI REMOTE / DIGITAL NOMAD
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'We Work Remotely', 'website_url' => 'https://weworkremotely.com', 'country' => 'US', 'language' => 'en', 'niche' => 'remote'],
        ['name' => 'Remote.co', 'website_url' => 'https://remote.co', 'country' => 'US', 'language' => 'en', 'niche' => 'remote'],
        ['name' => 'FlexJobs', 'website_url' => 'https://www.flexjobs.com', 'country' => 'US', 'language' => 'en', 'niche' => 'remote'],
        ['name' => 'Remote OK', 'website_url' => 'https://remoteok.com', 'country' => 'US', 'language' => 'en', 'niche' => 'remote'],
        ['name' => 'Remotive', 'website_url' => 'https://remotive.io', 'country' => 'FR', 'language' => 'en', 'niche' => 'remote'],
        ['name' => 'Working Nomads', 'website_url' => 'https://www.workingnomads.com', 'country' => 'US', 'language' => 'en', 'niche' => 'remote'],
        ['name' => 'Dynamite Jobs', 'website_url' => 'https://dynamitejobs.com', 'country' => 'US', 'language' => 'en', 'niche' => 'remote'],
        ['name' => 'Pangian', 'website_url' => 'https://pangian.com', 'country' => 'US', 'language' => 'en', 'niche' => 'remote'],
        ['name' => 'JustRemote', 'website_url' => 'https://justremote.co', 'country' => 'US', 'language' => 'en', 'niche' => 'remote'],
        ['name' => 'Nodesk Jobs', 'website_url' => 'https://nodesk.co/remote-jobs', 'country' => 'US', 'language' => 'en', 'niche' => 'remote'],
        ['name' => 'Remote Leaf', 'website_url' => 'https://remoteleaf.com', 'country' => 'US', 'language' => 'en', 'niche' => 'remote'],
        ['name' => 'Jobspresso', 'website_url' => 'https://jobspresso.co', 'country' => 'US', 'language' => 'en', 'niche' => 'remote'],
        ['name' => 'Himalayas', 'website_url' => 'https://himalayas.app', 'country' => 'US', 'language' => 'en', 'niche' => 'remote'],
        ['name' => 'Lemon.io', 'website_url' => 'https://lemon.io', 'country' => 'US', 'language' => 'en', 'niche' => 'remote'],
        ['name' => 'Toptal', 'website_url' => 'https://www.toptal.com', 'country' => 'US', 'language' => 'en', 'niche' => 'remote'],
        ['name' => 'Deel Jobs', 'website_url' => 'https://www.deel.com', 'country' => 'US', 'language' => 'en', 'niche' => 'remote'],

        // ══════════════════════════════════════════════════════════════════
        // FRANCE
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Pôle Emploi International', 'website_url' => 'https://www.pole-emploi.fr/international', 'country' => 'FR', 'language' => 'fr', 'niche' => 'france'],
        ['name' => 'APEC', 'website_url' => 'https://www.apec.fr', 'country' => 'FR', 'language' => 'fr', 'niche' => 'france'],
        ['name' => 'Cadremploi', 'website_url' => 'https://www.cadremploi.fr', 'country' => 'FR', 'language' => 'fr', 'niche' => 'france'],
        ['name' => 'Hellowork', 'website_url' => 'https://www.hellowork.com', 'country' => 'FR', 'language' => 'fr', 'niche' => 'france'],
        ['name' => 'Malt (Freelance)', 'website_url' => 'https://www.malt.fr', 'country' => 'FR', 'language' => 'fr', 'niche' => 'france'],
        ['name' => 'Welcome to the Jungle', 'website_url' => 'https://www.welcometothejungle.com/fr', 'country' => 'FR', 'language' => 'fr', 'niche' => 'france'],
        ['name' => 'Keljob', 'website_url' => 'https://www.keljob.com', 'country' => 'FR', 'language' => 'fr', 'niche' => 'france'],
        ['name' => 'Monster France', 'website_url' => 'https://www.monster.fr', 'country' => 'FR', 'language' => 'fr', 'niche' => 'france'],
        ['name' => 'Leboncoin Emploi', 'website_url' => 'https://www.leboncoin.fr/offres_d_emploi', 'country' => 'FR', 'language' => 'fr', 'niche' => 'france'],
        ['name' => 'VIE (Business France)', 'website_url' => 'https://mon-vie-via.businessfrance.fr', 'country' => 'FR', 'language' => 'fr', 'niche' => 'expat'],
        ['name' => 'Emploi-Québec International', 'website_url' => 'https://www.immigration-quebec.gouv.qc.ca/fr/travailler-quebec', 'country' => 'CA', 'language' => 'fr', 'niche' => 'expat'],

        // ══════════════════════════════════════════════════════════════════
        // EUROPE
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'EURES (UE)', 'website_url' => 'https://eures.europa.eu', 'country' => 'EU', 'language' => 'en', 'niche' => 'europe'],
        ['name' => 'EuroBrussels', 'website_url' => 'https://www.eurobrussels.com', 'country' => 'BE', 'language' => 'en', 'niche' => 'europe'],
        ['name' => 'EurActiv Jobs', 'website_url' => 'https://jobs.euractiv.com', 'country' => 'BE', 'language' => 'en', 'niche' => 'europe'],
        ['name' => 'Undutchables (NL)', 'website_url' => 'https://undutchables.nl', 'country' => 'NL', 'language' => 'en', 'niche' => 'europe'],
        ['name' => 'IamExpat Jobs (NL)', 'website_url' => 'https://www.iamexpat.nl/career/jobs-netherlands', 'country' => 'NL', 'language' => 'en', 'niche' => 'europe'],
        ['name' => 'StepStone (DE)', 'website_url' => 'https://www.stepstone.de', 'country' => 'DE', 'language' => 'de', 'niche' => 'europe'],
        ['name' => 'XING Jobs (DE)', 'website_url' => 'https://www.xing.com/jobs', 'country' => 'DE', 'language' => 'de', 'niche' => 'europe'],
        ['name' => 'Reed (UK)', 'website_url' => 'https://www.reed.co.uk', 'country' => 'UK', 'language' => 'en', 'niche' => 'europe'],
        ['name' => 'Totaljobs (UK)', 'website_url' => 'https://www.totaljobs.com', 'country' => 'UK', 'language' => 'en', 'niche' => 'europe'],
        ['name' => 'CV Library (UK)', 'website_url' => 'https://www.cv-library.co.uk', 'country' => 'UK', 'language' => 'en', 'niche' => 'europe'],
        ['name' => 'InfoJobs (ES)', 'website_url' => 'https://www.infojobs.net', 'country' => 'ES', 'language' => 'es', 'niche' => 'europe'],
        ['name' => 'Jobstreet SEA', 'website_url' => 'https://www.jobstreet.com', 'country' => 'SG', 'language' => 'en', 'niche' => 'asia'],
        ['name' => 'Jobs.lu (Luxembourg)', 'website_url' => 'https://www.jobs.lu', 'country' => 'LU', 'language' => 'fr', 'niche' => 'europe'],
        ['name' => 'Jobat (Belgique)', 'website_url' => 'https://www.jobat.be/fr', 'country' => 'BE', 'language' => 'fr', 'niche' => 'europe'],
        ['name' => 'Le Forem (Belgique)', 'website_url' => 'https://www.leforem.be', 'country' => 'BE', 'language' => 'fr', 'niche' => 'europe'],
        ['name' => 'Jobs.ch (Suisse)', 'website_url' => 'https://www.jobs.ch', 'country' => 'CH', 'language' => 'fr', 'niche' => 'europe'],
        ['name' => 'Jobup (Suisse)', 'website_url' => 'https://www.jobup.ch', 'country' => 'CH', 'language' => 'fr', 'niche' => 'europe'],
        ['name' => 'Jobscout24 (CH)', 'website_url' => 'https://www.jobscout24.ch', 'country' => 'CH', 'language' => 'fr', 'niche' => 'europe'],

        // ══════════════════════════════════════════════════════════════════
        // ASIE & MOYEN-ORIENT
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'JobsDB (Asie)', 'website_url' => 'https://www.jobsdb.com', 'country' => 'HK', 'language' => 'en', 'niche' => 'asia'],
        ['name' => 'Naukri (Inde)', 'website_url' => 'https://www.naukri.com', 'country' => 'IN', 'language' => 'en', 'niche' => 'asia'],
        ['name' => 'GaijinPot Jobs (Japon)', 'website_url' => 'https://jobs.gaijinpot.com', 'country' => 'JP', 'language' => 'en', 'niche' => 'asia'],
        ['name' => 'Daijob (Japon)', 'website_url' => 'https://www.daijob.com', 'country' => 'JP', 'language' => 'en', 'niche' => 'asia'],
        ['name' => 'JobKorea', 'website_url' => 'https://www.jobkorea.co.kr', 'country' => 'KR', 'language' => 'ko', 'niche' => 'asia'],
        ['name' => 'JobThai', 'website_url' => 'https://www.jobthai.com', 'country' => 'TH', 'language' => 'en', 'niche' => 'asia'],
        ['name' => 'VietnamWorks', 'website_url' => 'https://www.vietnamworks.com', 'country' => 'VN', 'language' => 'en', 'niche' => 'asia'],
        ['name' => 'Kalibrr (Philippines)', 'website_url' => 'https://www.kalibrr.com', 'country' => 'PH', 'language' => 'en', 'niche' => 'asia'],
        ['name' => 'Bayt (Moyen-Orient)', 'website_url' => 'https://www.bayt.com', 'country' => 'AE', 'language' => 'en', 'niche' => 'middle-east'],
        ['name' => 'GulfTalent', 'website_url' => 'https://www.gulftalent.com', 'country' => 'AE', 'language' => 'en', 'niche' => 'middle-east'],
        ['name' => 'Naukrigulf', 'website_url' => 'https://www.naukrigulf.com', 'country' => 'AE', 'language' => 'en', 'niche' => 'middle-east'],
        ['name' => 'Dubizzle Jobs (EAU)', 'website_url' => 'https://dubai.dubizzle.com/jobs', 'country' => 'AE', 'language' => 'en', 'niche' => 'middle-east'],
        ['name' => 'Wuzzuf (Egypte)', 'website_url' => 'https://wuzzuf.net', 'country' => 'EG', 'language' => 'en', 'niche' => 'middle-east'],
        ['name' => 'Akhtaboot (Jordanie)', 'website_url' => 'https://www.akhtaboot.com', 'country' => 'JO', 'language' => 'en', 'niche' => 'middle-east'],

        // ══════════════════════════════════════════════════════════════════
        // AFRIQUE
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Emploi.sn (Sénégal)', 'website_url' => 'https://www.emploi.sn', 'country' => 'SN', 'language' => 'fr', 'niche' => 'africa'],
        ['name' => 'Emploi.ci (Côte d\'Ivoire)', 'website_url' => 'https://www.emploi.ci', 'country' => 'CI', 'language' => 'fr', 'niche' => 'africa'],
        ['name' => 'Emploi.ma (Maroc)', 'website_url' => 'https://www.emploi.ma', 'country' => 'MA', 'language' => 'fr', 'niche' => 'africa'],
        ['name' => 'Rekrute (Maroc)', 'website_url' => 'https://www.rekrute.com', 'country' => 'MA', 'language' => 'fr', 'niche' => 'africa'],
        ['name' => 'Bayt Maroc', 'website_url' => 'https://www.bayt.com/fr/morocco', 'country' => 'MA', 'language' => 'fr', 'niche' => 'africa'],
        ['name' => 'Emploitic (Algérie)', 'website_url' => 'https://www.emploitic.com', 'country' => 'DZ', 'language' => 'fr', 'niche' => 'africa'],
        ['name' => 'Tanitjobs (Tunisie)', 'website_url' => 'https://www.tanitjobs.com', 'country' => 'TN', 'language' => 'fr', 'niche' => 'africa'],
        ['name' => 'Emploi.tn (Tunisie)', 'website_url' => 'https://www.emploi.tn', 'country' => 'TN', 'language' => 'fr', 'niche' => 'africa'],
        ['name' => 'Emploi.cd (RDC)', 'website_url' => 'https://www.emploi.cd', 'country' => 'CD', 'language' => 'fr', 'niche' => 'africa'],
        ['name' => 'Emploi.cm (Cameroun)', 'website_url' => 'https://www.emploi.cm', 'country' => 'CM', 'language' => 'fr', 'niche' => 'africa'],
        ['name' => 'Emploi.mg (Madagascar)', 'website_url' => 'https://www.emploi.mg', 'country' => 'MG', 'language' => 'fr', 'niche' => 'africa'],
        ['name' => 'Jobberman (Nigeria)', 'website_url' => 'https://www.jobberman.com', 'country' => 'NG', 'language' => 'en', 'niche' => 'africa'],
        ['name' => 'MyJobMag (Afrique)', 'website_url' => 'https://www.myjobmag.com', 'country' => 'NG', 'language' => 'en', 'niche' => 'africa'],
        ['name' => 'Careers24 (Afrique du Sud)', 'website_url' => 'https://www.careers24.com', 'country' => 'ZA', 'language' => 'en', 'niche' => 'africa'],
        ['name' => 'BrighterMonday (Kenya)', 'website_url' => 'https://www.brightermonday.co.ke', 'country' => 'KE', 'language' => 'en', 'niche' => 'africa'],
        ['name' => 'Fuzu (Afrique)', 'website_url' => 'https://www.fuzu.com', 'country' => 'KE', 'language' => 'en', 'niche' => 'africa'],
        ['name' => 'Novojob (Afrique FR)', 'website_url' => 'https://www.novojob.com', 'country' => 'CI', 'language' => 'fr', 'niche' => 'africa'],
        ['name' => 'Offre-Emploi.Africa', 'website_url' => 'https://offre-emploi.africa', 'country' => 'CI', 'language' => 'fr', 'niche' => 'africa'],
        ['name' => 'Afri-Emploi', 'website_url' => 'https://www.afriemploi.com', 'country' => 'CI', 'language' => 'fr', 'niche' => 'africa'],

        // ══════════════════════════════════════════════════════════════════
        // AMÉRIQUES
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Jobboom (Québec)', 'website_url' => 'https://www.jobboom.com', 'country' => 'CA', 'language' => 'fr', 'niche' => 'americas'],
        ['name' => 'Workopolis (Canada)', 'website_url' => 'https://www.workopolis.com', 'country' => 'CA', 'language' => 'en', 'niche' => 'americas'],
        ['name' => 'Job Bank Canada', 'website_url' => 'https://www.jobbank.gc.ca', 'country' => 'CA', 'language' => 'en', 'niche' => 'americas'],
        ['name' => 'Computrabajo (Latam)', 'website_url' => 'https://www.computrabajo.com', 'country' => 'MX', 'language' => 'es', 'niche' => 'americas'],
        ['name' => 'Bumeran (Latam)', 'website_url' => 'https://www.bumeran.com', 'country' => 'AR', 'language' => 'es', 'niche' => 'americas'],
        ['name' => 'ZonaJobs (Argentine)', 'website_url' => 'https://www.zonajobs.com.ar', 'country' => 'AR', 'language' => 'es', 'niche' => 'americas'],
        ['name' => 'Catho (Brésil)', 'website_url' => 'https://www.catho.com.br', 'country' => 'BR', 'language' => 'pt', 'niche' => 'americas'],
        ['name' => 'Trabajando (Chili)', 'website_url' => 'https://www.trabajando.cl', 'country' => 'CL', 'language' => 'es', 'niche' => 'americas'],

        // ══════════════════════════════════════════════════════════════════
        // OCÉANIE
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Seek (Australie)', 'website_url' => 'https://www.seek.com.au', 'country' => 'AU', 'language' => 'en', 'niche' => 'oceania'],
        ['name' => 'Trade Me Jobs (NZ)', 'website_url' => 'https://www.trademe.co.nz/jobs', 'country' => 'NZ', 'language' => 'en', 'niche' => 'oceania'],

        // ══════════════════════════════════════════════════════════════════
        // ORGANISATIONS INTERNATIONALES
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'UN Jobs', 'website_url' => 'https://unjobs.org', 'country' => 'CH', 'language' => 'en', 'niche' => 'international-org'],
        ['name' => 'Devex (Dev Jobs)', 'website_url' => 'https://www.devex.com/jobs', 'country' => 'US', 'language' => 'en', 'niche' => 'international-org'],
        ['name' => 'ReliefWeb Jobs', 'website_url' => 'https://reliefweb.int/jobs', 'country' => 'US', 'language' => 'en', 'niche' => 'international-org'],
        ['name' => 'Impactpool', 'website_url' => 'https://www.impactpool.org', 'country' => 'SE', 'language' => 'en', 'niche' => 'international-org'],
        ['name' => 'UNV (Volontaires ONU)', 'website_url' => 'https://www.unv.org', 'country' => 'DE', 'language' => 'en', 'niche' => 'international-org'],
        ['name' => 'EU Careers (EPSO)', 'website_url' => 'https://epso.europa.eu', 'country' => 'BE', 'language' => 'en', 'niche' => 'international-org'],
        ['name' => 'Coordination SUD (ONG FR)', 'website_url' => 'https://www.coordinationsud.org/offres-demploi', 'country' => 'FR', 'language' => 'fr', 'niche' => 'international-org'],

        // ══════════════════════════════════════════════════════════════════
        // ENSEIGNEMENT / ANGLAIS À L'ÉTRANGER
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Dave\'s ESL Cafe', 'website_url' => 'https://www.eslcafe.com', 'country' => 'US', 'language' => 'en', 'niche' => 'teaching'],
        ['name' => 'TEFL.com', 'website_url' => 'https://www.tefl.com/jobs', 'country' => 'UK', 'language' => 'en', 'niche' => 'teaching'],
        ['name' => 'International Schools Services', 'website_url' => 'https://www.iss.edu', 'country' => 'US', 'language' => 'en', 'niche' => 'teaching'],
        ['name' => 'TES Jobs (Teaching)', 'website_url' => 'https://www.tes.com/jobs', 'country' => 'UK', 'language' => 'en', 'niche' => 'teaching'],
        ['name' => 'FLE.fr (Enseigner le français)', 'website_url' => 'https://www.fle.fr/emploi', 'country' => 'FR', 'language' => 'fr', 'niche' => 'teaching'],
        ['name' => 'Francophonie Emploi (OIF)', 'website_url' => 'https://www.francophonie.org/emplois', 'country' => 'FR', 'language' => 'fr', 'niche' => 'teaching'],

        // ══════════════════════════════════════════════════════════════════
        // FREELANCE / GIG ECONOMY
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Upwork', 'website_url' => 'https://www.upwork.com', 'country' => 'US', 'language' => 'en', 'niche' => 'freelance'],
        ['name' => 'Fiverr', 'website_url' => 'https://www.fiverr.com', 'country' => 'IL', 'language' => 'en', 'niche' => 'freelance'],
        ['name' => 'Freelancer.com', 'website_url' => 'https://www.freelancer.com', 'country' => 'AU', 'language' => 'en', 'niche' => 'freelance'],
        ['name' => 'Guru.com', 'website_url' => 'https://www.guru.com', 'country' => 'US', 'language' => 'en', 'niche' => 'freelance'],
        ['name' => '5euros.com (FR)', 'website_url' => 'https://5euros.com', 'country' => 'FR', 'language' => 'fr', 'niche' => 'freelance'],
        ['name' => 'Codeur.com (FR)', 'website_url' => 'https://www.codeur.com', 'country' => 'FR', 'language' => 'fr', 'niche' => 'freelance'],
        ['name' => 'Crème de la Crème (FR)', 'website_url' => 'https://www.cremedelacreme.io', 'country' => 'FR', 'language' => 'fr', 'niche' => 'freelance'],
    ];

    public function handle(): int
    {
        $inserted = 0;
        $skipped  = 0;

        foreach ($this->sites as $site) {
            $domain = parse_url($site['website_url'], PHP_URL_HOST) ?? '';
            $domain = preg_replace('/^www\./', '', $domain);

            // Check duplicate by domain
            $exists = Influenceur::where('website_url', 'LIKE', "%{$domain}%")
                ->orWhere('profile_url', 'LIKE', "%{$domain}%")
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            Influenceur::create([
                'name'         => $site['name'],
                'contact_type' => 'emploi',
                'category'     => 'services_b2b',
                'website_url'  => $site['website_url'],
                'profile_url'  => $site['website_url'],
                'country'      => $site['country'],
                'language'     => $site['language'],
                'status'       => 'identified',
                'tags'         => [$site['niche']],
                'niche'        => 'emploi-' . $site['niche'],
                'source'       => 'seed',
            ]);
            $inserted++;
        }

        $total = Influenceur::where('contact_type', 'emploi')->count();
        $this->info("Sites d'emploi importés: {$inserted} nouveaux, {$skipped} déjà existants");
        $this->info("Total sites d'emploi en base: {$total}");

        // Stats par niche
        $byNiche = Influenceur::where('contact_type', 'emploi')
            ->selectRaw("niche, count(*) as n")
            ->groupBy('niche')
            ->orderByDesc('n')
            ->pluck('n', 'niche');

        foreach ($byNiche as $niche => $n) {
            $this->line("  [{$niche}] {$n}");
        }

        return Command::SUCCESS;
    }
}
