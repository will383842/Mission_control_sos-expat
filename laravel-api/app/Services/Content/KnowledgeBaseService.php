<?php

namespace App\Services\Content;

/**
 * Injects SOS-Expat.com Knowledge Base v2.0 into AI generation prompts.
 *
 * Call getSystemPrompt($contentType) to get the full system prompt
 * with Knowledge Base context for any content type.
 *
 * v2.0: 20 sections — identity, pricing, subscriptions, coverage, how_it_works,
 * audience, programs (7 types), payment (4 processors), legal, brand_voice,
 * content_rules (13 types), seo, tools, surveys, reviews, anti-fraud,
 * notifications, infrastructure, annuaire, anti-cannibalization.
 */
class KnowledgeBaseService
{
    private array $kb;

    public function __construct()
    {
        $this->kb = config('knowledge-base', []);
    }

    /**
     * Current KB version — persist this with every generated article so we
     * can identify which version produced it when rules change.
     */
    public function getVersion(): string
    {
        return $this->kb['meta']['kb_version'] ?? 'unknown';
    }

    /**
     * Last-verified date for the KB (ISO-8601).
     */
    public function getUpdatedAt(): string
    {
        return $this->kb['meta']['kb_updated_at'] ?? 'unknown';
    }

    /**
     * Full meta block — version, updated_at, source_of_truth, changelog.
     */
    public function getMeta(): array
    {
        return $this->kb['meta'] ?? [];
    }

    /**
     * Return the KB as a public-safe array, suitable for the cross-service
     * `/api/public/knowledge-base.json` endpoint and `kb:export-json` command.
     *
     * Includes: pricing, programs, brand voice, content rules, SEO/AEO rules,
     * audience, coverage, tools — everything content-generators need.
     *
     * Excludes: anti_fraud (rate limits, email blocker — security info),
     * backlink_engine (internal netlinking strategy), infrastructure
     * (region/function internals), notifications.telegram (bot handles),
     * cloudflare_edge_cache internals.
     *
     * Full export (same as config('knowledge-base')) is available via the
     * `kb:export-json --full` artisan command for internal use only.
     */
    public function toPublicArray(): array
    {
        $blacklist = [
            'anti_fraud',
            'backlink_engine',
            'infrastructure',
            'cloudflare_edge_cache',
        ];

        $publicKb = $this->kb;
        foreach ($blacklist as $key) {
            unset($publicKb[$key]);
        }

        // Strip telegram bot handles but keep public-facing notifications info
        if (isset($publicKb['notifications']['telegram'])) {
            unset($publicKb['notifications']['telegram']['bot_names']);
            unset($publicKb['notifications']['telegram']['bots']);
        }

        // Ensure meta stamp is present
        $publicKb['_served_at'] = now()->toIso8601String();
        $publicKb['_scope'] = 'public';

        return $publicKb;
    }

    /**
     * Full internal KB export (includes sensitive sections). Use for
     * internal backups / admin exports only — NEVER send to untrusted callers.
     */
    public function toFullArray(): array
    {
        $full = $this->kb;
        $full['_served_at'] = now()->toIso8601String();
        $full['_scope'] = 'full';
        return $full;
    }

    /**
     * Get the complete system prompt with Knowledge Base for a content type.
     */
    public function getSystemPrompt(string $contentType, ?string $country = null, ?string $language = null, ?string $searchIntent = null): string
    {
        $blocks = [
            $this->getIdentityBlock(),
            $this->getServicesBlock(),
            $this->getSubscriptionsBlock(),
            $this->getCoverageBlock(),
            $this->getHowItWorksBlock(),
            $this->getProgramsBlock($contentType),
            $this->getPaymentBlock(),
            $this->getLegalBlock(),
            $this->getBrandVoiceBlock(),
            $this->getToolsBlock(),
            $this->getAeoBlock(),
            $this->getSchemaBlock(),
            $this->getAntiCannibalizationBlock(),
            $this->getHelpfulContentBlock(),
        ];

        $contentRule = $this->getContentRule($contentType);
        $seoRules = $this->getSeoRulesBlock();

        $intentBlock = $searchIntent ? $this->getIntentBlock($searchIntent) : '';
        $disclaimerBlock = $this->getDisclaimerBlock($searchIntent, $contentType);

        $countryContext = $country
            ? "\nCONTEXTE PAYS : Cet article concerne specifiquement {$country}. Toutes les donnees, lois, prix, procedures doivent etre specifiques a ce pays.\n"
            : '';
        $langContext = $language
            ? "\nLANGUE DE GENERATION : {$language}\n"
            : '';

        // Audience context — injects the target nationalities for this language
        // (e.g. FR → France, Belgique, Suisse, Canada, Maroc, Tunisie…)
        // along with concrete banks, tax authorities and first names to use.
        $audienceContext = AudienceContextService::getContextFor($language);

        $kbContent = implode("\n\n", array_filter($blocks));

        return <<<PROMPT
=== SOS-EXPAT KNOWLEDGE BASE v2.0 (SOURCE DE VERITE ABSOLUE) ===

{$kbContent}

=== REGLES POUR CE TYPE DE CONTENU : {$contentType} ===
{$contentRule}

{$seoRules}
{$intentBlock}{$disclaimerBlock}{$countryContext}{$langContext}
{$audienceContext}

{$this->getHtmlTemplatesBlock()}

=== FIN KNOWLEDGE BASE ===

REGLES CRITIQUES :
- Ne JAMAIS inventer de donnees non presentes dans ce Knowledge Base.
- Ne JAMAIS modifier les prix, durees, taux de commission ou informations legales.
- Si tu n'es pas sur d'une information sur SOS-Expat.com, ne l'inclus pas.
- Toujours ecrire "SOS-Expat.com" avec un tiret.
PROMPT;
    }

    /**
     * Get a lighter prompt for specific content types that don't need full KB.
     * Used for news, Q/R where full KB would waste tokens.
     */
    public function getLightPrompt(string $contentType, ?string $country = null, ?string $language = null): string
    {
        $blocks = [
            $this->getIdentityBlock(),
            $this->getServicesBlock(),
            $this->getCoverageBlock(),
            $this->getBrandVoiceBlock(),
            $this->getAntiCannibalizationBlock(),
        ];

        $contentRule = $this->getContentRule($contentType);
        $seoRules = $this->getSeoRulesBlock();

        $countryContext = $country
            ? "\nCONTEXTE PAYS : {$country}\n"
            : '';
        $langContext = $language
            ? "\nLANGUE : {$language}\n"
            : '';

        // Audience context — per-language target nationalities and examples
        $audienceContext = AudienceContextService::getContextFor($language);

        $kbContent = implode("\n\n", array_filter($blocks));

        return <<<PROMPT
=== SOS-EXPAT KNOWLEDGE BASE (ESSENTIEL) ===

{$kbContent}

=== REGLES CONTENU : {$contentType} ===
{$contentRule}

{$seoRules}
{$countryContext}{$langContext}
{$audienceContext}

{$this->getHtmlTemplatesBlock()}

=== URLS INTERNES VALIDES (SEULES URLS AUTORISEES) ===
JAMAIS de www.sos-expat.com (le www n'existe PAS).
JAMAIS d'URL inventee. Utiliser UNIQUEMENT ces formats :
- Page prestataires : https://sos-expat.com/{lang}-{code_pays_iso_2}/{segment}
- Segments FR : prestataires, articles, vie-a-letranger, actualites-expats, outils, pays, annuaire
- Segments EN : providers, articles, living-abroad, expat-news, tools, countries, directory
- Exemples : https://sos-expat.com/fr-fr/prestataires, https://sos-expat.com/en-us/providers
- Le code pays est TOUJOURS 2 lettres ISO : fr, us, de, es, th, jp, ma, ch, be, etc.
- JAMAIS de nom de pays dans l'URL : /fr-france/ est FAUX → /fr-fr/ est CORRECT
- JAMAIS de /consultation, /recrutement, /blog/ — ces pages n'existent PAS
=== FIN KB ===

Ne JAMAIS inventer de donnees. Toujours ecrire "SOS-Expat.com" avec tiret.
PROMPT;
    }

    /**
     * Get just the Knowledge Base context for translations.
     */
    public function getTranslationContext(): string
    {
        return <<<PROMPT
=== SOS-EXPAT.com REFERENCE (NE PAS TRADUIRE CES TERMES) ===
- Nom exact : SOS-Expat.com (avec tiret et .com, ne PAS traduire)
- Service : plateforme de mise en relation telephonique avec des professionnels locaux (avocats ou experts) pour toute situation a l'etranger
- PUBLIC CIBLE LARGE : voyageurs, expatries, vacanciers, digital nomads, etudiants internationaux, investisseurs, retraites, et toute personne hors de son pays
- TYPES D'AIDE : juridique, administrative, pratique, medicale, financiere, conseil, orientation, urgence — PAS uniquement juridique
- Avocat partenaire : 49EUR / 55USD pour 20min | Expert local partenaire : 19EUR / 25USD pour 30min
- Le CLIENT paie — SOS-Expat.com prend des frais de mise en relation (19EUR/25USD avocat, 9EUR/15USD expert)
- L'avocat partenaire recoit : 30EUR/30USD | L'expert partenaire recoit : 10EUR/10USD
- Les avocats et experts sont des PARTENAIRES INDEPENDANTS
- 197 pays, 9 langues, disponible 24h/24 7j/7
- Mise en relation en moins de 5 minutes
- Ce n'est PAS un cabinet d'avocats, PAS une assurance, PAS gratuit, PAS un chatbot
- 5 programmes affilies : Chatter, Influenceur, Blogueur, Admin Groupe, Partenaire B2B
- Retrait minimum : \$30 | Frais retrait : \$3 fixe
- 4 moyens de paiement : Stripe (carte), PayPal, Wise, Flutterwave (Mobile Money Afrique)
- Entite legale : WorldExpat OU, Estonie

VOCABULAIRE INTERDIT dans toute traduction :
- Ne JAMAIS dire "pour les expatries" seul (dire : voyageurs, expatries, nomades, etudiants, investisseurs, toute personne a l'etranger)
- Ne JAMAIS dire "assistance juridique" seule (dire : aide, conseil, assistance, orientation)
- Ne JAMAIS inventer de chiffres (nombre d'avocats, de clients, de reviews)
=== FIN REFERENCE ===
PROMPT;
    }

    /**
     * Get program-specific prompt for affiliate/recruitment content.
     */
    public function getProgramPrompt(string $programType): string
    {
        $programs = $this->kb['programs'] ?? [];
        $program = $programs[$programType] ?? null;

        if (!$program) {
            return '';
        }

        $lines = ["PROGRAMME : {$program['name']}"];
        $lines[] = "Description : {$program['description']}";

        // Format commissions
        $commissionKeys = [
            'client_lawyer_call' => 'Commission appel avocat client',
            'client_expat_call' => 'Commission appel expert client',
            'n1_call_commission' => 'Commission N1 (filleul direct)',
            'n2_call_commission' => 'Commission N2 (filleul indirect)',
            'activation_bonus' => 'Bonus activation',
            'provider_referral_lawyer' => 'Affiliation prestataire avocat',
            'provider_referral_expat' => 'Affiliation prestataire expert',
            'telegram_bonus' => 'Bonus Telegram',
            'client_discount' => 'Reduction client',
        ];

        foreach ($commissionKeys as $key => $label) {
            if (isset($program[$key])) {
                $amount = $program[$key] / 100; // cents to dollars
                $lines[] = "- {$label} : \${$amount}";
            }
        }

        // Milestones
        if (isset($program['milestones'])) {
            $lines[] = "\nMilestones affiliation :";
            foreach ($program['milestones'] as $count => $bonus) {
                $lines[] = "- {$count} filleuls → \$" . ($bonus / 100);
            }
        }

        // Captain tiers
        if ($programType === 'captain_chatter' && isset($program['tiers'])) {
            $lines[] = "\nNiveaux Captain :";
            foreach ($program['tiers'] as $tierName => $tier) {
                $bonus = $tier['bonus'] / 100;
                $lines[] = "- " . ucfirst($tierName) . " : {$tier['min_team_calls']} appels equipe → \${$bonus}/mois";
            }
        }

        // Common withdrawal info
        $common = $programs['common'] ?? [];
        if ($common) {
            $lines[] = "\nRetraits :";
            $lines[] = "- Minimum : \$" . (($common['withdrawal_minimum'] ?? 3000) / 100);
            $lines[] = "- Frais : \$" . (($common['withdrawal_fee'] ?? 300) / 100) . " fixe par transaction";
            $lines[] = "- Delai : {$common['hold_period_hours']}h de blocage apres gain";
        }

        return implode("\n", $lines);
    }

    // -----------------------------------------------------------------
    // PRIVATE BLOCK BUILDERS
    // -----------------------------------------------------------------

    private function getIdentityBlock(): string
    {
        $identity = $this->kb['identity'] ?? [];
        $whatIs = implode("\n- ", $identity['what_it_is'] ?? []);
        $whatIsNot = implode("\n- ", $identity['what_it_is_NOT'] ?? []);
        $legal = $identity['legal_entity'] ?? [];

        return <<<BLOCK
═══════════════════════════════════════════════════════════════
POSITIONNEMENT UNIVERSEL DE SOS-EXPAT.com (REGLE ABSOLUE)
═══════════════════════════════════════════════════════════════

SOS-Expat.com est un service de mise en relation telephonique qui connecte
en moins de 5 minutes TOUTE PERSONNE HORS DE SON PAYS avec UN PROFESSIONNEL
LOCAL, pour OBTENIR TOUT TYPE D'AIDE.

PUBLIC CIBLE — CERCLE COMPLET (ne JAMAIS se limiter a "expatries") :
- Expatries (installes a l'etranger, court/long terme)
- Voyageurs (tourisme, business trips)
- Vacanciers (sejours courts)
- Digital nomads (travail a distance international)
- Etudiants internationaux (echanges, Erasmus, diplomes a l'etranger)
- Investisseurs etrangers (immobilier, business international)
- Retraites a l'etranger
- Travailleurs detaches
- Entrepreneurs internationaux
- Toute personne en mobilite internationale, meme temporaire

TYPES D'AIDE FOURNIS — SPECTRE LARGE (ne JAMAIS se limiter au juridique) :
- Juridique (droit, litiges, contrats, visa, residence, nationalite)
- Administrative (demarches, dossiers, formulaires, documents officiels)
- Pratique (installation, logement, ecoles, transport, demenagement)
- Medicale / Sante (orientation, hopitaux, assurance, urgences)
- Financiere (fiscalite, banque, transferts, investissements)
- Urgence (accidents, vols, arrestations, perte de papiers)
- Conseil et orientation (aide a la decision, second avis)
- Culturelle et linguistique (interpretariat, codes locaux)
- Toute situation ou un expert local apporte de la valeur

PROFESSIONNELS PARTENAIRES :
- Avocats locaux partenaires (49€/55\$ — 20 min)
- Experts locaux partenaires (19€/25\$ — 30 min) : consultants, expatries
  aidants, guides locaux, anciens fonctionnaires, professionnels bilingues

REGLES DE VOCABULAIRE A SUIVRE (IMPERATIF) :

❌ INTERDIT — ne JAMAIS ecrire :
- "pour les expatries" (sous-entend que les autres ne sont pas concernes)
- "assistance juridique" seule (limite le scope au droit)
- "aide legale" comme seul service
- "cabinet d'avocats" ou "firme juridique"
- "pour les Francais a l'etranger" (c'est multi-nationalites)
- "si vous etes expatrie" (c'est plus large)
- "votre ambassade francaise" (dire "votre ambassade" ou "votre consulat")
- Inventer des chiffres : "X avocats", "Y clients", "Z pays couverts en plus
  que 197", "note 4.8/5", "classe #1", "utilise par N personnes"

✅ OBLIGATOIRE — toujours utiliser :
- "SOS-Expat.com" (avec le .com et avec le tiret, toujours)
- "voyageurs, expatries, vacanciers, digital nomads, etudiants, investisseurs
  et toute personne hors de son pays"
- "aide", "conseil", "assistance", "orientation", "expertise"
- "un avocat ou un expert local"
- "pour toute situation a l'etranger" / "pour toute question"
- "professionnel local partenaire"
- "197 pays, 9 langues, 24h/24" (vrais chiffres uniquement)
- "en moins de 5 minutes" (vrai SLA)
- "49€/55\$ pour un appel avocat (20 min)" (vrai prix)
- "19€/25\$ pour un appel expert (30 min)" (vrai prix)

═══════════════════════════════════════════════════════════════

QUI EST SOS-EXPAT.com :
- {$whatIs}

CE QUE SOS-EXPAT.com N'EST PAS :
- {$whatIsNot}

ENTITE LEGALE : {$legal['name']} — {$legal['country']} ({$legal['type']})
BLOCK;
    }

    private function getServicesBlock(): string
    {
        $lawyer = $this->kb['services']['lawyer'] ?? [];
        $expat = $this->kb['services']['expat'] ?? [];
        $note = $this->kb['services']['note_important'] ?? '';

        return <<<BLOCK
SERVICES ET TARIFS EXACTS (le CLIENT paie, SOS-Expat.com prend des frais de mise en relation) :
- AVOCAT PARTENAIRE : {$lawyer['price_eur']}EUR / {$lawyer['price_usd']}USD — {$lawyer['duration_minutes']} minutes
  L'avocat partenaire recoit : {$lawyer['provider_payout_eur']}EUR / {$lawyer['provider_payout_usd']}USD | Frais SOS-Expat : {$lawyer['platform_fee_eur']}EUR / {$lawyer['platform_fee_usd']}USD
  {$lawyer['description_fr']}
- EXPERT LOCAL PARTENAIRE : {$expat['price_eur']}EUR / {$expat['price_usd']}USD — {$expat['duration_minutes']} minutes
  L'expert partenaire recoit : {$expat['provider_payout_eur']}EUR / {$expat['provider_payout_usd']}USD | Frais SOS-Expat : {$expat['platform_fee_eur']}EUR / {$expat['platform_fee_usd']}USD
  {$expat['description_fr']}
IMPORTANT : Les avocats et experts sont des PARTENAIRES INDEPENDANTS, PAS des employes. Ils s'inscrivent librement sur la plateforme. SOS-Expat.com ne les embauche PAS et ne les paie PAS — c'est le client qui paie.
NOTE : {$note}
BLOCK;
    }

    private function getSubscriptionsBlock(): string
    {
        $subs = $this->kb['subscriptions'] ?? [];
        $trial = $subs['trial'] ?? [];
        $lawyerPlans = $subs['lawyer_plans'] ?? [];
        $expatPlans = $subs['expat_plans'] ?? [];

        $lines = ["ABONNEMENTS & ASSISTANT IA :"];
        $lines[] = "- Essai gratuit : {$trial['ai_calls']} appels IA a vie (sans limite de temps)";

        $lines[] = "- Plans Avocat (mensuel) :";
        foreach ($lawyerPlans as $name => $plan) {
            $calls = $plan['ai_calls'] == -1 ? 'illimite (fair use ' . ($plan['fair_use_limit'] ?? 500) . ')' : $plan['ai_calls'];
            $lines[] = "  {$name} : {$plan['eur']}EUR / {$plan['usd']}USD — {$calls} appels IA/mois";
        }

        $lines[] = "- Plans Expert (mensuel) :";
        foreach ($expatPlans as $name => $plan) {
            $calls = $plan['ai_calls'] == -1 ? 'illimite (fair use ' . ($plan['fair_use_limit'] ?? 500) . ')' : $plan['ai_calls'];
            $lines[] = "  {$name} : {$plan['eur']}EUR / {$plan['usd']}USD — {$calls} appels IA/mois";
        }

        $annualLabel = $subs['annual_discount_label']
            ?? (isset($subs['annual_discount']) ? (int) round($subs['annual_discount'] * 100) . '%' : '20%');
        $lines[] = "- Reduction annuelle : {$annualLabel}";

        return implode("\n", $lines);
    }

    private function getCoverageBlock(): string
    {
        $coverage = $this->kb['coverage'] ?? [];
        $langs = implode(', ', array_map(
            fn($code, $name) => "{$name} ({$code})",
            array_keys($coverage['language_names'] ?? []),
            array_values($coverage['language_names'] ?? [])
        ));

        return <<<BLOCK
COUVERTURE :
- {$coverage['countries']} pays
- {$coverage['availability']}
- Mise en relation en {$coverage['response_time']}
- Langues : {$langs}
- {$coverage['tts_languages']}
BLOCK;
    }

    private function getHowItWorksBlock(): string
    {
        $hw = $this->kb['how_it_works'] ?? [];
        $steps = [];
        for ($i = 1; $i <= 7; $i++) {
            $key = "step_{$i}";
            if (isset($hw[$key])) {
                $steps[] = "{$i}. {$hw[$key]}";
            }
        }

        return "COMMENT CA MARCHE :\n" . implode("\n", $steps);
    }

    private function getProgramsBlock(string $contentType): string
    {
        // Map content type -> specific affiliate program key for targeted prompts.
        // Content types not mapped (null or absent) get the generic overview below.
        $programTypes = [
            'chatters' => 'chatter',
            'influenceurs' => 'influencer',
            'admin_groupes' => 'group_admin',
        ];

        $mappedType = $this->mapContentType($contentType);

        if (!empty($programTypes[$mappedType])) {
            return $this->getProgramPrompt($programTypes[$mappedType]);
        }

        // Generic overview for all other content types
        $programs = $this->kb['programs'] ?? [];
        $common = $programs['common'] ?? [];

        return <<<BLOCK
6 PROGRAMMES AFFILIES SOS-EXPAT :
- Chatter : \$5/appel avocat, \$3/appel expert, affiliation 2 niveaux (\$1 N1, \$0.50 N2), milestones \$15→\$4000
- Captain Chatter : 5 niveaux Bronze→Diamant (\$25→\$400/mois) + milestones
- Influenceur : \$5/\$3 par appel + \$5 reduction clients + milestones + Top 3 mensuel cash \$200/\$100/\$50
- Blogueur : \$5/\$3 par appel via widget + affiliation prestataires + milestones
- Admin Groupe : \$5/\$3 par appel + affiliation 2 niveaux + milestones + \$5 reduction clients
- Partenaire B2B : 15% du revenu des appels generes
- Retrait minimum : \${$this->cents($common['withdrawal_minimum'] ?? 3000)} | Frais : \${$this->cents($common['withdrawal_fee'] ?? 300)} fixe
BLOCK;
    }

    private function getPaymentBlock(): string
    {
        $p = $this->kb['payment'] ?? [];
        $mm = $p['flutterwave']['providers'] ?? [];

        $mmList = [];
        foreach ($mm as $provider => $countries) {
            $mmList[] = "  {$provider} : {$countries}";
        }
        $mmStr = implode("\n", $mmList);

        return <<<BLOCK
MOYENS DE PAIEMENT :
- Stripe : carte bancaire, {$p['stripe']['countries']} — {$p['stripe']['fees']}
- PayPal : {$p['paypal']['countries']}
- Wise : virement international, {$p['wise']['countries']}
- Flutterwave Mobile Money ({$p['flutterwave']['countries']}) :
{$mmStr}
BLOCK;
    }

    private function getLegalBlock(): string
    {
        $legal = $this->kb['legal'] ?? [];
        $disclaimers = implode("\n- ", $legal['disclaimers'] ?? []);
        $refund = $legal['refund_policy'] ?? [];

        return <<<BLOCK
INFORMATIONS LEGALES :
- Entite : WorldExpat OU, Estonie
- CGU clients v{$legal['terms_versions']['terms_clients']} | CGU affilies v{$legal['terms_versions']['terms_affiliate']}
- Politique de confidentialite v{$legal['terms_versions']['privacy_policy']}
- RGPD Art.17 : suppression definitive sur demande
- DSA : {$legal['dsa']}

DISCLAIMERS OBLIGATOIRES :
- {$disclaimers}

REMBOURSEMENT :
- Avant connexion : {$refund['before_connection']}
- Apres connexion : {$refund['after_connection']}
- Prestataire annule : {$refund['provider_cancels']}
BLOCK;
    }

    private function getBrandVoiceBlock(): string
    {
        $voice = $this->kb['brand_voice'] ?? [];
        $neverSay = implode("\n- ", $voice['never_say'] ?? []);
        $alwaysSay = implode("\n- ", $voice['always_say'] ?? []);

        return <<<BLOCK
VOIX DE MARQUE :
Ton : {$voice['tone']}

NE JAMAIS :
- {$neverSay}

TOUJOURS :
- {$alwaysSay}
BLOCK;
    }

    private function getToolsBlock(): string
    {
        $tools = $this->kb['tools'] ?? [];
        $categories = $tools['categories'] ?? [];

        $lines = ["{$tools['total']} OUTILS INTERACTIFS GRATUITS (sos-expat.com) :"];

        $catNames = [
            'calculate' => 'Calculateurs',
            'compare' => 'Comparateurs',
            'generate' => 'Generateurs',
            'emergency' => 'Urgences & Securite',
        ];

        foreach ($categories as $cat => $toolList) {
            $name = $catNames[$cat] ?? $cat;
            $count = count($toolList);
            $toolNames = implode(', ', array_values($toolList));
            $lines[] = "- {$name} ({$count}) : {$toolNames}";
        }

        return implode("\n", $lines);
    }

    private function getAntiCannibalizationBlock(): string
    {
        $rules = $this->kb['anti_cannibalization'] ?? [];
        if (empty($rules)) {
            return '';
        }

        $lines = ["REGLES ANTI-CANNIBALISATION (STRICTES) :"];
        foreach ($rules as $rule) {
            $lines[] = "- {$rule}";
        }

        return implode("\n", $lines);
    }

    private function getHelpfulContentBlock(): string
    {
        $rules = $this->kb['helpful_content_rules'] ?? [];
        if (empty($rules)) {
            return '';
        }

        $lines = ["GOOGLE HELPFUL CONTENT (OBLIGATOIRE 2026) :"];
        foreach ($rules as $key => $rule) {
            $lines[] = "- {$rule}";
        }

        return implode("\n", $lines);
    }

    private function getDisclaimerBlock(?string $searchIntent, string $contentType): string
    {
        $disclaimers = $this->kb['disclaimers_by_intent'] ?? [];
        $parts = [];

        if ($searchIntent && isset($disclaimers[$searchIntent])) {
            $parts[] = $disclaimers[$searchIntent];
        }
        // Legal disclaimer for legal content types
        if (in_array($contentType, ['tutorial', 'guide', 'guide_city', 'pillar']) && isset($disclaimers['legal'])) {
            $parts[] = $disclaimers['legal'];
        }

        if (empty($parts)) {
            return '';
        }

        return "\nDISCLAIMER OBLIGATOIRE (a inclure en fin d'article dans un <div class=\"disclaimer\">) :\n" . implode("\n", $parts) . "\n";
    }

    private function getContentRule(string $contentType): string
    {
        $rules = $this->kb['content_rules'] ?? [];
        $key = $this->mapContentType($contentType);

        return $rules[$key] ?? "Article informatif pour expatries et voyageurs. Ton professionnel, donnees chiffrees, CTA naturel vers SOS-Expat.";
    }

    private function getSeoRulesBlock(): string
    {
        $seo = $this->kb['seo_rules'] ?? [];

        $eeat = $seo['eeat_signals'] ?? [];
        $eeatStr = is_array($eeat)
            ? implode("\n  ", array_map(fn($k, $v) => strtoupper($k) . ": {$v}", array_keys($eeat), array_values($eeat)))
            : $eeat;

        return <<<BLOCK
REGLES SEO :
- CTA : {$seo['cta_max']}
- Format CTA : "{$seo['cta_format']}"
- Maillage : {$seo['internal_links']}
- Featured snippet : {$seo['featured_snippet']}
- Mots-cles : {$seo['no_keyword_stuffing']}
- Annee : {$seo['year_mention']}
- Titre : {$seo['title_length']}
- Structure : {$seo['h2_structure']}
- E-E-A-T :
  {$eeatStr}
BLOCK;
    }

    private function getAeoBlock(): string
    {
        $aeo = $this->kb['aeo_rules'] ?? [];
        if (empty($aeo)) {
            return '';
        }

        $lines = ["REGLES AEO (Answer Engine Optimization — SearchGPT, Perplexity, Claude) :"];
        foreach ($aeo as $rule) {
            $lines[] = "- {$rule}";
        }

        return implode("\n", $lines);
    }

    private function getSchemaBlock(): string
    {
        $schema = $this->kb['schema_rules'] ?? [];
        if (empty($schema)) {
            return '';
        }

        $lines = ["SCHEMA MARKUP OBLIGATOIRE :"];
        foreach ($schema as $rule) {
            $lines[] = "- {$rule}";
        }

        return implode("\n", $lines);
    }

    private function getHtmlTemplatesBlock(): string
    {
        return config('html-templates.prompt_instructions', '');
    }

    private function getIntentBlock(string $intent): string
    {
        $intents = $this->kb['search_intent'] ?? [];
        $intentData = $intents[$intent] ?? null;

        if (!$intentData || !isset($intentData['format']) || ($intentData['format']['generate'] ?? true) === false) {
            return '';
        }

        $format = $intentData['format'];
        $lines = [
            "\n=== INTENTION DE RECHERCHE : {$intentData['name']} ===",
            "Description : {$intentData['description']}",
            "Structure : {$format['structure']}",
            "Featured snippet : {$format['featured_snippet']}",
            "Longueur : {$format['longueur']}",
            "Ton : {$format['tone']}",
            "CTA : {$format['cta']}",
            "FAQ : {$format['faq_count']} questions",
            "",
            "ELEMENTS HTML OBLIGATOIRES :",
        ];

        foreach ($format['elements_html'] as $element) {
            $lines[] = "- {$element}";
        }

        // Long-tail rules if applicable
        $longTail = $this->kb['long_tail_rules'] ?? [];
        if (!empty($longTail['regles_generation'])) {
            $lines[] = "";
            $lines[] = "REGLES LONGUE TRAINE (requetes specifiques) :";
            foreach ($longTail['regles_generation'] as $rule) {
                $lines[] = "- {$rule}";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Map content type aliases to KB keys.
     */
    private function mapContentType(string $contentType): string
    {
        $typeMap = [
            'qa' => 'qr',
            'guide' => 'fiches_pays',
            'guide_city' => 'fiches_villes',
            'article' => 'art_mots_cles',
            'outreach' => 'chatters',
            'comparative' => 'comparatifs',
            'news' => 'news',
            'affiliation' => 'affiliation',
        ];

        return $typeMap[$contentType] ?? $contentType;
    }

    /**
     * Convert cents to dollars string.
     * Whole-dollar amounts display without decimals ($5), sub-dollar
     * amounts keep two decimals ($0.50) so affiliate copy never rounds
     * $0.50 to $1 or $3.50 to $4.
     */
    private function cents(int $cents): string
    {
        $dollars = $cents / 100;
        $decimals = ($cents % 100 === 0) ? 0 : 2;

        return number_format($dollars, $decimals);
    }
}
