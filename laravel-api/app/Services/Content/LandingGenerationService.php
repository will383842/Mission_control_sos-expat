<?php

namespace App\Services\Content;

use App\Models\LandingCampaign;
use App\Models\LandingPage;
use App\Models\LandingProblem;
use App\Services\AI\ClaudeService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LandingGenerationService
{
    // ============================================================
    // Templates definitions (constants, versioned in code)
    // ============================================================

    private const TEMPLATES = [
        'clients' => [
            'urgent' => [
                'label'       => 'Urgence',
                'intent'      => 'urgence',
                'cta_primary' => 'Être rappelé immédiatement',
                'tone'        => 'Urgente, rassurante, directe. Phrases courtes. Pas de jargon.',
                'sections'    => ['hero', 'trust_signals', 'local_info', 'faq', 'cta'],
            ],
            'seo' => [
                'label'       => 'SEO / Information',
                'intent'      => 'information',
                'cta_primary' => 'Parler à un expert',
                'tone'        => 'Pédagogique, complète, bien structurée. Répond aux questions Google.',
                'sections'    => ['hero', 'guide_steps', 'local_info', 'faq', 'trust_signals', 'cta'],
            ],
            'trust' => [
                'label'       => 'Rassurance',
                'intent'      => 'rassurance',
                'cta_primary' => 'Parler à un expert maintenant',
                'tone'        => 'Empathique, sécurisante, humaine. Lève les doutes.',
                'sections'    => ['hero', 'why_us', 'testimonial_proof', 'faq', 'cta'],
            ],
        ],
        'lawyers' => [
            'general' => [
                'label'    => 'Général — Nouveaux clients',
                'angle'    => 'Recevez des clients sans prospection',
                'tone'     => 'Professionnelle, directe, orientée bénéfices concrets.',
                'sections' => ['hero', 'earnings', 'freedom', 'process', 'faq', 'cta'],
            ],
            'urgent' => [
                'label'    => 'Urgence — Clients immédiats',
                'angle'    => 'Recevez des appels clients en urgence maintenant',
                'tone'     => 'Dynamique, immédiate. Appels temps-réel.',
                'sections' => ['hero', 'earnings', 'process', 'cta'],
            ],
            'freedom' => [
                'label'    => 'Liberté — Travail flexible',
                'angle'    => 'Travaillez quand vous voulez, sans contraintes',
                'tone'     => 'Légère, libérée. Pas d\'obligation. Liberté totale.',
                'sections' => ['hero', 'freedom', 'earnings', 'process', 'faq', 'cta'],
            ],
            'income' => [
                'label'    => 'Revenu — Complément rapide',
                'angle'    => 'Générez un revenu complémentaire immédiatement',
                'tone'     => 'Pragmatique, orientée ROI. Chiffres concrets.',
                'sections' => ['hero', 'earnings', 'process', 'trust_signals', 'cta'],
            ],
            'premium' => [
                'label'    => 'Premium — Clients qualifiés',
                'angle'    => 'Accédez à des clients internationaux qualifiés',
                'tone'     => 'Prestige, sélectivité, clientèle internationale.',
                'sections' => ['hero', 'client_quality', 'earnings', 'process', 'faq', 'cta'],
            ],
        ],
        'helpers' => [
            'recruitment' => [
                'label'    => 'Recrutement SEO',
                'angle'    => 'Devenez expatrié aidant dans {country}',
                'tone'     => 'Communautaire, solidaire, opportunité concrète.',
                'sections' => ['hero', 'what_you_do', 'earnings', 'process', 'faq', 'cta'],
            ],
            'opportunity' => [
                'label'    => 'Opportunité — FOMO',
                'angle'    => 'Rejoignez la communauté d\'entraide expatriés à {country}',
                'tone'     => 'Enthousiaste, FOMO, momentum communautaire.',
                'sections' => ['hero', 'community_proof', 'earnings', 'process', 'cta'],
            ],
            'reassurance' => [
                'label'    => 'Rassurance — Lever les freins',
                'angle'    => 'Aidez d\'autres expatriés à votre rythme',
                'tone'     => 'Douce, sans pression. Flexible, zéro risque.',
                'sections' => ['hero', 'no_pressure', 'earnings', 'faq', 'cta'],
            ],
        ],
        'matching' => [
            'expert' => [
                'label'    => 'Expert généraliste',
                'angle'    => 'Parler à un expert à {country}',
                'tone'     => 'Conversion directe. CTA immédiat. Court et percutant.',
                'sections' => ['hero', 'trust_signals', 'cta'],
            ],
            'lawyer' => [
                'label'    => 'Avocat',
                'angle'    => 'Parler à un avocat à {country}',
                'tone'     => 'Sérieux, compétent, disponible rapidement.',
                'sections' => ['hero', 'lawyer_advantages', 'trust_signals', 'cta'],
            ],
            'helper' => [
                'label'    => 'Expatrié aidant',
                'angle'    => 'Contacter un expatrié aidant à {country}',
                'tone'     => 'Chaleureux, humain, communautaire.',
                'sections' => ['hero', 'helper_advantages', 'trust_signals', 'cta'],
            ],
        ],
    ];

    // Slugs pays en français pour les URLs (ASCII-only, Str::slug fallback pour les autres)
    private const COUNTRY_SLUGS_FR = [
        // Asie du Sud-Est
        'TH' => 'thailande', 'VN' => 'vietnam', 'SG' => 'singapour',
        'MY' => 'malaisie', 'PH' => 'philippines', 'JP' => 'japon',
        'ID' => 'indonesie', 'KH' => 'cambodge', 'LA' => 'laos',
        'MM' => 'myanmar', 'TL' => 'timor-oriental', 'BN' => 'brunei',
        // Océanie
        'AU' => 'australie', 'NZ' => 'nouvelle-zelande', 'FJ' => 'fidji',
        'PG' => 'papouasie-nouvelle-guinee', 'VU' => 'vanuatu',
        // Amérique latine
        'MX' => 'mexique', 'BR' => 'bresil', 'CR' => 'costa-rica',
        'CO' => 'colombie', 'AR' => 'argentine', 'CL' => 'chili',
        'PE' => 'perou', 'EC' => 'equateur', 'BO' => 'bolivie',
        'PY' => 'paraguay', 'UY' => 'uruguay', 'VE' => 'venezuela',
        'DO' => 'republique-dominicaine', 'GT' => 'guatemala',
        'HN' => 'honduras', 'SV' => 'el-salvador', 'NI' => 'nicaragua',
        'PA' => 'panama', 'CU' => 'cuba', 'HT' => 'haiti',
        'JM' => 'jamaique', 'TT' => 'trinite-et-tobago', 'BB' => 'barbade',
        // Caraïbes (petites îles — liste prioritaire ARCHITECTURE.md)
        'LC' => 'sainte-lucie', 'VC' => 'saint-vincent-et-les-grenadines',
        'GD' => 'grenade', 'AG' => 'antigua-et-barbuda', 'DM' => 'dominique',
        // Amérique du Nord
        'US' => 'etats-unis', 'CA' => 'canada',
        // Europe occidentale
        'FR' => 'france', 'ES' => 'espagne', 'PT' => 'portugal',
        'IT' => 'italie', 'DE' => 'allemagne', 'NL' => 'pays-bas',
        'BE' => 'belgique', 'CH' => 'suisse', 'GB' => 'royaume-uni',
        'GR' => 'grece', 'AT' => 'autriche', 'SE' => 'suede',
        'NO' => 'norvege', 'DK' => 'danemark', 'FI' => 'finlande',
        'IE' => 'irlande', 'LU' => 'luxembourg', 'IS' => 'islande',
        'MC' => 'monaco', 'LI' => 'liechtenstein', 'AD' => 'andorre',
        'SM' => 'saint-marin', 'MT' => 'malte', 'CY' => 'chypre',
        // Europe orientale
        'HR' => 'croatie', 'PL' => 'pologne', 'RO' => 'roumanie',
        'CZ' => 'republique-tcheque', 'SK' => 'slovaquie', 'HU' => 'hongrie',
        'BG' => 'bulgarie', 'SI' => 'slovenie', 'RS' => 'serbie',
        'BA' => 'bosnie-herzegovine', 'MK' => 'macedoine-du-nord',
        'AL' => 'albanie', 'ME' => 'montenegro', 'XK' => 'kosovo',
        'EE' => 'estonie', 'LV' => 'lettonie', 'LT' => 'lituanie',
        'UA' => 'ukraine', 'BY' => 'bielorussie', 'MD' => 'moldavie',
        'RU' => 'russie', 'GE' => 'georgie', 'AM' => 'armenie',
        'AZ' => 'azerbaidjan',
        // Moyen-Orient
        'AE' => 'emirats-arabes-unis', 'QA' => 'qatar', 'SA' => 'arabie-saoudite',
        'BH' => 'bahrein', 'KW' => 'koweit', 'OM' => 'oman',
        'IL' => 'israel', 'JO' => 'jordanie', 'LB' => 'liban',
        'TR' => 'turquie', 'IQ' => 'irak', 'IR' => 'iran',
        'SY' => 'syrie', 'YE' => 'yemen',
        // Afrique du Nord
        'MA' => 'maroc', 'TN' => 'tunisie', 'DZ' => 'algerie',
        'LY' => 'libye', 'EG' => 'egypte',
        // Afrique sub-saharienne
        'SN' => 'senegal', 'CI' => 'cote-divoire', 'CM' => 'cameroun',
        'MG' => 'madagascar', 'MU' => 'ile-maurice', 'RE' => 'reunion',
        'ZA' => 'afrique-du-sud', 'KE' => 'kenya', 'NG' => 'nigeria',
        'GH' => 'ghana', 'ET' => 'ethiopie', 'TZ' => 'tanzanie',
        'UG' => 'ouganda', 'RW' => 'rwanda', 'BJ' => 'benin',
        'BF' => 'burkina-faso', 'ML' => 'mali', 'NE' => 'niger',
        'CD' => 'congo-rdc', 'CG' => 'congo', 'GA' => 'gabon',
        'GN' => 'guinee', 'MZ' => 'mozambique', 'AO' => 'angola',
        'ZM' => 'zambie', 'ZW' => 'zimbabwe', 'NA' => 'namibie',
        'BW' => 'botswana', 'SC' => 'seychelles', 'CV' => 'cap-vert',
        'MR' => 'mauritanie', 'DJ' => 'djibouti', 'SO' => 'somalie',
        'SD' => 'soudan', 'SS' => 'soudan-du-sud',
        // Asie centrale
        'KZ' => 'kazakhstan', 'UZ' => 'ouzbekistan', 'TM' => 'turkmenistan',
        'KG' => 'kirghizistan', 'TJ' => 'tadjikistan',
        // Asie du Sud
        'IN' => 'inde', 'LK' => 'sri-lanka', 'NP' => 'nepal',
        'PK' => 'pakistan', 'BD' => 'bangladesh', 'MV' => 'maldives',
        // Asie de l'Est
        'CN' => 'chine', 'HK' => 'hong-kong', 'TW' => 'taiwan',
        'KR' => 'coree-du-sud', 'MN' => 'mongolie',
    ];

    public function __construct(
        private ClaudeService $claude,
    ) {}

    // ============================================================
    // Point d'entrée public
    // ============================================================

    /**
     * Génère une landing page complète selon les params.
     *
     * @param array{
     *   audience_type: string,
     *   template_id: string,
     *   country_code: string,
     *   language: string,
     *   problem_slug?: string,
     *   created_by?: int|null,
     * } $params
     */
    public function generate(array $params): LandingPage
    {
        $audienceType = $params['audience_type'];
        $templateId   = $params['template_id'];
        $countryCode  = $params['country_code'];
        $language     = $params['language'] ?? 'fr';

        return match ($audienceType) {
            'clients'  => $this->generateClientLanding(
                LandingProblem::where('slug', $params['problem_slug'] ?? '')->firstOrFail(),
                $templateId,
                $countryCode,
                $language,
                $params['created_by'] ?? null,
            ),
            'lawyers'  => $this->generateLawyerLanding($templateId, $countryCode, $language, $params['created_by'] ?? null),
            'helpers'  => $this->generateHelperLanding($templateId, $countryCode, $language, $params['created_by'] ?? null),
            'matching' => $this->generateMatchingLanding($templateId, $countryCode, $language, $params['created_by'] ?? null),
            default    => throw new \InvalidArgumentException("audience_type invalide: {$audienceType}"),
        };
    }

    // ============================================================
    // Génération par audience
    // ============================================================

    private function generateClientLanding(
        LandingProblem $problem,
        string $templateId,
        string $countryCode,
        string $language,
        ?int $createdBy,
    ): LandingPage {
        $template    = self::TEMPLATES['clients'][$templateId] ?? self::TEMPLATES['clients']['seo'];
        $countryName = $this->getCountryName($countryCode, $language);
        $countrySlug = $this->getCountrySlug($countryCode);

        $systemPrompt = $this->buildSystemPrompt('clients', $countryCode, $countryName, $language);

        $userPrompt = <<<PROMPT
        Génère une landing page SOS-Expat pour :
        - Problème : {$problem->title}
        - Angle : {$problem->lp_angle}
        - Pays : {$countryName} ({$countryCode})
        - Template : {$template['label']} — Ton : {$template['tone']}
        - Langue de rédaction : {$language}
        - Intent : {$template['intent']}
        - CTA principal : "{$template['cta_primary']}"
        - FAQ seed : {$problem->faq_seed}

        Sections à inclure dans l'ordre : {$this->formatSections($template['sections'])}.

        Réponds UNIQUEMENT en JSON valide avec cette structure :
        {
          "title": "...",
          "sections": [
            {"type": "hero", "content": {"h1": "...", "subtitle": "...", "cta_text": "..."}},
            {"type": "trust_signals", "content": {"items": [{"icon": "⚡", "text": "..."}]}},
            {"type": "guide_steps", "content": {"steps": [{"num": 1, "title": "...", "text": "..."}]}},
            {"type": "local_info", "content": {"embassy": "...", "emergency_number": "...", "tip": "..."}},
            {"type": "faq", "content": {"items": [{"q": "...", "a": "..."}]}},
            {"type": "cta", "content": {"headline": "...", "button": "...", "subtext": "..."}}
          ],
          "meta_title": "...(max 60 chars)",
          "meta_description": "...(max 155 chars)",
          "cta_links": [
            {"label": "...", "url": "/", "style": "primary", "position": "hero"},
            {"label": "...", "url": "/", "style": "secondary", "position": "footer"}
          ]
        }
        PROMPT;

        $response = $this->claude->complete($systemPrompt, $userPrompt, [
            'model'      => 'claude-sonnet-4-6',
            'max_tokens' => 4000,
        ]);

        $parsed = $this->parseResponse($response);
        $slug   = $this->buildSlug('clients', $language, $countrySlug, $problem->slug, $templateId);

        return $this->saveLandingPage([
            'audience_type'      => 'clients',
            'template_id'        => $templateId,
            'problem_id'         => $problem->slug,
            'country_code'       => $countryCode,
            'language'           => $language,
            'country'            => $countryName,
            'generation_source'  => 'ai_generated',
            'generation_params'  => [
                'problem_slug'   => $problem->slug,
                'template_id'    => $templateId,
                'audience_type'  => 'clients',
                'language'       => $language,
                'country_code'   => $countryCode,
                'urgency_score'  => $problem->urgency_score,
                'business_value' => $problem->business_value,
                'lp_angle'       => $problem->lp_angle,
            ],
            'created_by'         => $createdBy,
        ], $parsed, $slug);
    }

    private function generateLawyerLanding(
        string $templateId,
        string $countryCode,
        string $language,
        ?int $createdBy,
    ): LandingPage {
        $template    = self::TEMPLATES['lawyers'][$templateId] ?? self::TEMPLATES['lawyers']['general'];
        $countryName = $this->getCountryName($countryCode, $language);
        $countrySlug = $this->getCountrySlug($countryCode);

        $systemPrompt = $this->buildSystemPrompt('lawyers', $countryCode, $countryName, $language);

        $angle = str_replace('{country}', $countryName, $template['angle']);

        $userPrompt = <<<PROMPT
        Génère une landing page de recrutement d'avocats partenaires pour SOS-Expat.
        - Pays : {$countryName} ({$countryCode})
        - Angle : {$angle}
        - Template : {$template['label']} — Ton : {$template['tone']}
        - Langue de rédaction : {$language}
        - Rémunération : 30€ par consultation de 20 minutes. Paiement sous 24h.
        - Liberté totale : l'avocat choisit ses horaires.
        - Processus : Inscription → Activation profil → Réception appels → Paiement.

        Réponds UNIQUEMENT en JSON valide avec cette structure :
        {
          "title": "...",
          "sections": [
            {"type": "hero", "content": {"h1": "...", "subtitle": "...", "cta_text": "..."}},
            {"type": "earnings", "content": {"headline": "...", "amount": "30€", "detail": "...", "badges": ["..."]}},
            {"type": "freedom", "content": {"headline": "...", "items": [{"icon": "✓", "text": "..."}]}},
            {"type": "process", "content": {"steps": [{"num": 1, "label": "..."}]}},
            {"type": "faq", "content": {"items": [{"q": "...", "a": "..."}]}},
            {"type": "cta", "content": {"headline": "...", "button": "...", "subtext": "..."}}
          ],
          "meta_title": "...(max 60 chars)",
          "meta_description": "...(max 155 chars)",
          "cta_links": [
            {"label": "S'inscrire maintenant", "url": "/inscription-avocat", "style": "primary", "position": "hero"}
          ]
        }
        PROMPT;

        $response = $this->claude->complete($systemPrompt, $userPrompt, [
            'model'      => 'claude-sonnet-4-6',
            'max_tokens' => 3000,
        ]);

        $parsed = $this->parseResponse($response);
        $slug   = $this->buildSlug('lawyers', $language, $countrySlug, null, $templateId);

        return $this->saveLandingPage([
            'audience_type'     => 'lawyers',
            'template_id'       => $templateId,
            'country_code'      => $countryCode,
            'language'          => $language,
            'country'           => $countryName,
            'generation_source' => 'ai_generated',
            'generation_params' => [
                'template_id'  => $templateId,
                'audience_type'=> 'lawyers',
                'language'     => $language,
                'country_code' => $countryCode,
                'angle'        => $angle,
            ],
            'created_by'        => $createdBy,
        ], $parsed, $slug);
    }

    private function generateHelperLanding(
        string $templateId,
        string $countryCode,
        string $language,
        ?int $createdBy,
    ): LandingPage {
        $template    = self::TEMPLATES['helpers'][$templateId] ?? self::TEMPLATES['helpers']['recruitment'];
        $countryName = $this->getCountryName($countryCode, $language);
        $countrySlug = $this->getCountrySlug($countryCode);

        $systemPrompt = $this->buildSystemPrompt('helpers', $countryCode, $countryName, $language);

        $angle = str_replace('{country}', $countryName, $template['angle']);

        $userPrompt = <<<PROMPT
        Génère une landing page de recrutement d'expatriés aidants pour SOS-Expat.
        - Pays : {$countryName} ({$countryCode})
        - Angle : {$angle}
        - Template : {$template['label']} — Ton : {$template['tone']}
        - Langue de rédaction : {$language}
        - Rémunération : 10€ par appel d'assistance de 20 minutes.
        - Profil cible : expatrié déjà installé dans le pays, connaissant les démarches locales.
        - Aide fournie : accompagnement pratique (logement, admin, intégration), pas juridique.

        Réponds UNIQUEMENT en JSON valide avec cette structure :
        {
          "title": "...",
          "sections": [
            {"type": "hero", "content": {"h1": "...", "subtitle": "...", "cta_text": "..."}},
            {"type": "what_you_do", "content": {"headline": "...", "items": [{"icon": "✓", "text": "..."}]}},
            {"type": "earnings", "content": {"headline": "...", "amount": "10€", "detail": "...", "badges": ["..."]}},
            {"type": "process", "content": {"steps": [{"num": 1, "label": "..."}]}},
            {"type": "faq", "content": {"items": [{"q": "...", "a": "..."}]}},
            {"type": "cta", "content": {"headline": "...", "button": "...", "subtext": "..."}}
          ],
          "meta_title": "...(max 60 chars)",
          "meta_description": "...(max 155 chars)",
          "cta_links": [
            {"label": "Devenir expatrié aidant", "url": "/inscription-helper", "style": "primary", "position": "hero"}
          ]
        }
        PROMPT;

        $response = $this->claude->complete($systemPrompt, $userPrompt, [
            'model'      => 'claude-sonnet-4-6',
            'max_tokens' => 3000,
        ]);

        $parsed = $this->parseResponse($response);
        $slug   = $this->buildSlug('helpers', $language, $countrySlug, null, $templateId);

        return $this->saveLandingPage([
            'audience_type'     => 'helpers',
            'template_id'       => $templateId,
            'country_code'      => $countryCode,
            'language'          => $language,
            'country'           => $countryName,
            'generation_source' => 'ai_generated',
            'generation_params' => [
                'template_id'  => $templateId,
                'audience_type'=> 'helpers',
                'language'     => $language,
                'country_code' => $countryCode,
            ],
            'created_by'        => $createdBy,
        ], $parsed, $slug);
    }

    private function generateMatchingLanding(
        string $templateId,
        string $countryCode,
        string $language,
        ?int $createdBy,
    ): LandingPage {
        $template    = self::TEMPLATES['matching'][$templateId] ?? self::TEMPLATES['matching']['expert'];
        $countryName = $this->getCountryName($countryCode, $language);
        $countrySlug = $this->getCountrySlug($countryCode);

        $systemPrompt = $this->buildSystemPrompt('matching', $countryCode, $countryName, $language);

        $angle = str_replace('{country}', $countryName, $template['angle']);

        $userPrompt = <<<PROMPT
        Génère une landing page de conversion directe pour SOS-Expat.
        - Type : {$template['label']}
        - Pays : {$countryName} ({$countryCode})
        - Angle : {$angle}
        - Ton : {$template['tone']}
        - Langue de rédaction : {$language}
        - Objectif : Conversion maximale. Page courte et percutante.
        - Disponibilité : 24h/24, réponse en moins de 5 minutes.
        - Prix : fixe et transparent.

        Réponds UNIQUEMENT en JSON valide avec cette structure :
        {
          "title": "...",
          "sections": [
            {"type": "hero", "content": {"h1": "...", "subtitle": "...", "cta_text": "..."}},
            {"type": "trust_signals", "content": {"items": [{"icon": "✓", "text": "..."}]}},
            {"type": "cta", "content": {"headline": "...", "button": "...", "subtext": "..."}}
          ],
          "meta_title": "...(max 60 chars)",
          "meta_description": "...(max 155 chars)",
          "cta_links": [
            {"label": "Parler à un expert maintenant", "url": "/", "style": "primary", "position": "hero"}
          ]
        }
        PROMPT;

        $response = $this->claude->complete($systemPrompt, $userPrompt, [
            'model'      => 'claude-sonnet-4-6',
            'max_tokens' => 2000,
        ]);

        $parsed = $this->parseResponse($response);
        $slug   = $this->buildSlug('matching', $language, $countrySlug, null, $templateId);

        return $this->saveLandingPage([
            'audience_type'     => 'matching',
            'template_id'       => $templateId,
            'country_code'      => $countryCode,
            'language'          => $language,
            'country'           => $countryName,
            'generation_source' => 'ai_generated',
            'generation_params' => [
                'template_id'  => $templateId,
                'audience_type'=> 'matching',
                'language'     => $language,
                'country_code' => $countryCode,
            ],
            'created_by'        => $createdBy,
        ], $parsed, $slug);
    }

    // ============================================================
    // Helpers privés
    // ============================================================

    private function buildSystemPrompt(string $audienceType, string $countryCode, string $countryName, string $language): string
    {
        $langInstruction = match ($language) {
            'fr'    => 'Rédige en français. Tutoiement interdit. Vouvoiement.',
            'en'    => 'Write in English. Professional tone.',
            'es'    => 'Escribe en español. Tono profesional.',
            'de'    => 'Schreibe auf Deutsch. Professioneller Ton.',
            'pt'    => 'Escreva em português. Tom profissional.',
            'ar'    => 'اكتب باللغة العربية. نبرة مهنية.',
            'hi'    => 'हिंदी में लिखें। पेशेवर स्वर।',
            'zh'    => '用中文写作。专业语气。',
            'ru'    => 'Пишите на русском. Профессиональный тон.',
            default => 'Write professionally.',
        };

        $audienceContext = match ($audienceType) {
            'clients'  => "Tu génères du contenu pour des expatriés/voyageurs en difficulté dans le pays {$countryName}. Tu travailles pour SOS-Expat, service de mise en relation rapide avec des experts (avocats, expatriés aidants). Prix fixe, disponible 24h/24.",
            'lawyers'  => "Tu génères du contenu pour recruter des avocats partenaires à {$countryName}. SOS-Expat leur apporte des clients sans prospection. 30€ par consultation de 20 min. Paiement sous 24h. Liberté totale des horaires.",
            'helpers'  => "Tu génères du contenu pour recruter des expatriés aidants à {$countryName}. Ce sont des expatriés déjà installés qui aident les nouveaux arrivants. 10€ par appel de 20 min. Aide pratique (pas juridique).",
            'matching' => "Tu génères une page de conversion directe pour connecter un utilisateur à un expert SOS-Expat dans le pays {$countryName}. Page courte, CTA fort, confiance maximale.",
            default    => "Tu génères du contenu pour SOS-Expat concernant le pays {$countryName}.",
        };

        return <<<SYSTEM
        Tu es un expert en rédaction de landing pages haute conversion pour SOS-Expat.

        CONTEXTE :
        {$audienceContext}

        RÈGLES ABSOLUES :
        - {$langInstruction}
        - Réponds UNIQUEMENT en JSON valide (pas de texte avant/après)
        - Contenu unique, utile, contextualisé pour {$countryName}
        - Pas de contenu générique copié-collé d'autres pays
        - Pas de promesses fausses ni de chiffres inventés
        - URLs dans cta_links : utiliser "/" comme placeholder (seront remplacées)
        - meta_title max 60 caractères
        - meta_description max 155 caractères
        - H1 unique, accrocheur, contient le pays et le sujet
        SYSTEM;
    }

    private function parseResponse(string $rawResponse): array
    {
        // Nettoyer les balises markdown si présentes
        $clean = preg_replace('/^```json\s*/m', '', $rawResponse);
        $clean = preg_replace('/^```\s*/m', '', $clean);
        $clean = trim($clean);

        $data = json_decode($clean, true);

        if (! is_array($data) || empty($data)) {
            Log::warning('LandingGenerationService: JSON parse failed or empty', [
                'raw' => substr($rawResponse, 0, 500),
            ]);
            throw new \RuntimeException('La réponse Claude n\'est pas un JSON valide ou est vide.');
        }

        // Valider les champs obligatoires
        if (empty($data['title'])) {
            throw new \RuntimeException('La réponse Claude ne contient pas de "title".');
        }
        if (empty($data['sections']) || ! is_array($data['sections'])) {
            throw new \RuntimeException('La réponse Claude ne contient pas de "sections" valides.');
        }

        return $data;
    }

    private function saveLandingPage(array $baseData, array $parsed, string $slug): LandingPage
    {
        // Déduplication : si la LP existe déjà, on ne la réécrit pas
        $existing = LandingPage::where('slug', $slug)->first();
        if ($existing) {
            return $existing;
        }

        $seoScore    = $this->calculateSeoScore($parsed);
        $hreflangMap = $this->buildHreflangMap($baseData);

        $landing = LandingPage::create(array_merge($baseData, [
            'title'            => $parsed['title'] ?? $slug,
            'slug'             => $slug,
            'meta_title'       => $parsed['meta_title'] ?? null,
            'meta_description' => $parsed['meta_description'] ?? null,
            'sections'         => $parsed['sections'] ?? [],
            'seo_score'        => $seoScore,
            'status'           => 'draft',
            'hreflang_map'     => $hreflangMap,
            'json_ld'          => $this->buildJsonLd($parsed, $baseData['audience_type'], $baseData['country'] ?? ''),
        ]));

        // CTAs
        if (! empty($parsed['cta_links'])) {
            foreach ($parsed['cta_links'] as $i => $cta) {
                $landing->ctaLinks()->create([
                    'url'        => $cta['url'] ?? '/',
                    'text'       => $cta['label'] ?? 'Contacter un expert',
                    'position'   => $cta['position'] ?? 'hero',
                    'style'      => $cta['style'] ?? 'primary',
                    'sort_order' => $i,
                ]);
            }
        }

        return $landing;
    }

    /**
     * Génère la hreflang_map pour les 9 langues supportées.
     * Structure: ["fr" => "/fr/aide/divorce/thaïlande", "en" => "/en/aide/divorce/thaïlande", ...]
     */
    private function buildHreflangMap(array $baseData): array
    {
        $supportedLanguages = ['fr', 'en', 'es', 'de', 'pt', 'ar', 'hi', 'zh', 'ru'];
        $audienceType = $baseData['audience_type'];
        $countryCode  = $baseData['country_code'] ?? '';
        $countrySlug  = $countryCode ? $this->getCountrySlug($countryCode) : '';
        $problemSlug  = $baseData['generation_params']['problem_slug'] ?? null;
        $templateId   = $baseData['template_id'] ?? null;

        $map = [];
        foreach ($supportedLanguages as $lang) {
            $langSlug = $this->buildSlug($audienceType, $lang, $countrySlug, $problemSlug, $templateId);
            $map[$lang] = '/' . $langSlug;
        }

        return $map;
    }

    private function calculateSeoScore(array $parsed): int
    {
        $score = 0;

        if (! empty($parsed['title'])) $score += 15;
        if (! empty($parsed['meta_title']) && strlen($parsed['meta_title']) <= 60) $score += 20;
        if (! empty($parsed['meta_description']) && strlen($parsed['meta_description']) <= 155) $score += 20;

        $sections = $parsed['sections'] ?? [];
        $types    = array_column($sections, 'type');

        if (in_array('hero', $types)) $score += 15;
        if (in_array('faq', $types))  $score += 15;
        if (in_array('cta', $types))  $score += 15;

        if (! empty($parsed['cta_links'])) $score += 0; // already covered by cta section

        return min(100, $score);
    }

    private function buildJsonLd(array $parsed, string $audienceType, string $countryName): array
    {
        $hasFaq = false;
        $faqItems = [];

        foreach ($parsed['sections'] ?? [] as $section) {
            if ($section['type'] === 'faq' && ! empty($section['content']['items'])) {
                $hasFaq = true;
                foreach ($section['content']['items'] as $item) {
                    $faqItems[] = [
                        '@type'          => 'Question',
                        'name'           => $item['q'] ?? '',
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text'  => $item['a'] ?? '',
                        ],
                    ];
                }
            }
        }

        $base = [
            '@context' => 'https://schema.org',
            '@type'    => 'WebPage',
            'name'     => $parsed['title'] ?? '',
            'description' => $parsed['meta_description'] ?? '',
        ];

        if ($hasFaq) {
            return [
                '@context'   => 'https://schema.org',
                '@graph'     => [
                    $base,
                    ['@type' => 'FAQPage', 'mainEntity' => $faqItems],
                ],
            ];
        }

        return $base;
    }

    public function buildSlug(
        string $audienceType,
        string $language,
        string $countrySlug,
        ?string $problemSlug = null,
        ?string $templateId = null,
    ): string {
        return match ($audienceType) {
            'clients'  => "{$language}/aide/{$problemSlug}/{$countrySlug}",
            'lawyers'  => "{$language}/devenir-partenaire/{$templateId}/{$countrySlug}",
            'helpers'  => "{$language}/expats-aidants/{$templateId}/{$countrySlug}",
            'matching' => "{$language}/expert/{$templateId}/{$countrySlug}",
            default    => "{$language}/landing/{$audienceType}/{$countrySlug}",
        };
    }

    private function getCountrySlug(string $countryCode): string
    {
        return self::COUNTRY_SLUGS_FR[$countryCode] ?? Str::slug(strtolower($countryCode));
    }

    /**
     * Retourne le nom du pays dans la langue demandée.
     * Utilise PHP intl (Locale::getDisplayRegion) si disponible — couvre TOUS les pays
     * dans TOUTES les langues via les données ICU. Fallback hardcodé pour les pays prioritaires.
     */
    private function getCountryName(string $countryCode, string $language): string
    {
        // 1. PHP intl extension (intl doit être activé dans php.ini)
        if (function_exists('locale_get_display_region')) {
            $name = locale_get_display_region('und-' . strtoupper($countryCode), $language);
            // Valider que ICU a bien retourné un nom (pas le code brut)
            if ($name && $name !== $countryCode && $name !== 'und-' . strtoupper($countryCode)) {
                return $name;
            }
        }

        // 2. Fallback hardcodé — pays prioritaires, 9 langues
        static $names = [
            'TH' => ['fr' => 'Thaïlande',       'en' => 'Thailand',           'es' => 'Tailandia',        'de' => 'Thailand',         'pt' => 'Tailândia',        'ar' => 'تايلاند',         'zh' => '泰国',      'hi' => 'थाईलैंड',   'ru' => 'Таиланд'],
            'VN' => ['fr' => 'Vietnam',           'en' => 'Vietnam',            'es' => 'Vietnam',          'de' => 'Vietnam',          'pt' => 'Vietnã',           'ar' => 'فيتنام',          'zh' => '越南',      'hi' => 'वियतनाम',   'ru' => 'Вьетнам'],
            'SG' => ['fr' => 'Singapour',         'en' => 'Singapore',          'es' => 'Singapur',         'de' => 'Singapur',         'pt' => 'Singapura',        'ar' => 'سنغافورة',        'zh' => '新加坡',    'hi' => 'सिंगापुर',  'ru' => 'Сингапур'],
            'MY' => ['fr' => 'Malaisie',          'en' => 'Malaysia',           'es' => 'Malasia',          'de' => 'Malaysia',         'pt' => 'Malásia',          'ar' => 'ماليزيا',         'zh' => '马来西亚',  'hi' => 'मलेशिया',   'ru' => 'Малайзия'],
            'PH' => ['fr' => 'Philippines',       'en' => 'Philippines',        'es' => 'Filipinas',        'de' => 'Philippinen',      'pt' => 'Filipinas',        'ar' => 'الفلبين',         'zh' => '菲律宾',    'hi' => 'फ़िलीपींस',  'ru' => 'Филиппины'],
            'JP' => ['fr' => 'Japon',             'en' => 'Japan',              'es' => 'Japón',            'de' => 'Japan',            'pt' => 'Japão',            'ar' => 'اليابان',         'zh' => '日本',      'hi' => 'जापान',     'ru' => 'Япония'],
            'AU' => ['fr' => 'Australie',         'en' => 'Australia',          'es' => 'Australia',        'de' => 'Australien',       'pt' => 'Austrália',        'ar' => 'أستراليا',        'zh' => '澳大利亚',  'hi' => 'ऑस्ट्रेलिया', 'ru' => 'Австралия'],
            'NZ' => ['fr' => 'Nouvelle-Zélande',  'en' => 'New Zealand',        'es' => 'Nueva Zelanda',    'de' => 'Neuseeland',       'pt' => 'Nova Zelândia',    'ar' => 'نيوزيلندا',       'zh' => '新西兰',    'hi' => 'न्यूजीलैंड', 'ru' => 'Новая Зеландия'],
            'ID' => ['fr' => 'Indonésie',         'en' => 'Indonesia',          'es' => 'Indonesia',        'de' => 'Indonesien',       'pt' => 'Indonésia',        'ar' => 'إندونيسيا',       'zh' => '印度尼西亚', 'hi' => 'इंडोनेशिया', 'ru' => 'Индонезия'],
            'KH' => ['fr' => 'Cambodge',          'en' => 'Cambodia',           'es' => 'Camboya',          'de' => 'Kambodscha',       'pt' => 'Camboja',          'ar' => 'كمبوديا',         'zh' => '柬埔寨',    'hi' => 'कंबोडिया',  'ru' => 'Камбоджа'],
            'MX' => ['fr' => 'Mexique',           'en' => 'Mexico',             'es' => 'México',           'de' => 'Mexiko',           'pt' => 'México',           'ar' => 'المكسيك',         'zh' => '墨西哥',    'hi' => 'मेक्सिको',  'ru' => 'Мексика'],
            'BR' => ['fr' => 'Brésil',            'en' => 'Brazil',             'es' => 'Brasil',           'de' => 'Brasilien',        'pt' => 'Brasil',           'ar' => 'البرازيل',        'zh' => '巴西',      'hi' => 'ब्राज़ील',   'ru' => 'Бразилия'],
            'CR' => ['fr' => 'Costa Rica',        'en' => 'Costa Rica',         'es' => 'Costa Rica',       'de' => 'Costa Rica',       'pt' => 'Costa Rica',       'ar' => 'كوستاريكا',       'zh' => '哥斯达黎加', 'hi' => 'कोस्टा रिका', 'ru' => 'Коста-Рика'],
            'CO' => ['fr' => 'Colombie',          'en' => 'Colombia',           'es' => 'Colombia',         'de' => 'Kolumbien',        'pt' => 'Colômbia',         'ar' => 'كولومبيا',        'zh' => '哥伦比亚',  'hi' => 'कोलंबिया',  'ru' => 'Колумбия'],
            'AR' => ['fr' => 'Argentine',         'en' => 'Argentina',          'es' => 'Argentina',        'de' => 'Argentinien',      'pt' => 'Argentina',        'ar' => 'الأرجنتين',       'zh' => '阿根廷',    'hi' => 'अर्जेंटीना', 'ru' => 'Аргентина'],
            'CL' => ['fr' => 'Chili',             'en' => 'Chile',              'es' => 'Chile',            'de' => 'Chile',            'pt' => 'Chile',            'ar' => 'تشيلي',           'zh' => '智利',      'hi' => 'चिली',      'ru' => 'Чили'],
            'PE' => ['fr' => 'Pérou',             'en' => 'Peru',               'es' => 'Perú',             'de' => 'Peru',             'pt' => 'Peru',             'ar' => 'بيرو',            'zh' => '秘鲁',      'hi' => 'पेरू',      'ru' => 'Перу'],
            'US' => ['fr' => 'États-Unis',        'en' => 'United States',      'es' => 'Estados Unidos',   'de' => 'Vereinigte Staaten', 'pt' => 'Estados Unidos', 'ar' => 'الولايات المتحدة', 'zh' => '美国',     'hi' => 'संयुक्त राज्य', 'ru' => 'США'],
            'CA' => ['fr' => 'Canada',            'en' => 'Canada',             'es' => 'Canadá',           'de' => 'Kanada',           'pt' => 'Canadá',           'ar' => 'كندا',            'zh' => '加拿大',    'hi' => 'कनाडा',     'ru' => 'Канада'],
            'FR' => ['fr' => 'France',            'en' => 'France',             'es' => 'Francia',          'de' => 'Frankreich',       'pt' => 'França',           'ar' => 'فرنسا',           'zh' => '法国',      'hi' => 'फ्रांस',    'ru' => 'Франция'],
            'ES' => ['fr' => 'Espagne',           'en' => 'Spain',              'es' => 'España',           'de' => 'Spanien',          'pt' => 'Espanha',          'ar' => 'إسبانيا',         'zh' => '西班牙',    'hi' => 'स्पेन',     'ru' => 'Испания'],
            'PT' => ['fr' => 'Portugal',          'en' => 'Portugal',           'es' => 'Portugal',         'de' => 'Portugal',         'pt' => 'Portugal',         'ar' => 'البرتغال',        'zh' => '葡萄牙',    'hi' => 'पुर्तगाल',  'ru' => 'Португалия'],
            'IT' => ['fr' => 'Italie',            'en' => 'Italy',              'es' => 'Italia',           'de' => 'Italien',          'pt' => 'Itália',           'ar' => 'إيطاليا',         'zh' => '意大利',    'hi' => 'इटली',      'ru' => 'Италия'],
            'DE' => ['fr' => 'Allemagne',         'en' => 'Germany',            'es' => 'Alemania',         'de' => 'Deutschland',      'pt' => 'Alemanha',         'ar' => 'ألمانيا',         'zh' => '德国',      'hi' => 'जर्मनी',    'ru' => 'Германия'],
            'NL' => ['fr' => 'Pays-Bas',          'en' => 'Netherlands',        'es' => 'Países Bajos',     'de' => 'Niederlande',      'pt' => 'Países Baixos',    'ar' => 'هولندا',          'zh' => '荷兰',      'hi' => 'नीदरलैंड',  'ru' => 'Нидерланды'],
            'BE' => ['fr' => 'Belgique',          'en' => 'Belgium',            'es' => 'Bélgica',          'de' => 'Belgien',          'pt' => 'Bélgica',          'ar' => 'بلجيكا',          'zh' => '比利时',    'hi' => 'बेल्जियम',  'ru' => 'Бельгия'],
            'CH' => ['fr' => 'Suisse',            'en' => 'Switzerland',        'es' => 'Suiza',            'de' => 'Schweiz',          'pt' => 'Suíça',            'ar' => 'سويسرا',          'zh' => '瑞士',      'hi' => 'स्विट्जरलैंड', 'ru' => 'Швейцария'],
            'GB' => ['fr' => 'Royaume-Uni',       'en' => 'United Kingdom',     'es' => 'Reino Unido',      'de' => 'Vereinigtes Königreich', 'pt' => 'Reino Unido', 'ar' => 'المملكة المتحدة', 'zh' => '英国',     'hi' => 'यूनाइटेड किंगडम', 'ru' => 'Великобритания'],
            'GR' => ['fr' => 'Grèce',             'en' => 'Greece',             'es' => 'Grecia',           'de' => 'Griechenland',     'pt' => 'Grécia',           'ar' => 'اليونان',         'zh' => '希腊',      'hi' => 'ग्रीस',     'ru' => 'Греция'],
            'AT' => ['fr' => 'Autriche',          'en' => 'Austria',            'es' => 'Austria',          'de' => 'Österreich',       'pt' => 'Áustria',          'ar' => 'النمسا',          'zh' => '奥地利',    'hi' => 'ऑस्ट्रिया', 'ru' => 'Австрия'],
            'SE' => ['fr' => 'Suède',             'en' => 'Sweden',             'es' => 'Suecia',           'de' => 'Schweden',         'pt' => 'Suécia',           'ar' => 'السويد',          'zh' => '瑞典',      'hi' => 'स्वीडन',    'ru' => 'Швеция'],
            'NO' => ['fr' => 'Norvège',           'en' => 'Norway',             'es' => 'Noruega',          'de' => 'Norwegen',         'pt' => 'Noruega',          'ar' => 'النرويج',         'zh' => '挪威',      'hi' => 'नॉर्वे',    'ru' => 'Норвегия'],
            'DK' => ['fr' => 'Danemark',          'en' => 'Denmark',            'es' => 'Dinamarca',        'de' => 'Dänemark',         'pt' => 'Dinamarca',        'ar' => 'الدنمارك',        'zh' => '丹麦',      'hi' => 'डेनमार्क',  'ru' => 'Дания'],
            'FI' => ['fr' => 'Finlande',          'en' => 'Finland',            'es' => 'Finlandia',        'de' => 'Finnland',         'pt' => 'Finlândia',        'ar' => 'فنلندا',          'zh' => '芬兰',      'hi' => 'फ़िनलैंड',  'ru' => 'Финляндия'],
            'IE' => ['fr' => 'Irlande',           'en' => 'Ireland',            'es' => 'Irlanda',          'de' => 'Irland',           'pt' => 'Irlanda',          'ar' => 'أيرلندا',         'zh' => '爱尔兰',    'hi' => 'आयरलैंड',   'ru' => 'Ирландия'],
            'PL' => ['fr' => 'Pologne',           'en' => 'Poland',             'es' => 'Polonia',          'de' => 'Polen',            'pt' => 'Polônia',          'ar' => 'بولندا',          'zh' => '波兰',      'hi' => 'पोलैंड',    'ru' => 'Польша'],
            'CZ' => ['fr' => 'Tchéquie',          'en' => 'Czechia',            'es' => 'República Checa',  'de' => 'Tschechien',       'pt' => 'Tchéquia',         'ar' => 'جمهورية التشيك',  'zh' => '捷克',      'hi' => 'चेक गणराज्य', 'ru' => 'Чехия'],
            'HU' => ['fr' => 'Hongrie',           'en' => 'Hungary',            'es' => 'Hungría',          'de' => 'Ungarn',           'pt' => 'Hungria',          'ar' => 'المجر',           'zh' => '匈牙利',    'hi' => 'हंगरी',     'ru' => 'Венгрия'],
            'RO' => ['fr' => 'Roumanie',          'en' => 'Romania',            'es' => 'Rumanía',          'de' => 'Rumänien',         'pt' => 'Romênia',          'ar' => 'رومانيا',         'zh' => '罗马尼亚',  'hi' => 'रोमानिया',  'ru' => 'Румыния'],
            'AE' => ['fr' => 'Émirats Arabes Unis', 'en' => 'United Arab Emirates', 'es' => 'Emiratos Árabes Unidos', 'de' => 'Vereinigte Arabische Emirate', 'pt' => 'Emirados Árabes Unidos', 'ar' => 'الإمارات العربية المتحدة', 'zh' => '阿联酋', 'hi' => 'संयुक्त अरब अमीरात', 'ru' => 'ОАЭ'],
            'QA' => ['fr' => 'Qatar',             'en' => 'Qatar',              'es' => 'Catar',            'de' => 'Katar',            'pt' => 'Catar',            'ar' => 'قطر',             'zh' => '卡塔尔',    'hi' => 'क़तर',      'ru' => 'Катар'],
            'SA' => ['fr' => 'Arabie Saoudite',   'en' => 'Saudi Arabia',       'es' => 'Arabia Saudita',   'de' => 'Saudi-Arabien',    'pt' => 'Arábia Saudita',   'ar' => 'المملكة العربية السعودية', 'zh' => '沙特阿拉伯', 'hi' => 'सऊदी अरब', 'ru' => 'Саудовская Аравия'],
            'KW' => ['fr' => 'Koweït',            'en' => 'Kuwait',             'es' => 'Kuwait',           'de' => 'Kuwait',           'pt' => 'Kuwait',           'ar' => 'الكويت',          'zh' => '科威特',    'hi' => 'कुवैत',     'ru' => 'Кувейт'],
            'BH' => ['fr' => 'Bahreïn',           'en' => 'Bahrain',            'es' => 'Baréin',           'de' => 'Bahrain',          'pt' => 'Bahrein',          'ar' => 'البحرين',         'zh' => '巴林',      'hi' => 'बहरीन',     'ru' => 'Бахрейн'],
            'OM' => ['fr' => 'Oman',              'en' => 'Oman',               'es' => 'Omán',             'de' => 'Oman',             'pt' => 'Omã',              'ar' => 'عُمان',           'zh' => '阿曼',      'hi' => 'ओमान',      'ru' => 'Оман'],
            'IL' => ['fr' => 'Israël',            'en' => 'Israel',             'es' => 'Israel',           'de' => 'Israel',           'pt' => 'Israel',           'ar' => 'إسرائيل',         'zh' => '以色列',    'hi' => 'इज़राइल',   'ru' => 'Израиль'],
            'LB' => ['fr' => 'Liban',             'en' => 'Lebanon',            'es' => 'Líbano',           'de' => 'Libanon',          'pt' => 'Líbano',           'ar' => 'لبنان',           'zh' => '黎巴嫩',    'hi' => 'लेबनान',    'ru' => 'Ливан'],
            'TR' => ['fr' => 'Turquie',           'en' => 'Turkey',             'es' => 'Turquía',          'de' => 'Türkei',           'pt' => 'Turquia',          'ar' => 'تركيا',           'zh' => '土耳其',    'hi' => 'तुर्की',    'ru' => 'Турция'],
            'EG' => ['fr' => 'Égypte',            'en' => 'Egypt',              'es' => 'Egipto',           'de' => 'Ägypten',          'pt' => 'Egito',            'ar' => 'مصر',             'zh' => '埃及',      'hi' => 'मिस्र',     'ru' => 'Египет'],
            'MA' => ['fr' => 'Maroc',             'en' => 'Morocco',            'es' => 'Marruecos',        'de' => 'Marokko',          'pt' => 'Marrocos',         'ar' => 'المغرب',          'zh' => '摩洛哥',    'hi' => 'मोरक्को',   'ru' => 'Марокко'],
            'TN' => ['fr' => 'Tunisie',           'en' => 'Tunisia',            'es' => 'Túnez',            'de' => 'Tunesien',         'pt' => 'Tunísia',          'ar' => 'تونس',            'zh' => '突尼斯',    'hi' => 'ट्यूनीशिया', 'ru' => 'Тунис'],
            'DZ' => ['fr' => 'Algérie',           'en' => 'Algeria',            'es' => 'Argelia',          'de' => 'Algerien',         'pt' => 'Argélia',          'ar' => 'الجزائر',         'zh' => '阿尔及利亚', 'hi' => 'अल्जीरिया',  'ru' => 'Алжир'],
            'SN' => ['fr' => 'Sénégal',           'en' => 'Senegal',            'es' => 'Senegal',          'de' => 'Senegal',          'pt' => 'Senegal',          'ar' => 'السنغال',         'zh' => '塞内加尔',  'hi' => 'सेनेगल',    'ru' => 'Сенегал'],
            'CI' => ['fr' => 'Côte d\'Ivoire',    'en' => 'Ivory Coast',        'es' => 'Costa de Marfil',  'de' => 'Elfenbeinküste',   'pt' => 'Costa do Marfim',  'ar' => 'ساحل العاج',      'zh' => '象牙海岸',  'hi' => 'आइवरी कोस्ट', 'ru' => 'Кот-д\'Ивуар'],
            'CM' => ['fr' => 'Cameroun',          'en' => 'Cameroon',           'es' => 'Camerún',          'de' => 'Kamerun',          'pt' => 'Camarões',         'ar' => 'الكاميرون',       'zh' => '喀麦隆',    'hi' => 'कैमरून',    'ru' => 'Камерун'],
            'ZA' => ['fr' => 'Afrique du Sud',    'en' => 'South Africa',       'es' => 'Sudáfrica',        'de' => 'Südafrika',        'pt' => 'África do Sul',    'ar' => 'جنوب أفريقيا',   'zh' => '南非',      'hi' => 'दक्षिण अफ्रीका', 'ru' => 'ЮАР'],
            'KE' => ['fr' => 'Kenya',             'en' => 'Kenya',              'es' => 'Kenia',            'de' => 'Kenia',            'pt' => 'Quênia',           'ar' => 'كينيا',           'zh' => '肯尼亚',    'hi' => 'केन्या',    'ru' => 'Кения'],
            'NG' => ['fr' => 'Nigeria',           'en' => 'Nigeria',            'es' => 'Nigeria',          'de' => 'Nigeria',          'pt' => 'Nigéria',          'ar' => 'نيجيريا',         'zh' => '尼日利亚',  'hi' => 'नाइजीरिया', 'ru' => 'Нигерия'],
            'GH' => ['fr' => 'Ghana',             'en' => 'Ghana',              'es' => 'Ghana',            'de' => 'Ghana',            'pt' => 'Gana',             'ar' => 'غانا',            'zh' => '加纳',      'hi' => 'घाना',      'ru' => 'Гана'],
            'IN' => ['fr' => 'Inde',              'en' => 'India',              'es' => 'India',            'de' => 'Indien',           'pt' => 'Índia',            'ar' => 'الهند',           'zh' => '印度',      'hi' => 'भारत',      'ru' => 'Индия'],
            'PK' => ['fr' => 'Pakistan',          'en' => 'Pakistan',           'es' => 'Pakistán',         'de' => 'Pakistan',         'pt' => 'Paquistão',        'ar' => 'باكستان',         'zh' => '巴基斯坦',  'hi' => 'पाकिस्तान', 'ru' => 'Пакистан'],
            'LK' => ['fr' => 'Sri Lanka',         'en' => 'Sri Lanka',          'es' => 'Sri Lanka',        'de' => 'Sri Lanka',        'pt' => 'Sri Lanka',        'ar' => 'سريلانكا',        'zh' => '斯里兰卡',  'hi' => 'श्रीलंका',  'ru' => 'Шри-Ланка'],
            'NP' => ['fr' => 'Népal',             'en' => 'Nepal',              'es' => 'Nepal',            'de' => 'Nepal',            'pt' => 'Nepal',            'ar' => 'نيبال',           'zh' => '尼泊尔',    'hi' => 'नेपाल',     'ru' => 'Непал'],
            'BD' => ['fr' => 'Bangladesh',        'en' => 'Bangladesh',         'es' => 'Bangladés',        'de' => 'Bangladesch',      'pt' => 'Bangladesh',       'ar' => 'بنغلاديش',        'zh' => '孟加拉国',  'hi' => 'बांग्लादेश', 'ru' => 'Бангладеш'],
            'CN' => ['fr' => 'Chine',             'en' => 'China',              'es' => 'China',            'de' => 'China',            'pt' => 'China',            'ar' => 'الصين',           'zh' => '中国',      'hi' => 'चीन',       'ru' => 'Китай'],
            'KR' => ['fr' => 'Corée du Sud',      'en' => 'South Korea',        'es' => 'Corea del Sur',    'de' => 'Südkorea',         'pt' => 'Coreia do Sul',    'ar' => 'كوريا الجنوبية', 'zh' => '韩国',      'hi' => 'दक्षिण कोरिया', 'ru' => 'Южная Корея'],
            'HK' => ['fr' => 'Hong Kong',         'en' => 'Hong Kong',          'es' => 'Hong Kong',        'de' => 'Hongkong',         'pt' => 'Hong Kong',        'ar' => 'هونغ كونغ',       'zh' => '香港',      'hi' => 'हांगकांग',  'ru' => 'Гонконг'],
            'TW' => ['fr' => 'Taïwan',            'en' => 'Taiwan',             'es' => 'Taiwán',           'de' => 'Taiwan',           'pt' => 'Taiwan',           'ar' => 'تايوان',          'zh' => '台湾',      'hi' => 'ताइवान',    'ru' => 'Тайвань'],
            'RU' => ['fr' => 'Russie',                          'en' => 'Russia',                      'es' => 'Rusia',                       'de' => 'Russland',                    'pt' => 'Rússia',                      'ar' => 'روسيا',                   'zh' => '俄罗斯',    'hi' => 'रूस',            'ru' => 'Россия'],
            'UA' => ['fr' => 'Ukraine',                         'en' => 'Ukraine',                     'es' => 'Ucrania',                     'de' => 'Ukraine',                     'pt' => 'Ucrânia',                     'ar' => 'أوكرانيا',                'zh' => '乌克兰',    'hi' => 'यूक्रेन',       'ru' => 'Украина'],
            // Moyen-Orient complémentaires
            'JO' => ['fr' => 'Jordanie',                        'en' => 'Jordan',                      'es' => 'Jordania',                    'de' => 'Jordanien',                   'pt' => 'Jordânia',                    'ar' => 'الأردن',                  'zh' => '约旦',      'hi' => 'जॉर्डन',        'ru' => 'Иордания'],
            // Afrique sub-saharienne complémentaires
            'ET' => ['fr' => 'Éthiopie',                        'en' => 'Ethiopia',                    'es' => 'Etiopía',                     'de' => 'Äthiopien',                   'pt' => 'Etiópia',                     'ar' => 'إثيوبيا',                 'zh' => '埃塞俄比亚', 'hi' => 'इथियोपिया',      'ru' => 'Эфиопия'],
            // Amérique latine complémentaires
            'EC' => ['fr' => 'Équateur',                        'en' => 'Ecuador',                     'es' => 'Ecuador',                     'de' => 'Ecuador',                     'pt' => 'Equador',                     'ar' => 'الإكوادور',               'zh' => '厄瓜多尔',  'hi' => 'इक्वाडोर',       'ru' => 'Эквадор'],
            'UY' => ['fr' => 'Uruguay',                         'en' => 'Uruguay',                     'es' => 'Uruguay',                     'de' => 'Uruguay',                     'pt' => 'Uruguai',                     'ar' => 'أوروغواي',                'zh' => '乌拉圭',    'hi' => 'उरुग्वे',        'ru' => 'Уругвай'],
            'PY' => ['fr' => 'Paraguay',                        'en' => 'Paraguay',                    'es' => 'Paraguay',                    'de' => 'Paraguay',                    'pt' => 'Paraguai',                    'ar' => 'باراغواي',                'zh' => '巴拉圭',    'hi' => 'पराग्वे',        'ru' => 'Парагвай'],
            'BO' => ['fr' => 'Bolivie',                         'en' => 'Bolivia',                     'es' => 'Bolivia',                     'de' => 'Bolivien',                    'pt' => 'Bolívia',                     'ar' => 'بوليفيا',                 'zh' => '玻利维亚',  'hi' => 'बोलीविया',       'ru' => 'Боливия'],
            'VE' => ['fr' => 'Venezuela',                       'en' => 'Venezuela',                   'es' => 'Venezuela',                   'de' => 'Venezuela',                   'pt' => 'Venezuela',                   'ar' => 'فنزويلا',                 'zh' => '委内瑞拉',  'hi' => 'वेनेज़ुएला',     'ru' => 'Венесуэла'],
            'DO' => ['fr' => 'République dominicaine',          'en' => 'Dominican Republic',          'es' => 'República Dominicana',        'de' => 'Dominikanische Republik',     'pt' => 'República Dominicana',        'ar' => 'جمهورية الدومينيكان',     'zh' => '多米尼加',  'hi' => 'डोमिनिकन गणराज्य', 'ru' => 'Доминиканская Республика'],
            'GT' => ['fr' => 'Guatemala',                       'en' => 'Guatemala',                   'es' => 'Guatemala',                   'de' => 'Guatemala',                   'pt' => 'Guatemala',                   'ar' => 'غواتيمالا',               'zh' => '危地马拉',  'hi' => 'ग्वाटेमाला',     'ru' => 'Гватемала'],
            'HN' => ['fr' => 'Honduras',                        'en' => 'Honduras',                    'es' => 'Honduras',                    'de' => 'Honduras',                    'pt' => 'Honduras',                    'ar' => 'هندوراس',                 'zh' => '洪都拉斯',  'hi' => 'होंडुरास',       'ru' => 'Гондурас'],
            'SV' => ['fr' => 'Salvador',                        'en' => 'El Salvador',                 'es' => 'El Salvador',                 'de' => 'El Salvador',                 'pt' => 'El Salvador',                 'ar' => 'السلفادور',               'zh' => '萨尔瓦多',  'hi' => 'एल साल्वाडोर',   'ru' => 'Сальвадор'],
            'NI' => ['fr' => 'Nicaragua',                       'en' => 'Nicaragua',                   'es' => 'Nicaragua',                   'de' => 'Nicaragua',                   'pt' => 'Nicarágua',                   'ar' => 'نيكاراغوا',               'zh' => '尼加拉瓜',  'hi' => 'निकारागुआ',      'ru' => 'Никарагуа'],
            'PA' => ['fr' => 'Panama',                          'en' => 'Panama',                      'es' => 'Panamá',                      'de' => 'Panama',                      'pt' => 'Panamá',                      'ar' => 'بنما',                    'zh' => '巴拿马',    'hi' => 'पनामा',          'ru' => 'Панама'],
            'CU' => ['fr' => 'Cuba',                            'en' => 'Cuba',                        'es' => 'Cuba',                        'de' => 'Kuba',                        'pt' => 'Cuba',                        'ar' => 'كوبا',                    'zh' => '古巴',      'hi' => 'क्यूबा',         'ru' => 'Куба'],
            'HT' => ['fr' => 'Haïti',                           'en' => 'Haiti',                       'es' => 'Haití',                       'de' => 'Haiti',                       'pt' => 'Haiti',                       'ar' => 'هايتي',                   'zh' => '海地',      'hi' => 'हैती',           'ru' => 'Гаити'],
            'JM' => ['fr' => 'Jamaïque',                        'en' => 'Jamaica',                     'es' => 'Jamaica',                     'de' => 'Jamaika',                     'pt' => 'Jamaica',                     'ar' => 'جامايكا',                 'zh' => '牙买加',    'hi' => 'जमैका',          'ru' => 'Ямайка'],
            'TT' => ['fr' => 'Trinité-et-Tobago',               'en' => 'Trinidad and Tobago',         'es' => 'Trinidad y Tobago',           'de' => 'Trinidad und Tobago',         'pt' => 'Trinidad e Tobago',           'ar' => 'ترينيداد وتوباغو',        'zh' => '特立尼达和多巴哥', 'hi' => 'त्रिनिदाद और टोबैगो', 'ru' => 'Тринидад и Тобаго'],
            'BB' => ['fr' => 'Barbade',                         'en' => 'Barbados',                    'es' => 'Barbados',                    'de' => 'Barbados',                    'pt' => 'Barbados',                    'ar' => 'بربادوس',                 'zh' => '巴巴多斯',  'hi' => 'बारबाडोस',       'ru' => 'Барбадос'],
            'LC' => ['fr' => 'Sainte-Lucie',                    'en' => 'Saint Lucia',                 'es' => 'Santa Lucía',                 'de' => 'St. Lucia',                   'pt' => 'Santa Lúcia',                 'ar' => 'سانت لوسيا',              'zh' => '圣卢西亚', 'hi' => 'सेंट लूसिया',    'ru' => 'Сент-Люсия'],
            'VC' => ['fr' => 'Saint-Vincent-et-les-Grenadines', 'en' => 'Saint Vincent and the Grenadines', 'es' => 'San Vicente y las Granadinas', 'de' => 'St. Vincent und die Grenadinen', 'pt' => 'São Vicente e Granadinas', 'ar' => 'سانت فنسنت وجزر غرينادين', 'zh' => '圣文森特和格林纳丁斯', 'hi' => 'सेंट विंसेंट', 'ru' => 'Сент-Винсент'],
            'GD' => ['fr' => 'Grenade',                         'en' => 'Grenada',                     'es' => 'Granada',                     'de' => 'Grenada',                     'pt' => 'Granada',                     'ar' => 'غرينادا',                 'zh' => '格林纳达',  'hi' => 'ग्रेनाडा',       'ru' => 'Гренада'],
            'AG' => ['fr' => 'Antigua-et-Barbuda',              'en' => 'Antigua and Barbuda',         'es' => 'Antigua y Barbuda',           'de' => 'Antigua und Barbuda',         'pt' => 'Antígua e Barbuda',           'ar' => 'أنتيغوا وبربودا',         'zh' => '安提瓜和巴布达', 'hi' => 'एंटीगुआ और बारबुडा', 'ru' => 'Антигуа и Барбуда'],
            'DM' => ['fr' => 'Dominique',                       'en' => 'Dominica',                    'es' => 'Dominica',                    'de' => 'Dominica',                    'pt' => 'Dominica',                    'ar' => 'دومينيكا',                'zh' => '多米尼克',  'hi' => 'डोमिनिका',       'ru' => 'Доминика'],
            // Europe orientale complémentaires
            'SK' => ['fr' => 'Slovaquie',                       'en' => 'Slovakia',                    'es' => 'Eslovaquia',                  'de' => 'Slowakei',                    'pt' => 'Eslováquia',                  'ar' => 'سلوفاكيا',                'zh' => '斯洛伐克',  'hi' => 'स्लोवाकिया',     'ru' => 'Словакия'],
            'BG' => ['fr' => 'Bulgarie',                        'en' => 'Bulgaria',                    'es' => 'Bulgaria',                    'de' => 'Bulgarien',                   'pt' => 'Bulgária',                    'ar' => 'بلغاريا',                 'zh' => '保加利亚',  'hi' => 'बुल्गारिया',     'ru' => 'Болгария'],
            'HR' => ['fr' => 'Croatie',                         'en' => 'Croatia',                     'es' => 'Croacia',                     'de' => 'Kroatien',                    'pt' => 'Croácia',                     'ar' => 'كرواتيا',                 'zh' => '克罗地亚',  'hi' => 'क्रोएशिया',      'ru' => 'Хорватия'],
            'SI' => ['fr' => 'Slovénie',                        'en' => 'Slovenia',                    'es' => 'Eslovenia',                   'de' => 'Slowenien',                   'pt' => 'Eslovênia',                   'ar' => 'سلوفينيا',                'zh' => '斯洛文尼亚', 'hi' => 'स्लोवेनिया',    'ru' => 'Словения'],
            'RS' => ['fr' => 'Serbie',                          'en' => 'Serbia',                      'es' => 'Serbia',                      'de' => 'Serbien',                     'pt' => 'Sérvia',                      'ar' => 'صربيا',                   'zh' => '塞尔维亚',  'hi' => 'सर्बिया',        'ru' => 'Сербия'],
            'BA' => ['fr' => 'Bosnie-Herzégovine',              'en' => 'Bosnia and Herzegovina',      'es' => 'Bosnia y Herzegovina',        'de' => 'Bosnien und Herzegowina',     'pt' => 'Bósnia e Herzegovina',        'ar' => 'البوسنة والهرسك',         'zh' => '波斯尼亚',  'hi' => 'बोस्निया',       'ru' => 'Босния и Герцеговина'],
            'MK' => ['fr' => 'Macédoine du Nord',               'en' => 'North Macedonia',             'es' => 'Macedonia del Norte',         'de' => 'Nordmazedonien',              'pt' => 'Macedônia do Norte',          'ar' => 'مقدونيا الشمالية',        'zh' => '北马其顿',  'hi' => 'उत्तर मैसेडोनिया', 'ru' => 'Северная Македония'],
            'AL' => ['fr' => 'Albanie',                         'en' => 'Albania',                     'es' => 'Albania',                     'de' => 'Albanien',                    'pt' => 'Albânia',                     'ar' => 'ألبانيا',                 'zh' => '阿尔巴尼亚', 'hi' => 'अल्बानिया',     'ru' => 'Албания'],
            'ME' => ['fr' => 'Monténégro',                      'en' => 'Montenegro',                  'es' => 'Montenegro',                  'de' => 'Montenegro',                  'pt' => 'Montenegro',                  'ar' => 'الجبل الأسود',            'zh' => '黑山',      'hi' => 'मोंटेनेग्रो',    'ru' => 'Черногория'],
            'LU' => ['fr' => 'Luxembourg',                      'en' => 'Luxembourg',                  'es' => 'Luxemburgo',                  'de' => 'Luxemburg',                   'pt' => 'Luxemburgo',                  'ar' => 'لوكسمبورغ',               'zh' => '卢森堡',    'hi' => 'लक्समबर्ग',      'ru' => 'Люксембург'],
            'IS' => ['fr' => 'Islande',                         'en' => 'Iceland',                     'es' => 'Islandia',                    'de' => 'Island',                      'pt' => 'Islândia',                    'ar' => 'أيسلندا',                 'zh' => '冰岛',      'hi' => 'आइसलैंड',        'ru' => 'Исландия'],
            'CY' => ['fr' => 'Chypre',                          'en' => 'Cyprus',                      'es' => 'Chipre',                      'de' => 'Zypern',                      'pt' => 'Chipre',                      'ar' => 'قبرص',                    'zh' => '塞浦路斯',  'hi' => 'साइप्रस',        'ru' => 'Кипр'],
            'MT' => ['fr' => 'Malte',                           'en' => 'Malta',                       'es' => 'Malta',                       'de' => 'Malta',                       'pt' => 'Malta',                       'ar' => 'مالطا',                   'zh' => '马耳他',    'hi' => 'माल्टा',         'ru' => 'Мальта'],
            // Asie centrale
            'BY' => ['fr' => 'Biélorussie',                     'en' => 'Belarus',                     'es' => 'Bielorrusia',                 'de' => 'Weißrussland',                'pt' => 'Bielorrússia',                'ar' => 'بيلاروسيا',               'zh' => '白俄罗斯',  'hi' => 'बेलारूस',        'ru' => 'Беларусь'],
            'MD' => ['fr' => 'Moldavie',                        'en' => 'Moldova',                     'es' => 'Moldavia',                    'de' => 'Moldau',                      'pt' => 'Moldávia',                    'ar' => 'مولدوفا',                 'zh' => '摩尔多瓦',  'hi' => 'मोल्दोवा',       'ru' => 'Молдова'],
            'GE' => ['fr' => 'Géorgie',                         'en' => 'Georgia',                     'es' => 'Georgia',                     'de' => 'Georgien',                    'pt' => 'Geórgia',                     'ar' => 'جورجيا',                  'zh' => '格鲁吉亚',  'hi' => 'जॉर्जिया',       'ru' => 'Грузия'],
            'AM' => ['fr' => 'Arménie',                         'en' => 'Armenia',                     'es' => 'Armenia',                     'de' => 'Armenien',                    'pt' => 'Armênia',                     'ar' => 'أرمينيا',                 'zh' => '亚美尼亚',  'hi' => 'आर्मेनिया',      'ru' => 'Армения'],
            'AZ' => ['fr' => 'Azerbaïdjan',                     'en' => 'Azerbaijan',                  'es' => 'Azerbaiyán',                  'de' => 'Aserbaidschan',               'pt' => 'Azerbaijão',                  'ar' => 'أذربيجان',                'zh' => '阿塞拜疆',  'hi' => 'अज़रबैजान',      'ru' => 'Азербайджан'],
            'KZ' => ['fr' => 'Kazakhstan',                      'en' => 'Kazakhstan',                  'es' => 'Kazajistán',                  'de' => 'Kasachstan',                  'pt' => 'Cazaquistão',                 'ar' => 'كازاخستان',               'zh' => '哈萨克斯坦', 'hi' => 'कज़ाकस्तान',   'ru' => 'Казахстан'],
            'UZ' => ['fr' => 'Ouzbékistan',                     'en' => 'Uzbekistan',                  'es' => 'Uzbekistán',                  'de' => 'Usbekistan',                  'pt' => 'Uzbequistão',                 'ar' => 'أوزبكستان',               'zh' => '乌兹别克斯坦', 'hi' => 'उज़्बेकिस्तान', 'ru' => 'Узбекистан'],
            'TM' => ['fr' => 'Turkménistan',                    'en' => 'Turkmenistan',                'es' => 'Turkmenistán',                'de' => 'Turkmenistan',                'pt' => 'Turcomenistão',               'ar' => 'تركمانستان',              'zh' => '土库曼斯坦', 'hi' => 'तुर्कमेनिस्तान', 'ru' => 'Туркменистан'],
            'KG' => ['fr' => 'Kirghizistan',                    'en' => 'Kyrgyzstan',                  'es' => 'Kirguistán',                  'de' => 'Kirgisistan',                 'pt' => 'Quirguistão',                 'ar' => 'قيرغيزستان',              'zh' => '吉尔吉斯斯坦', 'hi' => 'किर्गिज़स्तान', 'ru' => 'Кыргызстан'],
            'TJ' => ['fr' => 'Tadjikistan',                     'en' => 'Tajikistan',                  'es' => 'Tayikistán',                  'de' => 'Tadschikistan',               'pt' => 'Tajiquistão',                 'ar' => 'طاجيكستان',               'zh' => '塔吉克斯坦', 'hi' => 'ताजिकिस्तान',  'ru' => 'Таджикистан'],
            // Asie du Sud-Est complémentaires
            'MM' => ['fr' => 'Myanmar',                         'en' => 'Myanmar',                     'es' => 'Myanmar',                     'de' => 'Myanmar',                     'pt' => 'Myanmar',                     'ar' => 'ميانمار',                 'zh' => '缅甸',      'hi' => 'म्यांमार',       'ru' => 'Мьянма'],
            'LA' => ['fr' => 'Laos',                            'en' => 'Laos',                        'es' => 'Laos',                        'de' => 'Laos',                        'pt' => 'Laos',                        'ar' => 'لاوس',                    'zh' => '老挝',      'hi' => 'लाओस',           'ru' => 'Лаос'],
            'TL' => ['fr' => 'Timor oriental',                  'en' => 'Timor-Leste',                 'es' => 'Timor Oriental',              'de' => 'Osttimor',                    'pt' => 'Timor-Leste',                 'ar' => 'تيمور الشرقية',           'zh' => '东帝汶',    'hi' => 'पूर्वी तिमोर',   'ru' => 'Восточный Тимор'],
            // Océanie complémentaires
            'FJ' => ['fr' => 'Fidji',                           'en' => 'Fiji',                        'es' => 'Fiyi',                        'de' => 'Fidschi',                     'pt' => 'Fiji',                        'ar' => 'فيجي',                    'zh' => '斐济',      'hi' => 'फ़िजी',           'ru' => 'Фиджи'],
            'PG' => ['fr' => 'Papouasie-Nouvelle-Guinée',       'en' => 'Papua New Guinea',            'es' => 'Papúa Nueva Guinea',          'de' => 'Papua-Neuguinea',             'pt' => 'Papua-Nova Guiné',            'ar' => 'بابوا غينيا الجديدة',     'zh' => '巴布亚新几内亚', 'hi' => 'पापुआ न्यू गिनी', 'ru' => 'Папуа — Новая Гвинея'],
            'VU' => ['fr' => 'Vanuatu',                         'en' => 'Vanuatu',                     'es' => 'Vanuatu',                     'de' => 'Vanuatu',                     'pt' => 'Vanuatu',                     'ar' => 'فانواتو',                 'zh' => '瓦努阿图',  'hi' => 'वानुअतु',        'ru' => 'Вануату'],
            // Asie de l'Est complémentaires
            'MN' => ['fr' => 'Mongolie',                        'en' => 'Mongolia',                    'es' => 'Mongolia',                    'de' => 'Mongolei',                    'pt' => 'Mongólia',                    'ar' => 'منغوليا',                 'zh' => '蒙古',      'hi' => 'मंगोलिया',       'ru' => 'Монголия'],
        ];

        return $names[$countryCode][$language]
            ?? $names[$countryCode]['en']
            ?? $names[$countryCode]['fr']
            ?? $countryCode;
    }

    private function formatSections(array $sections): string
    {
        return implode(', ', $sections);
    }

    /**
     * Retourne les définitions des templates pour une audience.
     * Utilisé par le frontend pour afficher les options de config.
     */
    public static function getTemplatesForAudience(string $audienceType): array
    {
        $templates = self::TEMPLATES[$audienceType] ?? [];

        return collect($templates)->map(function ($def, $id) {
            return [
                'id'          => $id,
                'label'       => $def['label'],
                'description' => $def['tone'] ?? ($def['angle'] ?? ''),
            ];
        })->values()->toArray();
    }
}
