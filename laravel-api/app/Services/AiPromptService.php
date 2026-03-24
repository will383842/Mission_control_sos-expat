<?php

namespace App\Services;

/**
 * Generates AI research prompts per contact type + country.
 *
 * IMPORTANT: These prompts are sent to PERPLEXITY (web search engine).
 * They must contain SEARCH KEYWORDS, not formatting instructions.
 * Claude handles the structuring AFTER Perplexity returns raw web results.
 */
class AiPromptService
{
    public function buildPrompt(string $contactType, string $country, string $language = 'fr', array $excludeUrls = []): string
    {
        $exclusionBlock = '';
        if (!empty($excludeUrls)) {
            $exclusionBlock = "\n\nEXCLURE ces contacts (déjà dans notre base) :\n"
                . implode("\n", array_slice($excludeUrls, 0, 50))
                . "\n\nNe propose QUE des contacts qui ne sont PAS dans cette liste.";
        }

        // 1. Try DB prompt first (admin-editable)
        $dbPrompt = \App\Models\AiPrompt::getFor($contactType);
        if ($dbPrompt) {
            // Replace {{PAYS}} and {{LANGUE}} placeholders
            $prompt = str_replace(
                ['{{PAYS}}', '{{LANGUE}}', '{{pays}}', '{{langue}}'],
                [$country, $language, $country, $language],
                $dbPrompt
            );
            return $prompt . $exclusionBlock;
        }

        // 2. Fallback to hardcoded prompts (new type names)
        $prompt = match ($contactType) {
            'ecole' => $this->schoolPrompt($country, $language),
            'consulat' => $this->consulatPrompt($country, $language),
            'influenceur' => $this->influenceurPrompt($country, $language),
            'blog' => $this->bloggerPrompt($country, $language),
            'association' => $this->associationPrompt($country, $language),
            'presse' => $this->pressePrompt($country, $language),
            'podcast_radio' => $this->podcastRadioPrompt($country, $language),
            'communaute_expat' => $this->communauteExpatPrompt($country, $language),
            'backlink' => $this->backlinkPrompt($country, $language),
            'immobilier' => $this->realEstatePrompt($country, $language),
            'traducteur' => $this->translatorPrompt($country, $language),
            'agence_voyage' => $this->travelAgencyPrompt($country, $language),
            'assurance' => $this->enterprisePrompt($country, $language, 'assurance'),
            'avocat' => $this->lawyerPrompt($country, $language),
            'emploi' => $this->jobBoardPrompt($country, $language),
            'groupe_whatsapp_telegram' => $this->groupAdminPrompt($country, $language),
            'chambre_commerce' => $this->partnerPrompt($country, $language),
            'partenaire' => $this->partnerPrompt($country, $language),
            // Legacy names (backward compat)
            'school' => $this->schoolPrompt($country, $language),
            'press' => $this->pressePrompt($country, $language),
            'blogger' => $this->bloggerPrompt($country, $language),
            'consulats' => $this->consulatPrompt($country, $language),
            default => $this->genericPrompt($country, $language, $contactType),
        };

        return $prompt . $exclusionBlock;
    }

    // =========================================================================
    // CHAQUE PROMPT = des MOTS-CLÉS DE RECHERCHE pour Perplexity
    // + une demande de format NOM/EMAIL/URL/TEL pour structurer les résultats
    // =========================================================================

    private function schoolPrompt(string $country, string $lang): string
    {
        return <<<PROMPT
MISSION : Trouver la liste EXHAUSTIVE et COMPLÈTE de TOUTES les écoles françaises, francophones et bilingues français en {$country}. Je veux un MINIMUM de 10-20 résultats. Ne t'arrête pas aux 5 premiers.

CATÉGORIES À CHERCHER (toutes) :
1. Écoles du réseau AEFE (homologuées)
2. Écoles du réseau MLF (Mission Laïque Française)
3. Écoles partenaires AEFE
4. Écoles françaises privées hors réseau
5. Sections françaises dans des écoles internationales
6. Écoles bilingues français-anglais ou français-langue locale
7. Crèches et maternelles francophones
8. Alliances françaises avec programmes scolaires
9. Instituts français avec cours pour enfants

MOTS-CLÉS de recherche à utiliser :
- "lycée français {$country}"
- "école française {$country}"
- "école francophone {$country}"
- "french school {$country}"
- "école bilingue français {$country}"
- "bilingual french school {$country}"
- "école internationale francophone {$country}"
- "crèche française {$country}"
- "French kindergarten {$country}"
- "établissement homologué {$country}"
- "section française {$country}"
- "alliance française {$country} cours enfants"

IMPORTANT : Cherche dans TOUTES les villes du pays, pas seulement la capitale. Inclus aussi les petites structures.

⚠️ RÈGLE ABSOLUE — URL :
- L'URL doit être le SITE WEB PROPRE de l'école elle-même (ex: lyceefrancais-machin.org, ecole-bilingue-truc.com)
- JAMAIS une URL de répertoire, annuaire ou site gouvernemental comme : aefe.fr, mlfmonde.org, education.gouv.fr, onisep.fr, letudiant.fr, campusfrance.org, diplomatie.gouv.fr, expat.com, internations.org, lepetitjournal.com, wikipedia.org, odyssey.education, french-schools.org
- Si tu ne trouves pas le site web propre de l'école, mets URL: INCONNU — ne mets JAMAIS un lien de répertoire à la place
- Chaque contact doit être UNE école individuelle, PAS un article qui LISTE des écoles

Pour CHAQUE école trouvée, donne :
NOM: nom complet officiel de l'école
EMAIL: email trouvé sur LEUR PROPRE site web (page contact, admissions, secrétariat)
TEL: téléphone avec indicatif international
URL: site web PROPRE de l'école (voir règle ci-dessus)
DIRECTEUR: nom du directeur/proviseur si disponible
SOURCE: URL où tu as trouvé l'info
PROMPT;
    }

    private function erasmusPrompt(string $country, string $lang): string
    {
        return <<<PROMPT
Cherche sur le web les universités en {$country} qui participent au programme Erasmus+ et qui accueillent des étudiants francophones.

Mots-clés :
- "Erasmus+ {$country}"
- "université {$country} relations internationales"
- "international office {$country} university"
- "échange universitaire {$country}"
- "study abroad {$country} french"
- site:erasmus-plus.ec.europa.eu {$country}

⚠️ RÈGLE ABSOLUE — URL :
- L'URL doit être le SITE WEB PROPRE de l'université (ex: universite-machin.edu, uni-truc.ac.be)
- JAMAIS un répertoire comme : erasmus-plus.ec.europa.eu, campusfrance.org, education.gouv.fr, onisep.fr, letudiant.fr, wikipedia.org, expat.com
- Si tu ne trouves pas le site propre, mets URL: INCONNU
- Chaque contact = UNE université, PAS un article qui en liste plusieurs

Pour chaque université : NOM, EMAIL (bureau international), TEL, URL (site PROPRE de l'université), COORDINATEUR Erasmus si trouvé, SOURCE.
PROMPT;
    }

    private function jobBoardPrompt(string $country, string $lang): string
    {
        return <<<PROMPT
Cherche les sites d'offres d'emploi en {$country} où on peut publier gratuitement une annonce pour recruter des freelances ou chatters francophones.

Mots-clés :
- "site emploi gratuit {$country}"
- "post job free {$country}"
- "offre emploi francophone {$country}"
- "freelance job board {$country}"

⚠️ RÈGLE ABSOLUE — URL :
- L'URL doit être le SITE WEB PROPRE du job board (ex: jobs-machin.com)
- JAMAIS un répertoire ou agrégateur comme : indeed.com, glassdoor.com, wikipedia.org, expat.com
- Si tu ne trouves pas le site propre, mets URL: INCONNU
- Chaque contact = UN site d'emploi, PAS un article qui en liste plusieurs

Pour chaque : NOM du site, URL (site PROPRE), EMAIL (si page contact trouvée), SOURCE.
PROMPT;
    }

    private function influenceurPrompt(string $country, string $lang): string
    {
        return <<<PROMPT
Cherche sur le web des influenceurs francophones qui vivent en {$country} ou créent du contenu sur l'expatriation/voyage en {$country}.

Mots-clés :
- "influenceur français {$country}"
- "expatrié {$country} YouTube"
- "vie en {$country} Instagram français"
- "digital nomad {$country} francophone"
- "créateur contenu français {$country}"
- "french influencer {$country}"

Cherche sur Instagram, TikTok, YouTube, et les blogs.

⚠️ RÈGLE ABSOLUE — URL :
- L'URL doit être le lien DIRECT vers le profil de l'influenceur ou son site personnel
- JAMAIS un article de blog/média qui LISTE des influenceurs (lepetitjournal.com, expat.com, etc.)
- Chaque contact = UN influenceur individuel, PAS un article qui en liste plusieurs

Pour chaque profil trouvé : NOM (pseudo), PLATEFORME, ABONNÉS, EMAIL (cherche sur leur site/bio/page contact), URL (lien DIRECT vers leur profil), SOURCE.
PROMPT;
    }

    private function tiktokerPrompt(string $country, string $lang): string
    {
        return <<<PROMPT
Cherche sur TikTok et le web des créateurs TikTok francophones qui font du contenu sur {$country}, la vie en {$country}, ou l'expatriation en {$country}.

Mots-clés :
- "tiktok français {$country}"
- "tiktoker expatrié {$country}"
- "vie à {$country} tiktok francophone"
- "{$country} expat tiktok french"

⚠️ RÈGLE ABSOLUE — URL :
- L'URL doit être le lien DIRECT TikTok du créateur (tiktok.com/@pseudo)
- JAMAIS un article qui LISTE des tiktokers (lepetitjournal.com, expat.com, buzzfeed.com, etc.)
- Chaque contact = UN tiktoker individuel

Pour chaque : NOM (pseudo TikTok avec @), ABONNÉS, EMAIL (si trouvé sur leur site/bio), URL (lien DIRECT tiktok.com/@...), SOURCE.
PROMPT;
    }

    private function youtuberPrompt(string $country, string $lang): string
    {
        return <<<PROMPT
Cherche sur YouTube et le web des chaînes YouTube francophones sur l'expatriation, le voyage ou la vie quotidienne en {$country}.

Mots-clés :
- "youtube français {$country}"
- "chaîne youtube expatrié {$country}"
- "vivre en {$country} youtube"
- "s'installer en {$country} vlog"
- "{$country} expat vlog french"

⚠️ RÈGLE ABSOLUE — URL :
- L'URL doit être le lien DIRECT YouTube de la chaîne (youtube.com/@pseudo)
- JAMAIS un article qui LISTE des chaînes YouTube
- Chaque contact = UNE chaîne YouTube individuelle

Pour chaque chaîne : NOM, ABONNÉS, EMAIL (souvent dans "À propos"), URL (lien youtube.com/@... ou youtube.com/c/...), SOURCE.
PROMPT;
    }

    private function instagramerPrompt(string $country, string $lang): string
    {
        return <<<PROMPT
Cherche des créateurs Instagram francophones qui vivent en {$country} ou partagent du contenu expatriation/voyage en {$country}.

Mots-clés :
- "instagram français {$country}"
- "expatrié {$country} instagram"
- "photographe français {$country}"
- "{$country} life instagram francophone"

⚠️ RÈGLE ABSOLUE — URL :
- L'URL doit être le lien DIRECT Instagram du créateur (instagram.com/pseudo)
- JAMAIS un article qui LISTE des instagrameurs
- Chaque contact = UN compte Instagram individuel

Pour chaque : NOM (@pseudo), ABONNÉS, EMAIL (bio ou linktr.ee), URL (lien instagram.com/...), SOURCE.
PROMPT;
    }

    private function bloggerPrompt(string $country, string $lang): string
    {
        return <<<PROMPT
Cherche sur Google des blogs francophones écrits par des expatriés en {$country} ou sur le voyage/installation en {$country}.

Mots-clés :
- "blog expatrié {$country}"
- "blog français {$country}"
- "vivre en {$country} blog"
- "s'expatrier en {$country} témoignage"
- "blog voyage {$country} francophone"

⚠️ RÈGLE ABSOLUE — URL :
- L'URL doit être le SITE PROPRE du blog (ex: monblog-expat.com, vivre-a-bangkok.fr)
- JAMAIS un répertoire comme : expat.com, lepetitjournal.com, femmexpat.com, internations.org
- Si tu ne trouves pas le site propre du blog, mets URL: INCONNU
- Chaque contact = UN blog individuel, PAS un article qui en liste plusieurs

Pour chaque blog : NOM, AUTEUR, EMAIL (page contact du blog), URL (site PROPRE du blog), SOURCE.
PROMPT;
    }

    private function associationPrompt(string $country, string $lang): string
    {
        return <<<PROMPT
Cherche TOUTES les associations de Français et francophones expatriés en {$country}.

Mots-clés :
- "association français {$country}"
- "communauté française {$country}"
- "accueil des français {$country}"
- "UFE {$country}"
- "ADFE {$country}"
- "français du monde {$country}"
- "French expat association {$country}"

⚠️ RÈGLE ABSOLUE — URL :
- L'URL doit être le SITE WEB PROPRE de l'association (ex: ufe-japon.org, accueil-francais-berlin.de)
- JAMAIS un répertoire comme : expat.com, internations.org, femmexpat.com, lepetitjournal.com, diplomatie.gouv.fr, wikipedia.org
- Si tu ne trouves pas le site propre, mets URL: INCONNU
- Chaque contact = UNE association individuelle, PAS un article qui en liste plusieurs

Pour chaque : NOM, EMAIL (page contact), URL (site PROPRE de l'association), TEL, RESPONSABLE (président/contact), SOURCE.
PROMPT;
    }

    private function pressePrompt(string $country, string $lang): string
    {
        return <<<PROMPT
MISSION : Trouver les VRAIS médias, journaux et magazines francophones SPÉCIFIQUES à {$country} — PAS des sites globaux, PAS des blogs, PAS des associations.

TYPES DE MÉDIAS RECHERCHÉS (uniquement) :
1. Journaux en ligne francophones basés en {$country} (ex: gazettedeberlin.de, lepetitjournal.com/bangkok)
2. Magazines francophones sur la vie en {$country}
3. Web radios francophones en {$country}
4. Chaînes TV / web TV francophones locales en {$country}
5. Sites d'actualité francophones dédiés à {$country}

Mots-clés :
- "journal français {$country}"
- "journal francophone {$country}"
- "média francophone {$country}"
- "magazine français expatriés {$country}"
- "web radio française {$country}"
- "actualité française {$country}"
- "french language newspaper {$country}"
- "french media {$country}"

⚠️ RÈGLES STRICTES :
- UNIQUEMENT des médias/presse (journaux, magazines, radios, TV)
- PAS de blogs personnels (→ ils vont dans le type "blog")
- PAS d'associations, UFE, Accueil (→ ils vont dans le type "association")
- PAS de sites d'assurance, immobilier ou services (ACS, Expat Communication...)
- PAS de grands médias nationaux français (LeMonde, LeFigaro, France24, RFI, Capital...)
- PAS d'article SUR un média, mais le SITE DU MÉDIA lui-même
- L'URL doit être le SITE WEB PROPRE du média
- JAMAIS un répertoire (expat.com, internations.org, wikipedia.org, mondafrique.com, cfi.fr)
- Chaque contact = UN média individuel avec son propre site web

Pour chaque média trouvé :
NOM: nom exact du média
EMAIL: email de la rédaction (chercher sur page contact du média)
TEL: téléphone si trouvé
URL: site web PROPRE du média (PAS un article sur un autre site)
SOURCE: page web où tu as trouvé ce média
PROMPT;
    }

    private function backlinkPrompt(string $country, string $lang): string
    {
        return <<<PROMPT
Cherche des sites web francophones où SOS-Expat.com pourrait obtenir un backlink ou être référencé.

Mots-clés :
- "annuaire expatriés francophone"
- "annuaire services juridiques international"
- "annuaire startups françaises"
- "communiqué de presse gratuit francophone"
- "forum expatriés francophone"
- "guest post expatriation"
- "annuaire avocat international"
- "répertoire services expatriés"

⚠️ RÈGLE ABSOLUE — URL :
- L'URL doit être le SITE WEB PROPRE où poster (ex: annuaire-expats.fr, forum-expat.org)
- JAMAIS un gros site généraliste comme : google.com, wikipedia.org, amazon.com
- Chaque contact = UN site individuel, PAS un article qui en liste plusieurs

Pour chaque site : NOM, URL (site PROPRE), TYPE (annuaire/guest post/forum/communiqué), EMAIL (contact pour soumission), SOURCE.
PROMPT;
    }

    private function realEstatePrompt(string $country, string $lang): string
    {
        return <<<PROMPT
Cherche les agences immobilières spécialisées pour les expatriés en {$country} ou les agences de relocation.

Mots-clés :
- "agence immobilière expatriés {$country}"
- "relocation agency {$country}"
- "immobilier français {$country}"
- "real estate expat {$country}"
- "agence relocation francophone {$country}"

⚠️ RÈGLE ABSOLUE — URL :
- L'URL doit être le SITE WEB PROPRE de l'agence (ex: relocation-tokyo.com, expat-immo-dubai.com)
- JAMAIS un répertoire comme : expat.com, internations.org, seloger.com, tripadvisor.com, pagesjaunes.fr
- Si tu ne trouves pas le site propre, mets URL: INCONNU
- Chaque contact = UNE agence individuelle, PAS un article qui en liste plusieurs

Pour chaque agence : NOM, EMAIL (page contact), URL (site PROPRE de l'agence), TEL, SPÉCIALITÉ (location/vente/relocation), SOURCE.
PROMPT;
    }

    private function translatorPrompt(string $country, string $lang): string
    {
        return <<<PROMPT
Cherche les traducteurs assermentés et agences de traduction francophones en {$country}.

Mots-clés :
- "traducteur assermenté français {$country}"
- "traduction certifiée {$country}"
- "sworn translator french {$country}"
- "agence traduction francophone {$country}"
- "traducteur juridique français {$country}"

⚠️ RÈGLE ABSOLUE — URL :
- L'URL doit être le SITE WEB PROPRE du traducteur ou cabinet (ex: traduction-dupont.com)
- JAMAIS un répertoire comme : pagesjaunes.fr, kompass.com, societe.com, expat.com
- Si tu ne trouves pas le site propre, mets URL: INCONNU
- Chaque contact = UN traducteur ou cabinet individuel

Pour chaque : NOM, EMAIL, URL (site PROPRE), TEL, LANGUES, ASSERMENTÉ (oui/non), SOURCE.
PROMPT;
    }

    private function travelAgencyPrompt(string $country, string $lang): string
    {
        return <<<PROMPT
Cherche les agences de voyage et de relocation spécialisées pour les expatriés en {$country}.

Mots-clés :
- "agence voyage expatriés {$country}"
- "relocation service {$country}"
- "déménagement international {$country}"
- "travel agency expat {$country}"
- "agence installation {$country}"

⚠️ RÈGLE ABSOLUE — URL :
- L'URL doit être le SITE WEB PROPRE de l'agence (ex: demenagement-expat.com, reloc-service-berlin.de)
- JAMAIS un répertoire comme : expat.com, internations.org, tripadvisor.com, booking.com
- Si tu ne trouves pas le site propre, mets URL: INCONNU
- Chaque contact = UNE agence individuelle

Pour chaque : NOM, EMAIL, URL (site PROPRE), TEL, SERVICES proposés, SOURCE.
PROMPT;
    }

    private function enterprisePrompt(string $country, string $lang, string $type): string
    {
        $label = $type === 'insurer' ? 'assurance expatriés' : 'entreprises avec salariés expatriés';
        $keywords = $type === 'insurer'
            ? "\"assurance expatrié {$country}\", \"insurance expat {$country}\", \"mutuelle internationale {$country}\", \"CFE {$country}\""
            : "\"entreprise française {$country}\", \"French company {$country}\", \"CCI France {$country}\", \"chambre de commerce {$country}\"";

        return <<<PROMPT
Cherche les {$label} en {$country}.

Mots-clés :
- {$keywords}

⚠️ RÈGLE ABSOLUE — URL :
- L'URL doit être le SITE WEB PROPRE de l'entreprise/assureur (ex: axa-expat.com, april-international.com)
- JAMAIS un répertoire comme : expat.com, kompass.com, societe.com, glassdoor.com, indeed.com
- Si tu ne trouves pas le site propre, mets URL: INCONNU
- Chaque contact = UNE entreprise/assureur individuel

Pour chaque : NOM, EMAIL, URL (site PROPRE), TEL, CONTACT (responsable RH/mobilité si trouvé), SOURCE.
PROMPT;
    }

    private function partnerPrompt(string $country, string $lang): string
    {
        return <<<PROMPT
Cherche les institutions françaises et internationales qui accompagnent les expatriés en {$country}.

Mots-clés :
- "ambassade de France {$country}"
- "consulat français {$country}"
- "chambre de commerce France {$country}"
- "CCI France International {$country}"
- "Alliance française {$country}"
- "CCEF {$country}"
- "banque française {$country}"

⚠️ RÈGLE ABSOLUE — URL :
- L'URL doit être le SITE WEB PROPRE de l'institution (ex: ambafrance-jp.org, ccifrance-japon.or.jp)
- JAMAIS un répertoire comme : diplomatie.gouv.fr (sauf la page locale de l'ambassade), expat.com, wikipedia.org
- Si tu ne trouves pas le site propre, mets URL: INCONNU
- Chaque contact = UNE institution individuelle

Pour chaque : NOM, EMAIL, URL (site PROPRE de l'institution), TEL, TYPE (ambassade/chambre/alliance/banque), SOURCE.
PROMPT;
    }

    private function lawyerPrompt(string $country, string $lang): string
    {
        return <<<PROMPT
Cherche des avocats francophones en {$country} spécialisés en droit international, droit des étrangers, ou droit des affaires.

Mots-clés :
- "avocat français {$country}"
- "avocat francophone {$country}"
- "French lawyer {$country}"
- "cabinet d'avocats international {$country}"
- "avocat expatrié {$country}"
- "barreau de Paris {$country}"

⚠️ RÈGLE ABSOLUE — URL :
- L'URL doit être le SITE WEB PROPRE du cabinet d'avocats (ex: cabinet-dupont-avocats.com)
- JAMAIS un répertoire comme : pagesjaunes.fr, avocats.fr, kompass.com, societe.com, expat.com
- Si tu ne trouves pas le site propre, mets URL: INCONNU
- Chaque contact = UN avocat ou cabinet individuel

Pour chaque avocat/cabinet : NOM, EMAIL, URL (site PROPRE du cabinet), TEL, SPÉCIALITÉ, SOURCE.
PROMPT;
    }

    private function groupAdminPrompt(string $country, string $lang): string
    {
        return <<<PROMPT
Cherche les groupes Facebook et communautés en ligne francophones d'expatriés en {$country}.

Mots-clés :
- "groupe Facebook français {$country}"
- "communauté expatriés français {$country}"
- "French expats {$country} Facebook group"
- "forum expatriés {$country}"
- "WhatsApp français {$country}"

⚠️ RÈGLE ABSOLUE — URL :
- L'URL doit être le lien DIRECT vers le groupe Facebook/WhatsApp/Forum
- JAMAIS un article qui LISTE des groupes (lepetitjournal.com, expat.com, femmexpat.com)
- Chaque contact = UN groupe individuel, PAS un article qui en liste plusieurs

Pour chaque groupe : NOM, PLATEFORME (Facebook/WhatsApp/Forum), MEMBRES (nombre), URL (lien DIRECT du groupe), ADMIN (nom si visible), SOURCE.
PROMPT;
    }

    private function consulatPrompt(string $country, string $lang): string
    {
        return <<<PROMPT
MISSION : Trouver TOUS les consulats, ambassades et représentations diplomatiques francophones en {$country}.

CATÉGORIES À CHERCHER :
1. Ambassade de France en {$country}
2. Consulat(s) de France (général et honoraires)
3. Consulats de Belgique, Suisse, Canada, Luxembourg
4. Alliance Française en {$country}
5. Institut Français en {$country}
6. Chambre de Commerce Franco-{$country}
7. Conseillers des Français de l'étranger en {$country}
8. Services consulaires des pays francophones (Sénégal, Côte d'Ivoire, Tunisie, Maroc, etc.)

Mots-clés :
- "ambassade France {$country}"
- "consulat France {$country}"
- "consul honoraire France {$country}"
- "Alliance Française {$country}"
- "Institut Français {$country}"
- "CCIFI {$country}"
- "French embassy {$country}"

⚠️ RÈGLE ABSOLUE — URL :
- L'URL doit être le SITE WEB OFFICIEL du consulat/ambassade
- JAMAIS une page d'annuaire comme diplomatie.gouv.fr, france-consulat.org, etc.
- Si pas de site propre, donne l'URL de la fiche officielle sur le site du MAE
- Chaque contact = UN consulat/ambassade individuel

Pour chaque : NOM, EMAIL, URL (site OFFICIEL), TEL, ADRESSE, NOTES, SOURCE.
PROMPT;
    }

    private function communauteExpatPrompt(string $country, string $lang): string
    {
        return <<<PROMPT
MISSION : Trouver TOUTES les communautés, forums, plateformes et réseaux en ligne francophones pour expatriés en {$country}.

TYPES RECHERCHÉS :
1. Forums de discussion francophones sur {$country} (pas expat.com)
2. Sites communautaires pour les Français de {$country}
3. Groupes Facebook francophones en {$country} (avec lien direct)
4. Plateformes d'entraide entre expatriés francophones en {$country}
5. Sites de networking pour expatriés en {$country}
6. Pages "communauté" ou "vie locale" francophones

Mots-clés :
- "communauté française {$country}"
- "forum expatriés français {$country}"
- "français de {$country}"
- "vivre en {$country} communauté"
- "réseau français {$country}"
- "French community {$country}"
- "expats français {$country} forum"
- "groupe Facebook français {$country}"
- "entraide expatriés {$country}"

⚠️ RÈGLES STRICTES :
- PAS de grands agrégateurs (expat.com, internations.org, femmexpat.com)
- PAS d'associations (UFE, Accueil, ADFE → type "association")
- PAS de médias/presse (→ type "presse")
- L'URL doit être le SITE WEB PROPRE ou le LIEN DIRECT du groupe
- Chaque contact = UNE communauté individuelle

Pour chaque :
NOM: nom de la communauté/forum/groupe
EMAIL: email de contact si trouvé
TEL: téléphone si trouvé
URL: site web PROPRE ou lien direct du groupe
PLATEFORME: website/facebook/whatsapp/telegram/forum
SOURCE: page web où tu as trouvé cette communauté
PROMPT;
    }

    private function podcastRadioPrompt(string $country, string $lang): string
    {
        return <<<PROMPT
Cherche les podcasts et web radios francophones qui parlent de {$country}, de l'expatriation en {$country}, ou qui sont basés en {$country}.

Mots-clés :
- "podcast expatriation {$country}"
- "podcast français {$country}"
- "web radio française {$country}"
- "radio francophone {$country}"
- "podcast vivre à {$country}"
- "french podcast {$country}"

⚠️ RÈGLES :
- L'URL doit être le lien DIRECT vers le podcast (Apple Podcasts, Spotify, site propre, Ausha, etc.)
- PAS un article qui LISTE des podcasts
- Chaque contact = UN podcast ou UNE radio individuelle

Pour chaque :
NOM: nom du podcast ou de la radio
EMAIL: email de contact (page about/contact)
URL: lien direct vers le podcast ou site de la radio
PLATEFORME: apple_podcasts/spotify/website/ausha
SOURCE: page web où tu as trouvé ce podcast
PROMPT;
    }

    private function genericPrompt(string $country, string $lang, string $type): string
    {
        return <<<PROMPT
Cherche sur le web des contacts professionnels de type "{$type}" en lien avec l'expatriation francophone en {$country}.

Mots-clés :
- "{$type} francophone {$country}"
- "{$type} français {$country}"
- "{$type} expat {$country}"

⚠️ RÈGLE ABSOLUE — URL :
- L'URL doit être le SITE WEB PROPRE du contact
- JAMAIS un répertoire, annuaire ou article de blog qui LISTE des contacts
- Si tu ne trouves pas le site propre, mets URL: INCONNU
- Chaque contact = UN professionnel/organisme individuel

Pour chaque : NOM, EMAIL, URL (site PROPRE), TEL, NOTES, SOURCE.
PROMPT;
    }
}
