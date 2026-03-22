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

        // 2. Fallback to hardcoded prompts
        $prompt = match ($contactType) {
            'school' => $this->schoolPrompt($country, $language),
            'erasmus' => $this->erasmusPrompt($country, $language),
            'chatter', 'job_board' => $this->jobBoardPrompt($country, $language),
            'influenceur' => $this->influenceurPrompt($country, $language),
            'tiktoker' => $this->tiktokerPrompt($country, $language),
            'youtuber' => $this->youtuberPrompt($country, $language),
            'instagramer' => $this->instagramerPrompt($country, $language),
            'blogger' => $this->bloggerPrompt($country, $language),
            'association' => $this->associationPrompt($country, $language),
            'press' => $this->pressPrompt($country, $language),
            'backlink' => $this->backlinkPrompt($country, $language),
            'real_estate' => $this->realEstatePrompt($country, $language),
            'translator' => $this->translatorPrompt($country, $language),
            'travel_agency' => $this->travelAgencyPrompt($country, $language),
            'insurer', 'enterprise' => $this->enterprisePrompt($country, $language, $contactType),
            'partner' => $this->partnerPrompt($country, $language),
            'lawyer' => $this->lawyerPrompt($country, $language),
            'group_admin' => $this->groupAdminPrompt($country, $language),
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
1. Écoles du réseau AEFE (homologuées) — cherche sur aefe.fr/fr/etablissements la liste pour {$country}
2. Écoles du réseau MLF (Mission Laïque Française) — mlfmonde.org
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
- "AEFE {$country}" et chercher sur aefe.fr la liste complète
- "MLF {$country}" et chercher sur mlfmonde.org
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

Pour CHAQUE école trouvée, donne :
NOM: nom complet officiel
EMAIL: email trouvé sur leur site web (page contact, admissions, secrétariat)
TEL: téléphone avec indicatif international
URL: site web officiel (PAS aefe.fr ou mlfmonde.org, mais le VRAI site de l'école)
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

Pour chaque université : NOM, EMAIL (bureau international), TEL, URL (page mobilité internationale), COORDINATEUR Erasmus si trouvé, SOURCE.
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

Pour chaque : NOM du site, URL, EMAIL (si page contact trouvée), SOURCE.
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

Cherche sur Instagram, TikTok, YouTube, et les blogs. Pour chaque profil trouvé : NOM (pseudo), PLATEFORME, ABONNÉS, EMAIL (cherche sur leur site/bio/page contact), URL (lien DIRECT vers leur profil), SOURCE.
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

Pour chaque blog : NOM, AUTEUR, EMAIL (page contact du blog), URL (adresse du blog), SOURCE.
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

Pour chaque : NOM, EMAIL (page contact), URL (site web), TEL, RESPONSABLE (président/contact), SOURCE.
PROMPT;
    }

    private function pressPrompt(string $country, string $lang): string
    {
        return <<<PROMPT
Cherche les médias et journaux francophones qui couvrent l'expatriation en {$country} ou l'actualité des expatriés.

Mots-clés :
- "journal français {$country}"
- "média francophone {$country}"
- "lepetitjournal.com {$country}"
- "french media {$country}"
- "presse française expatriés {$country}"
- "magazine expatriation {$country}"

Pour chaque média : NOM, EMAIL (rédaction), URL, JOURNALISTE (nom si trouvé), SOURCE.
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

Pour chaque site : NOM, URL, TYPE (annuaire/guest post/forum/communiqué), EMAIL (contact pour soumission), SOURCE.
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

Pour chaque agence : NOM, EMAIL (page contact), URL (site web), TEL, SPÉCIALITÉ (location/vente/relocation), SOURCE.
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

Pour chaque : NOM, EMAIL, URL (site web), TEL, LANGUES, ASSERMENTÉ (oui/non), SOURCE.
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

Pour chaque : NOM, EMAIL, URL, TEL, SERVICES proposés, SOURCE.
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

Pour chaque : NOM, EMAIL, URL, TEL, CONTACT (responsable RH/mobilité si trouvé), SOURCE.
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

Pour chaque : NOM, EMAIL, URL (site officiel), TEL, TYPE (ambassade/chambre/alliance/banque), SOURCE.
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

Pour chaque avocat/cabinet : NOM, EMAIL, URL (site du cabinet), TEL, SPÉCIALITÉ, SOURCE.
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

Pour chaque groupe : NOM, PLATEFORME (Facebook/WhatsApp/Forum), MEMBRES (nombre), URL (lien du groupe), ADMIN (nom si visible), SOURCE.
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

Pour chaque : NOM, EMAIL, URL, TEL, NOTES, SOURCE.
PROMPT;
    }
}
