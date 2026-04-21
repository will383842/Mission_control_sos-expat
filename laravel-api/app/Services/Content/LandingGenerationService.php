<?php

namespace App\Services\Content;

use App\Models\ApiCost;
use App\Models\CountryGeo;
use App\Models\LandingCampaign;
use App\Models\LandingPage;
use App\Models\LandingProblem;
use App\Models\Sondage;
use App\Services\AI\ClaudeService;
use App\Services\AI\OpenAiService;
use App\Services\AI\UnsplashService;
use App\Services\AI\UnsplashUsageTracker;
use App\Services\Content\KnowledgeBaseService;
use App\Services\Content\StatisticsInjectionService;
use App\Services\Seo\GeoMetaService;
use App\Services\Seo\JsonLdService;
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

        // ── Nouveaux types 2026 ─────────────────────────────────────────────
        'category_pillar' => [
            'overview' => [
                'label'    => 'Vue d\'ensemble catégorie',
                'intent'   => 'information',
                'tone'     => 'Autoritaire, pédagogique, exhaustif. Page pilier qui répond à TOUTES les questions de la catégorie pour ce pays. Chiffres précis, étapes concrètes, FAQ longue.',
                'sections' => ['hero', 'guide_steps', 'faq', 'local_info', 'trust_signals', 'cta'],
            ],
            'guide' => [
                'label'    => 'Guide pratique catégorie',
                'intent'   => 'information',
                'tone'     => 'Pratique, actionnable, step-by-step. Checklists concrètes. Sous-titres H2 reformulés comme des PAA Google.',
                'sections' => ['hero', 'guide_steps', 'faq', 'cta'],
            ],
        ],
        'profile' => [
            'profile_general' => [
                'label'    => 'Profil général',
                'intent'   => 'navigational',
                'tone'     => 'Empathique, "je comprends ta situation". Vocabulaire EXACTEMENT adapté au profil cible. Anticipe les peurs et objections de ce profil.',
                'sections' => ['hero', 'features', 'faq', 'trust_signals', 'cta'],
            ],
            'profile_guide' => [
                'label'    => 'Guide profil',
                'intent'   => 'information',
                'tone'     => 'Pratique et rassurant. Step-by-step adapté au parcours de ce profil. Anticipe les 5 blocages principaux.',
                'sections' => ['hero', 'guide_steps', 'faq', 'cta'],
            ],
        ],
        'emergency' => [
            'emergency' => [
                'label'    => 'Urgence pays',
                'intent'   => 'urgency',
                'tone'     => 'ULTRA-URGENT. Chaque seconde compte. Phrases de 5-8 mots. CTA au-dessus du fold. Pas d\'intro, réponse directe immédiate.',
                'sections' => ['hero', 'trust_signals', 'faq', 'cta'],
            ],
        ],
        'nationality' => [
            'nationality_general' => [
                'label'    => 'Nationalité × pays destination',
                'intent'   => 'transactional',
                'tone'     => 'Culturellement ancré dans la nationalité d\'origine. Références aux procédures administratives du pays d\'origine. Anticipe les spécificités bilatérales (conventions fiscales, accords de sécurité sociale, restrictions douanières).',
                'sections' => ['hero', 'local_info', 'guide_steps', 'faq', 'trust_signals', 'cta'],
            ],
        ],
    ];

    /** Segments d'URL localisés par audience × langue (ASCII-only, kebab-case) */
    private const URL_SEGMENTS = [
        'clients' => [
            'fr'=>'aide',    'en'=>'help',     'es'=>'ayuda',
            'de'=>'hilfe',   'pt'=>'ajuda',    'ar'=>'aide',
            'hi'=>'madad',   'zh'=>'bangzhu',  'ru'=>'pomoshch',
        ],
        'lawyers' => [
            'fr'=>'devenir-partenaire',  'en'=>'become-partner',   'es'=>'ser-socio',
            'de'=>'partner-werden',      'pt'=>'tornar-parceiro',  'ar'=>'devenir-partenaire',
            'hi'=>'sajhidari-bane',      'zh'=>'chengwei-hezuo',   'ru'=>'stat-partnerom',
        ],
        'helpers' => [
            'fr'=>'expats-aidants',   'en'=>'expat-helpers',    'es'=>'expats-ayudantes',
            'de'=>'expat-helfer',     'pt'=>'expats-ajudantes', 'ar'=>'expats-aidants',
            'hi'=>'expat-sahayta',    'zh'=>'expat-bangzhu',    'ru'=>'expat-pomoshchniki',
        ],
        'matching' => [
            'fr'=>'expert',        'en'=>'expert',        'es'=>'experto',
            'de'=>'experte',       'pt'=>'especialista',  'ar'=>'expert',
            'hi'=>'visheshagya',   'zh'=>'zhuanjia',      'ru'=>'ekspert',
        ],
        'category_pillar' => [
            'fr'=>'aide',    'en'=>'help',     'es'=>'ayuda',
            'de'=>'hilfe',   'pt'=>'ajuda',    'ar'=>'aide',
            'hi'=>'madad',   'zh'=>'bangzhu',  'ru'=>'pomoshch',
        ],
        'profile' => [
            'fr'=>'aide',    'en'=>'help',     'es'=>'ayuda',
            'de'=>'hilfe',   'pt'=>'ajuda',    'ar'=>'aide',
            'hi'=>'madad',   'zh'=>'bangzhu',  'ru'=>'pomoshch',
        ],
        'emergency' => [
            'fr'=>'urgence',   'en'=>'emergency',       'es'=>'emergencia',
            'de'=>'notfall',   'pt'=>'emergencia',      'ar'=>'tawari',
            'hi'=>'aapat',     'zh'=>'jinjiqingkuang',  'ru'=>'ekstrennaya-pomoshch',
        ],
        'nationality' => [
            'fr'=>'aide',    'en'=>'help',     'es'=>'ayuda',
            'de'=>'hilfe',   'pt'=>'ajuda',    'ar'=>'aide',
            'hi'=>'madad',   'zh'=>'bangzhu',  'ru'=>'pomoshch',
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
        private OpenAiService              $openAi,          // PRIMARY: GPT-4o pour génération
        private ClaudeService              $claude,          // FALLBACK: si OpenAI down
        private UnsplashService            $unsplash,
        private UnsplashUsageTracker       $unsplashTracker,
        private KnowledgeBaseService       $kb,
        private StatisticsInjectionService $stats,
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
     *   parent_id?: int|null,
     *   featured_image_url?: string|null,
     *   featured_image_alt?: string|null,
     *   featured_image_attribution?: string|null,
     *   photographer_name?: string|null,
     *   photographer_url?: string|null,
     * } $params
     */
    public function generate(array $params): LandingPage
    {
        $audienceType = $params['audience_type'];
        $templateId   = $params['template_id'];
        $countryCode  = $params['country_code'];
        $language     = $params['language'] ?? 'fr';

        // Shell créé AVANT les appels IA pour rattacher les api_costs à la LP.
        $shell = $this->createLandingShell($params);

        try {
            return match ($audienceType) {
                'clients'  => $this->generateClientLanding(
                    LandingProblem::where('slug', $params['problem_slug'] ?? '')->firstOrFail(),
                    $templateId,
                    $countryCode,
                    $language,
                    $params['created_by'] ?? null,
                    $params,
                    $shell,
                ),
                'lawyers'  => $this->generateLawyerLanding($templateId, $countryCode, $language, $params['created_by'] ?? null, $params, $shell),
                'helpers'  => $this->generateHelperLanding($templateId, $countryCode, $language, $params['created_by'] ?? null, $params, $shell),
                'matching' => $this->generateMatchingLanding($templateId, $countryCode, $language, $params['created_by'] ?? null, $params, $shell),
                'category_pillar' => $this->generateCategoryPillarLanding(
                    $params['category_slug'] ?? ($params['problem_slug'] ?? 'general'),
                    $templateId, $countryCode, $language, $params['created_by'] ?? null, $params, $shell,
                ),
                'profile' => $this->generateProfileLanding(
                    $params['user_profile'] ?? ($params['problem_slug'] ?? 'expatrie'),
                    $templateId, $countryCode, $language, $params['created_by'] ?? null, $params, $shell,
                ),
                'emergency' => $this->generateEmergencyLanding(
                    $templateId, $countryCode, $language, $params['created_by'] ?? null, $params, $shell,
                ),
                'nationality' => $this->generateNationalityLanding(
                    $params['origin_nationality'] ?? strtoupper($params['problem_slug'] ?? 'FR'),
                    $templateId, $countryCode, $language, $params['created_by'] ?? null, $params, $shell,
                ),
                default    => throw new \InvalidArgumentException("audience_type invalide: {$audienceType}"),
            };
        } catch (\Throwable $e) {
            // Nettoie le shell orphelin si la génération échoue, sinon la dédup par
            // (audience_type, template_id, country_code, language) bloquerait les retries.
            $shell->forceDelete();
            throw $e;
        }
    }

    // ============================================================
    // Shell + AI wrappers avec cost tracking
    // ============================================================

    /**
     * Crée une LP "coquille" avec un slug temporaire, avant les appels IA.
     * Permet aux ApiCost créés pendant la génération d'être rattachés via costable_id.
     * Le slug + contenu final seront injectés par saveLandingPage() (UPDATE).
     */
    private function createLandingShell(array $params): LandingPage
    {
        return LandingPage::create([
            'audience_type'      => $params['audience_type'],
            'template_id'        => $params['template_id'],
            'country_code'       => $params['country_code'],
            'language'           => $params['language'] ?? 'fr',
            'problem_id'         => $params['problem_slug']       ?? null,
            'category_slug'      => $params['category_slug']      ?? null,
            'user_profile'       => $params['user_profile']       ?? null,
            'origin_nationality' => $params['origin_nationality'] ?? null,
            'generation_source'  => 'ai_generated',
            'parent_id'          => $params['parent_id'] ?? null,
            'created_by'         => $params['created_by'] ?? null,
            'slug'               => 'tmp-' . (string) Str::uuid(),
            'title'              => '[generating]',
            'sections'           => [],
            'seo_score'          => 0,
            'status'             => 'generating',
        ]);
    }

    /**
     * Wrapper OpenAI qui injecte costable_type/costable_id pour rattacher
     * l'ApiCost créé à la LandingPage en cours de génération.
     */
    private function openAiWithCost(
        string $systemPrompt,
        string $userPrompt,
        array $options,
        ?LandingPage $shell,
    ): array {
        if ($shell !== null) {
            $options['costable_type'] = LandingPage::class;
            $options['costable_id']   = $shell->id;
        }
        return $this->openAi->complete($systemPrompt, $userPrompt, $options);
    }

    /**
     * Wrapper Claude (fallback) qui injecte costable_type/costable_id et
     * retourne le tableau complet (corrige le bug qui aplatissait le retour).
     */
    private function claudeWithCost(
        string $systemPrompt,
        string $userPrompt,
        array $options,
        ?LandingPage $shell,
    ): array {
        if ($shell !== null) {
            $options['costable_type'] = LandingPage::class;
            $options['costable_id']   = $shell->id;
        }
        return $this->claude->complete($systemPrompt, $userPrompt, $options);
    }

    // ============================================================
    // Import manuel (Claude Opus 4.7 via chat, bypass LLM API)
    // ============================================================

    /**
     * Importe une LP dont le contenu JSON a été produit hors-pipeline
     * (ex: Claude Opus 4.7 via chat Max). Bypass les appels OpenAI/Claude
     * mais applique TOUT le reste : slug ASCII, JSON-LD, hreflang, UTM,
     * Unsplash, geo fields, internal_links, dédup, soft-delete sur échec.
     *
     * @param array $params audience_type, template_id, country_code, language,
     *                      + problem_slug|category_slug|user_profile|origin_nationality
     *                      + created_by?, parent_id?
     * @param array $parsed Contenu JSON (title, sections, meta_*, keywords_*, cta_links, lsi_keywords, internal_links, url_slug?)
     */
    public function importManual(array $params, array $parsed): LandingPage
    {
        // force_update=true → les appels à saveLandingPage hydrateront l'existante
        // au lieu de la skip. Activé par landings:import --force-update=true.
        $forceUpdate = ! empty($params['force_update']);

        // Si on force l'update, on skip le shell (évite de créer une coquille
        // dupliquée). On passe le flag directement dans $params pour saveLandingPage.
        $shell = $forceUpdate ? null : $this->createLandingShell($params);

        try {
            $audienceType = $params['audience_type'];
            $templateId   = $params['template_id'];
            $countryCode  = $params['country_code'];
            $language     = $params['language'] ?? 'fr';
            $countryName  = $this->getCountryName($countryCode, $language);
            $countrySlug  = $this->getCountrySlug($countryCode, $language);

            $baseData = [
                'audience_type'     => $audienceType,
                'template_id'       => $templateId,
                'country_code'      => $countryCode,
                'language'          => $language,
                'country'           => $countryName,
                'generation_source' => 'manual',
                'generation_params' => array_merge(
                    [
                        'via'           => 'claude_max_chat',
                        'audience_type' => $audienceType,
                        'template_id'   => $templateId,
                        'language'      => $language,
                        'country_code'  => $countryCode,
                    ],
                    array_intersect_key($params, array_flip([
                        'problem_slug', 'category_slug', 'user_profile', 'origin_nationality',
                    ])),
                ),
                'parent_id'  => $params['parent_id'] ?? null,
                'created_by' => $params['created_by'] ?? null,
            ];

            // Champs spécifiques par audience + clé utilisée pour buildSlug
            $slugKey = null;
            switch ($audienceType) {
                case 'clients':
                    $baseData['problem_id'] = $params['problem_slug'] ?? null;
                    $slugKey = $params['problem_slug'] ?? null;
                    break;
                case 'category_pillar':
                    $baseData['category_slug'] = $params['category_slug'] ?? null;
                    $baseData['problem_id']    = $params['category_slug'] ?? null;
                    $slugKey = $params['category_slug'] ?? null;
                    break;
                case 'profile':
                    $baseData['user_profile'] = $params['user_profile'] ?? null;
                    $baseData['problem_id']   = $params['user_profile'] ?? null;
                    $slugKey = isset($params['user_profile'])
                        ? str_replace('_', '-', $params['user_profile'])
                        : null;
                    break;
                case 'nationality':
                    $baseData['origin_nationality'] = $params['origin_nationality'] ?? null;
                    $baseData['problem_id']         = isset($params['origin_nationality'])
                        ? strtolower($params['origin_nationality'])
                        : null;
                    $slugKey = isset($params['origin_nationality'])
                        ? $this->getNationalitySlug($params['origin_nationality'], $language)
                        : null;
                    break;
            }

            $slug = $this->buildSlug(
                $audienceType,
                $language,
                $countrySlug,
                $slugKey,
                $templateId,
                $parsed['url_slug'] ?? null,
            );

            return $this->saveLandingPage(
                $baseData,
                $parsed,
                $slug,
                $countryName,
                $countryCode,
                $audienceType,
                $params,
                $shell,
            );
        } catch (\Throwable $e) {
            $shell?->forceDelete();
            throw $e;
        }
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
        array $params = [],
        ?LandingPage $shell = null,
    ): LandingPage {
        $template    = self::TEMPLATES['clients'][$templateId] ?? self::TEMPLATES['clients']['seo'];
        $countryName = $this->getCountryName($countryCode, $language);
        $countrySlug = $this->getCountrySlug($countryCode, $language);

        $systemPrompt = $this->buildSystemPrompt('clients', $countryCode, $countryName, $language);

        // ── Préparer le contexte enrichi du problème ────────────────
        $searchQueriesList = 'Non disponible';
        if (!empty($problem->search_queries_seed)) {
            $queries = is_array($problem->search_queries_seed)
                ? $problem->search_queries_seed
                : (json_decode($problem->search_queries_seed, true) ?? []);
            if (!empty($queries)) {
                $searchQueriesList = '• ' . implode("\n        • ", array_slice($queries, 0, 5));
            }
        }

        $faqSeedStr = 'Non disponible';
        if (!empty($problem->faq_seed)) {
            $faq = is_array($problem->faq_seed)
                ? $problem->faq_seed
                : (json_decode($problem->faq_seed, true) ?? []);
            $faqSeedStr = !empty($faq) ? json_encode($faq, JSON_UNESCAPED_UNICODE) : 'Non disponible';
        }

        $urgencyLabel = match(true) {
            $problem->urgency_score >= 8 => "TRÈS URGENT ({$problem->urgency_score}/10) → langage d'urgence, \"maintenant\", FOMO, verbes au présent actif",
            $problem->urgency_score >= 5 => "MODÉRÉ ({$problem->urgency_score}/10) → mélange informatif + réassurance + CTA direct",
            default                      => "INFORMATIF ({$problem->urgency_score}/10) → pédagogique, exhaustif, sans pression excessive",
        };

        $expertTypes = [];
        if ($problem->needs_lawyer) $expertTypes[] = 'avocat partenaire SOS-Expat';
        if ($problem->needs_helper) $expertTypes[] = 'expatrié aidant SOS-Expat';
        $expertTypeStr = implode(' ou ', $expertTypes ?: ['expert SOS-Expat']);

        $userPrompt = <<<PROMPT
        TÂCHE: Générer une landing page haute-conversion en {$language} pour SOS-Expat.
        Répondre UNIQUEMENT en JSON valide. TOUT le contenu en {$language}.

        ══════════════════════════════════════════════════════
        PROBLÈME À RÉSOUDRE
        ══════════════════════════════════════════════════════
        Titre du problème : {$problem->title}
        Angle LP          : {$problem->lp_angle}
        Catégorie         : {$problem->category}
        Urgence           : {$urgencyLabel}
        Business value    : {$problem->business_value}
        Expert à connecter: {$expertTypeStr}

        ══════════════════════════════════════════════════════
        CIBLAGE GÉOGRAPHIQUE
        ══════════════════════════════════════════════════════
        Pays : {$countryName} ({$countryCode})
        Langue OBLIGATOIRE: {$language} — TOUT le contenu dans cette langue, SANS EXCEPTION

        ══════════════════════════════════════════════════════
        REQUÊTES GOOGLE CIBLES → H1 ET META_TITLE DOIVENT Y RÉPONDRE
        ══════════════════════════════════════════════════════
        {$searchQueriesList}

        ══════════════════════════════════════════════════════
        TEMPLATE & TON
        ══════════════════════════════════════════════════════
        Template : {$template['label']} | Ton : {$template['tone']}
        Intent   : {$template['intent']}
        CTA cible: "{$template['cta_primary']}" (TRADUIRE en {$language} si nécessaire)

        ══════════════════════════════════════════════════════
        FAQ SEEDS (à enrichir, adapter au pays, Answer-First)
        ══════════════════════════════════════════════════════
        {$faqSeedStr}

        ══════════════════════════════════════════════════════
        SECTIONS À GÉNÉRER (ordre exact)
        ══════════════════════════════════════════════════════
        {$this->formatSections($template['sections'])}

        RÈGLES PAR SECTION:
        • hero      → badge (≤10 mots, chiffres ou stats), h1 (5-9 MOTS MAX, keyword dès le 1er mot),
                      subtitle (15-25 mots, amplifie H1, "pourquoi SOS-Expat"),
                      cta_text (3-5 mots, verbe d'action + bénéfice, EN {$language}),
                      cta_subtext (≤12 mots, lever le frein principal, EN {$language})
        • trust_signals → 4-5 items, chiffres précis, max 7 mots chacun, EN {$language}
        • guide_steps   → headline H2 "People Also Ask", 3-5 étapes, verbe impératif, 15-30 mots/étape
        • local_info    → données réelles/vraisemblables pour {$countryName} (ambassade, num urgence local, conseil pratique)
        • faq           → headline H2, MINIMUM 5 questions recherche vocale, réponses Answer-First 40-60 mots
        • why_us/trust  → arguments spécifiques SOS-Expat vs alternatives (avocats traditionnels, forums)
        • cta           → headline ≤10 mots + button 3-5 mots (EN {$language}) + subtext ≤12 mots

        ══════════════════════════════════════════════════════
        STRUCTURE JSON (répondre avec ce schéma, tout en {$language})
        ══════════════════════════════════════════════════════
        {
          "title": "...(50-70 chars, keyword principal + pays)",
          "url_slug": "...(ASCII kebab-case slug EN {$language}, ex FR: visa-refuse-dossier-incomplet, EN: visa-refused-incomplete-file)",
          "keywords_primary": "...(keyword exact comme tapé sur Google, en {$language})",
          "keywords_secondary": ["...", "...", "..."],
          "sections": [
            {"type": "hero", "content": {"badge": "★ 4.9/5 · ...", "h1": "...", "subtitle": "...", "cta_text": "...", "cta_subtext": "..."}},
            {"type": "trust_signals", "content": {"items": [{"icon": "⚡", "text": "..."}]}},
            {"type": "guide_steps", "content": {"headline": "...", "steps": [{"num": 1, "title": "...", "text": "..."}]}},
            {"type": "local_info", "content": {"headline": "...", "embassy": "...", "emergency_number": "...", "tip": "...", "local_fact": "..."}},
            {"type": "faq", "content": {"headline": "...", "items": [{"q": "...", "a": "..."}]}},
            {"type": "cta", "content": {"headline": "...", "button": "...", "subtext": "..."}}
          ],
          "meta_title": "...(EXACTEMENT 55-60 chars — keyword + pays + SOS-Expat, EN {$language})",
          "meta_description": "...(EXACTEMENT 148-155 chars — verbe d'action + bénéfice + pays, EN {$language})",
          "cta_links": [
            {"label": "...(3-5 mots EN {$language})", "url": "#contact", "style": "primary", "position": "hero"},
            {"label": "...(3-5 mots EN {$language})", "url": "#contact", "style": "secondary", "position": "footer"}
          ],
          "lsi_keywords": ["...", "...", "...(8-12 termes sémantiquement liés au keyword principal, EN {$language})"],
          "internal_links": [
            {"anchor": "...(3-6 mots descriptifs EN {$language})", "topic": "sujet de la page cible SOS-Expat"},
            {"anchor": "...", "topic": "..."}
          ]
        }
        PROMPT;

        $model       = ($params['use_cheap_model'] ?? false) ? 'gpt-4o-mini' : 'gpt-4o';
        $temperature = ($params['use_cheap_model'] ?? false) ? 0.4 : 0.7;
        $result      = $this->openAiWithCost($systemPrompt, $userPrompt, [
            'model'       => $model,
            'max_tokens'  => 4500,
            'json_mode'   => true,
            'temperature' => $temperature,
        ], $shell);

        // Fallback Claude si OpenAI échoue
        if (empty($result['content'])) {
            Log::warning('LandingGenerationService: OpenAI failed, fallback to Claude', ['audience' => 'clients']);
            $result = $this->claudeWithCost($systemPrompt, $userPrompt, ['model' => 'claude-sonnet-4-6', 'max_tokens' => 4000], $shell);
        }

        $response = $result['content'] ?? '';

        $parsed = $this->parseResponse($response);
        $slug   = $this->buildSlug('clients', $language, $countrySlug, $problem->slug, $templateId, $parsed['url_slug'] ?? null);

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
            'parent_id'          => $params['parent_id'] ?? null,
            'created_by'         => $createdBy,
        ], $parsed, $slug, $countryName, $countryCode, 'clients', $params, $shell);
    }

    private function generateLawyerLanding(
        string $templateId,
        string $countryCode,
        string $language,
        ?int $createdBy,
        array $params = [],
        ?LandingPage $shell = null,
    ): LandingPage {
        $template    = self::TEMPLATES['lawyers'][$templateId] ?? self::TEMPLATES['lawyers']['general'];
        $countryName = $this->getCountryName($countryCode, $language);
        $countrySlug = $this->getCountrySlug($countryCode, $language);

        $systemPrompt = $this->buildSystemPrompt('lawyers', $countryCode, $countryName, $language);

        $angle = str_replace('{country}', $countryName, $template['angle']);

        $userPrompt = <<<PROMPT
        TÂCHE: Générer une landing page de recrutement d'avocats partenaires en {$language} pour SOS-Expat.
        Répondre UNIQUEMENT en JSON valide. TOUT le contenu en {$language}.

        ══════════════════════════════════════════════════════
        CONTEXTE PRODUIT — CE QU'ON OFFRE AUX AVOCATS
        ══════════════════════════════════════════════════════
        • 30€ par consultation de 20 minutes (tarif fixe, transparent)
        • Paiement garanti sous 24h après chaque appel — zéro retard, zéro impayé
        • Zéro prospection: SOS-Expat envoie les clients directement sur leur profil
        • Liberté totale des horaires: l'avocat active/désactive son profil quand il veut
        • Clientèle internationale qualifiée: expatriés et voyageurs à {$countryName}
        • Inscription et activation en moins de 5 minutes, sans engagement

        ══════════════════════════════════════════════════════
        ANGLE & TEMPLATE
        ══════════════════════════════════════════════════════
        Angle     : {$angle}
        Template  : {$template['label']}
        Ton       : {$template['tone']}
        Pays      : {$countryName} ({$countryCode})
        Langue OBLIGATOIRE: {$language} — TOUT en {$language}, SANS EXCEPTION

        ══════════════════════════════════════════════════════
        ANGLE COMPÉTITIF (à intégrer subtilement)
        ══════════════════════════════════════════════════════
        Vs publicité/SEO traditionnel: coûts fixes élevés, résultats incertains, délais longs
        Vs bouche-à-oreille: aléatoire, limité géographiquement
        SOS-Expat: clients préqualifiés, paiement instantané, flux continu sans effort

        ══════════════════════════════════════════════════════
        SECTIONS À GÉNÉRER (ordre exact)
        ══════════════════════════════════════════════════════
        {$this->formatSections($template['sections'])}

        RÈGLES PAR SECTION:
        • hero          → badge (≤10 mots, ex: "★ Déjà 500+ avocats partenaires"), h1 (5-9 mots, bénéfice principal),
                          subtitle (15-25 mots, clarifier l'opportunité), cta_text (3-5 mots EN {$language}),
                          cta_subtext (≤12 mots, lever le frein: "Sans engagement · Activation immédiate")
        • earnings      → headline H2, montant exact "30€", délai paiement "24h", 3-4 badges financiers précis
                          (ex: "Paiement chaque consultation", "Zéro impayé", "Cumul illimité")
        • freedom       → headline H2, 4-5 items liberté avec chiffres (ex: "0 heure minimum exigée")
        • process       → headline H2, exactement 4 étapes: Inscription → Profil → Appels → Paiement
        • client_quality→ headline H2, 3-4 items sur la qualité des clients (internationaux, qualifiés, préparés)
        • faq           → headline H2, MINIMUM 5 questions que se pose un avocat hésitant (Answer-First, 40-60 mots)
        • cta           → headline ≤10 mots + button 3-5 mots (EN {$language}) + subtext ≤12 mots

        ══════════════════════════════════════════════════════
        STRUCTURE JSON (tout en {$language})
        ══════════════════════════════════════════════════════
        {
          "title": "...(50-70 chars, keyword + pays)",
          "url_slug": "...(ASCII kebab-case EN {$language}, ex FR: devenir-avocat-partenaire, EN: become-lawyer-partner)",
          "keywords_primary": "...(keyword comme tapé sur Google, en {$language})",
          "keywords_secondary": ["...", "...", "..."],
          "sections": [
            {"type": "hero", "content": {"badge": "★ ...", "h1": "...", "subtitle": "...", "cta_text": "...", "cta_subtext": "..."}},
            {"type": "earnings", "content": {"headline": "...", "amount": "30€", "per": "...", "payment_delay": "...", "badges": ["...","...","..."]}},
            {"type": "freedom", "content": {"headline": "...", "items": [{"icon": "✓", "text": "..."}]}},
            {"type": "process", "content": {"headline": "...", "steps": [{"num": 1, "label": "...", "detail": "..."}]}},
            {"type": "faq", "content": {"headline": "...", "items": [{"q": "...", "a": "..."}]}},
            {"type": "cta", "content": {"headline": "...", "button": "...", "subtext": "..."}}
          ],
          "meta_title": "...(EXACTEMENT 55-60 chars, EN {$language})",
          "meta_description": "...(EXACTEMENT 148-155 chars, EN {$language})",
          "cta_links": [
            {"label": "...(3-5 mots EN {$language})", "url": "#inscription", "style": "primary", "position": "hero"}
          ],
          "lsi_keywords": ["...", "...", "...(8-12 termes sémantiquement liés, EN {$language})"],
          "internal_links": [
            {"anchor": "...(3-6 mots descriptifs EN {$language})", "topic": "sujet page SOS-Expat liée"},
            {"anchor": "...", "topic": "..."}
          ]
        }
        PROMPT;

        $model       = ($params['use_cheap_model'] ?? false) ? 'gpt-4o-mini' : 'gpt-4o';
        $temperature = ($params['use_cheap_model'] ?? false) ? 0.4 : 0.7;
        $result      = $this->openAiWithCost($systemPrompt, $userPrompt, [
            'model'       => $model,
            'max_tokens'  => 3500,
            'json_mode'   => true,
            'temperature' => $temperature,
        ], $shell);

        // Fallback Claude si OpenAI échoue
        if (empty($result['content'])) {
            Log::warning('LandingGenerationService: OpenAI failed, fallback to Claude', ['audience' => 'lawyers']);
            $result = $this->claudeWithCost($systemPrompt, $userPrompt, ['model' => 'claude-sonnet-4-6', 'max_tokens' => 3000], $shell);
        }

        $response = $result['content'] ?? '';

        $parsed = $this->parseResponse($response);
        $slug   = $this->buildSlug('lawyers', $language, $countrySlug, null, $templateId, null);

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
            'parent_id'         => $params['parent_id'] ?? null,
            'created_by'        => $createdBy,
        ], $parsed, $slug, $countryName, $countryCode, 'lawyers', $params, $shell);
    }

    private function generateHelperLanding(
        string $templateId,
        string $countryCode,
        string $language,
        ?int $createdBy,
        array $params = [],
        ?LandingPage $shell = null,
    ): LandingPage {
        $template    = self::TEMPLATES['helpers'][$templateId] ?? self::TEMPLATES['helpers']['recruitment'];
        $countryName = $this->getCountryName($countryCode, $language);
        $countrySlug = $this->getCountrySlug($countryCode, $language);

        $systemPrompt = $this->buildSystemPrompt('helpers', $countryCode, $countryName, $language);

        $angle = str_replace('{country}', $countryName, $template['angle']);

        $userPrompt = <<<PROMPT
        TÂCHE: Générer une landing page de recrutement d'expatriés aidants en {$language} pour SOS-Expat.
        Répondre UNIQUEMENT en JSON valide. TOUT le contenu en {$language}.

        ══════════════════════════════════════════════════════
        PROFIL CIBLE — QUI EST L'EXPATRIÉ AIDANT ?
        ══════════════════════════════════════════════════════
        • Déjà installé à {$countryName} depuis au moins 6-12 mois
        • Connaît les démarches locales (logement, visa, banque, sécurité sociale, admin)
        • Veut aider les nouveaux arrivants comme il aurait voulu être aidé lui-même
        • AIDE PRATIQUE UNIQUEMENT: logement, intégration, administration, vie quotidienne — PAS juridique
        • Peut répondre depuis son smartphone, à n'importe quelle heure selon ses disponibilités

        ══════════════════════════════════════════════════════
        CE QU'ON LUI OFFRE
        ══════════════════════════════════════════════════════
        • 10€ par appel d'assistance de 20 minutes
        • Paiement automatique après chaque appel
        • Liberté totale: activer/désactiver son profil à tout moment, zéro engagement
        • Impact réel: aider des expatriés dans une situation difficile, comme lui l'a vécu
        • Communauté: faire partie du réseau entraide SOS-Expat à {$countryName}

        ══════════════════════════════════════════════════════
        ANGLE & TEMPLATE
        ══════════════════════════════════════════════════════
        Angle    : {$angle}
        Template : {$template['label']}
        Ton      : {$template['tone']}
        Pays     : {$countryName} ({$countryCode})
        Langue OBLIGATOIRE: {$language} — TOUT en {$language}, SANS EXCEPTION

        ══════════════════════════════════════════════════════
        SECTIONS À GÉNÉRER (ordre exact)
        ══════════════════════════════════════════════════════
        {$this->formatSections($template['sections'])}

        RÈGLES PAR SECTION:
        • hero           → badge (ex: "★ Déjà 200+ expatriés aidants à {$countryName}"), h1 (5-9 mots, angle communautaire/opportunité),
                           subtitle (15-25 mots, "valoriser son expérience"), cta_text (3-5 mots EN {$language}),
                           cta_subtext (≤12 mots, lever le frein: "Zéro engagement · 100% flexible")
        • what_you_do    → headline H2, 4-5 exemples concrets d'aide fournie (logement, banque, admin, orientation, réseau)
        • earnings       → headline H2, "10€", délai paiement, 3-4 badges (ex: "Paiement instantané", "Cumul illimité", "Depuis son téléphone")
        • community_proof→ headline H2, témoignages/stats communauté, sentiment d'appartenance
        • no_pressure    → headline H2, 4-5 items liberté (zéro quota, zéro horaire fixe, zéro contrat, pause quand on veut)
        • process        → headline H2, 4 étapes: Inscription → Profil → Appels entrants → Paiement
        • faq            → headline H2, MINIMUM 5 questions qu'un expatrié hésitant se pose (Answer-First, 40-60 mots)
        • cta            → headline ≤10 mots + button 3-5 mots (EN {$language}) + subtext ≤12 mots

        ══════════════════════════════════════════════════════
        STRUCTURE JSON (tout en {$language})
        ══════════════════════════════════════════════════════
        {
          "title": "...(50-70 chars, keyword + pays)",
          "url_slug": "...(ASCII kebab-case EN {$language}, ex FR: devenir-expat-aidant, EN: become-expat-helper)",
          "keywords_primary": "...(keyword comme tapé sur Google, en {$language})",
          "keywords_secondary": ["...", "...", "..."],
          "sections": [
            {"type": "hero", "content": {"badge": "★ ...", "h1": "...", "subtitle": "...", "cta_text": "...", "cta_subtext": "..."}},
            {"type": "what_you_do", "content": {"headline": "...", "items": [{"icon": "✓", "text": "..."}]}},
            {"type": "earnings", "content": {"headline": "...", "amount": "10€", "per": "...", "payment_delay": "...", "badges": ["...","..."]}},
            {"type": "process", "content": {"headline": "...", "steps": [{"num": 1, "label": "...", "detail": "..."}]}},
            {"type": "faq", "content": {"headline": "...", "items": [{"q": "...", "a": "..."}]}},
            {"type": "cta", "content": {"headline": "...", "button": "...", "subtext": "..."}}
          ],
          "meta_title": "...(EXACTEMENT 55-60 chars, EN {$language})",
          "meta_description": "...(EXACTEMENT 148-155 chars, EN {$language})",
          "cta_links": [
            {"label": "...(3-5 mots EN {$language})", "url": "#inscription", "style": "primary", "position": "hero"}
          ],
          "lsi_keywords": ["...", "...", "...(8-12 termes sémantiquement liés, EN {$language})"],
          "internal_links": [
            {"anchor": "...(3-6 mots descriptifs EN {$language})", "topic": "sujet page SOS-Expat liée"},
            {"anchor": "...", "topic": "..."}
          ]
        }
        PROMPT;

        $model       = ($params['use_cheap_model'] ?? false) ? 'gpt-4o-mini' : 'gpt-4o';
        $temperature = ($params['use_cheap_model'] ?? false) ? 0.4 : 0.7;
        $result      = $this->openAiWithCost($systemPrompt, $userPrompt, [
            'model'       => $model,
            'max_tokens'  => 3500,
            'json_mode'   => true,
            'temperature' => $temperature,
        ], $shell);

        // Fallback Claude si OpenAI échoue
        if (empty($result['content'])) {
            Log::warning('LandingGenerationService: OpenAI failed, fallback to Claude', ['audience' => 'helpers']);
            $result = $this->claudeWithCost($systemPrompt, $userPrompt, ['model' => 'claude-sonnet-4-6', 'max_tokens' => 3000], $shell);
        }

        $response = $result['content'] ?? '';

        $parsed = $this->parseResponse($response);
        $slug   = $this->buildSlug('helpers', $language, $countrySlug, null, $templateId, null);

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
            'parent_id'         => $params['parent_id'] ?? null,
            'created_by'        => $createdBy,
        ], $parsed, $slug, $countryName, $countryCode, 'helpers', $params, $shell);
    }

    private function generateMatchingLanding(
        string $templateId,
        string $countryCode,
        string $language,
        ?int $createdBy,
        array $params = [],
        ?LandingPage $shell = null,
    ): LandingPage {
        $template    = self::TEMPLATES['matching'][$templateId] ?? self::TEMPLATES['matching']['expert'];
        $countryName = $this->getCountryName($countryCode, $language);
        $countrySlug = $this->getCountrySlug($countryCode, $language);

        $systemPrompt = $this->buildSystemPrompt('matching', $countryCode, $countryName, $language);

        $angle = str_replace('{country}', $countryName, $template['angle']);

        // Type d'expert selon le template
        $expertLabel = match ($templateId) {
            'lawyer' => 'avocat',
            'helper' => 'expatrié aidant',
            default  => 'expert',
        };

        $userPrompt = <<<PROMPT
        TÂCHE: Générer une landing page de conversion directe ultra-percutante en {$language} pour SOS-Expat.
        Répondre UNIQUEMENT en JSON valide. TOUT le contenu en {$language}.
        OBJECTIF: Faire cliquer le visiteur dès les 5 premières secondes. Page courte, CTA unique, friction zéro.

        ══════════════════════════════════════════════════════
        TYPE DE PAGE & CONTEXTE
        ══════════════════════════════════════════════════════
        Type d'expert    : {$template['label']} ({$expertLabel})
        Angle            : {$angle}
        Ton              : {$template['tone']}
        Pays             : {$countryName} ({$countryCode})
        Langue OBLIGATOIRE: {$language} — TOUT en {$language}, SANS EXCEPTION

        ══════════════════════════════════════════════════════
        USPs SOS-EXPAT (À INTÉGRER DANS LES TRUST SIGNALS)
        ══════════════════════════════════════════════════════
        • Réponse en moins de 5 minutes — disponible 24h/24, 7j/7
        • Prix fixe et transparent — aucune surprise, aucun dépassement
        • Expert local à {$countryName} — connaît les lois, la culture, les démarches locales
        • 100% confidentiel — confidentialité garantie
        • Sans rendez-vous — connexion immédiate, pas d'attente
        • Déjà 10,000+ expatriés aidés dans 50+ pays

        ══════════════════════════════════════════════════════
        PSYCHOLOGIE DE CONVERSION (PRINCIPES À APPLIQUER)
        ══════════════════════════════════════════════════════
        • URGENCE IMPLICITE: le visiteur a un problème maintenant → la solution est disponible maintenant
        • RÉDUCTION DU RISQUE: prix fixe + sans engagement + confidentialité → lever tous les freins
        • PREUVE SOCIALE CHIFFRÉE: chiffres précis qui crédibilisent
        • CLARTÉ ABSOLUE: en 5 secondes le visiteur sait CE QU'IL OBTIENT et COMMENT

        ══════════════════════════════════════════════════════
        SECTIONS À GÉNÉRER (ordre exact — PAGE COURTE)
        ══════════════════════════════════════════════════════
        {$this->formatSections($template['sections'])}

        RÈGLES PAR SECTION:
        • hero          → badge (★ + chiffre de confiance, ≤10 mots), h1 (5-8 MOTS MAX, bénéfice immédiat + pays),
                          subtitle (12-20 mots, éliminer le doute principal du visiteur),
                          cta_text (3-4 mots, verbe d'action fort EN {$language}),
                          cta_subtext (≤10 mots, les 2 freins principaux levés, EN {$language})
        • trust_signals → 4-5 items UNIQUEMENT FACTUELS, chiffres précis, max 6 mots chacun,
                          couvrir: vitesse · prix · disponibilité · confidentialité · volume
        • lawyer_advantages / helper_advantages →
                          headline H2 (question PAA), 4 arguments différenciants vs alternatives locales
        • cta           → headline urgence ≤10 mots + button 3-4 mots (verbe fort, EN {$language}) +
                          subtext ≤10 mots (les 2 dernières objections levées)

        ══════════════════════════════════════════════════════
        STRUCTURE JSON (tout en {$language})
        ══════════════════════════════════════════════════════
        {
          "title": "...(50-65 chars, keyword + pays, très direct)",
          "url_slug": "...(ASCII kebab-case EN {$language}, ex FR: expert-expatrie-thaïlande→expert-expatrie-thailande, EN: expat-expert-thailand)",
          "keywords_primary": "...(keyword comme tapé sur Google, en {$language})",
          "keywords_secondary": ["...", "...", "..."],
          "sections": [
            {"type": "hero", "content": {"badge": "★ ...", "h1": "...", "subtitle": "...", "cta_text": "...", "cta_subtext": "..."}},
            {"type": "trust_signals", "content": {"items": [{"icon": "⚡", "text": "..."}, {"icon": "💰", "text": "..."}, {"icon": "🔒", "text": "..."}, {"icon": "⏱", "text": "..."}]}},
            {"type": "cta", "content": {"headline": "...", "button": "...", "subtext": "..."}}
          ],
          "meta_title": "...(EXACTEMENT 55-60 chars, EN {$language}, très orienté conversion)",
          "meta_description": "...(EXACTEMENT 148-155 chars, EN {$language}, réponse directe + bénéfice + urgence)",
          "cta_links": [
            {"label": "...(3-4 mots EN {$language}, verbe fort)", "url": "#contact", "style": "primary", "position": "hero"}
          ],
          "lsi_keywords": ["...", "...", "...(8-12 termes sémantiquement liés, EN {$language})"],
          "internal_links": [
            {"anchor": "...(3-6 mots EN {$language})", "topic": "sujet page SOS-Expat liée"},
            {"anchor": "...", "topic": "..."}
          ]
        }
        PROMPT;

        $model       = ($params['use_cheap_model'] ?? false) ? 'gpt-4o-mini' : 'gpt-4o';
        $temperature = ($params['use_cheap_model'] ?? false) ? 0.4 : 0.65;
        $result      = $this->openAiWithCost($systemPrompt, $userPrompt, [
            'model'       => $model,
            'max_tokens'  => 2500,
            'json_mode'   => true,
            'temperature' => $temperature,
        ], $shell);

        // Fallback Claude si OpenAI échoue
        if (empty($result['content'])) {
            Log::warning('LandingGenerationService: OpenAI failed, fallback to Claude', ['audience' => 'matching']);
            $result = $this->claudeWithCost($systemPrompt, $userPrompt, ['model' => 'claude-sonnet-4-6', 'max_tokens' => 2000], $shell);
        }

        $response = $result['content'] ?? '';

        $parsed = $this->parseResponse($response);
        $slug   = $this->buildSlug('matching', $language, $countrySlug, null, $templateId, null);

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
            'parent_id'         => $params['parent_id'] ?? null,
            'created_by'        => $createdBy,
        ], $parsed, $slug, $countryName, $countryCode, 'matching', $params, $shell);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Nouveaux types 2026 — Piliers, Profils, Urgences, Nationalités
    // ──────────────────────────────────────────────────────────────────────────

    private function generateCategoryPillarLanding(
        string $categorySlug,
        string $templateId,
        string $countryCode,
        string $language,
        ?int $createdBy,
        array $params = [],
        ?LandingPage $shell = null,
    ): LandingPage {
        $template    = self::TEMPLATES['category_pillar'][$templateId] ?? self::TEMPLATES['category_pillar']['overview'];
        $countryName = $this->getCountryName($countryCode, $language);
        $countrySlug = $this->getCountrySlug($countryCode, $language);

        // Libellé de catégorie dans la langue cible
        $categoryLabel = $this->getCategoryLabel($categorySlug, $language);

        $systemPrompt = $this->buildSystemPrompt('category_pillar', $countryCode, $countryName, $language);

        $userPrompt = <<<PROMPT
        TÂCHE: Générer une landing page PILIER thématique exhaustive en {$language} pour SOS-Expat.
        Répondre UNIQUEMENT en JSON valide. TOUT le contenu en {$language}.

        ══════════════════════════════════════════════════════
        CONTEXTE — PAGE PILIER
        ══════════════════════════════════════════════════════
        Catégorie    : {$categoryLabel} (slug: {$categorySlug})
        Pays cible   : {$countryName} ({$countryCode})
        Template     : {$template['label']}
        Ton          : {$template['tone']}
        Langue OBLIGATOIRE : {$language} — TOUT en {$language}, SANS EXCEPTION

        OBJECTIF SEO: Se positionner sur les requêtes HEAD de la catégorie "{$categoryLabel}"
        à {$countryName}. Ex: "problèmes {$categoryLabel} expatriés {$countryName}",
        "{$categoryLabel} {$countryName} conseils", etc.
        → Cette page DOIT être la référence absolue sur ce sujet pour ce pays.

        ══════════════════════════════════════════════════════
        CONTENU REQUIS — EXHAUSTIVITÉ PILIER
        ══════════════════════════════════════════════════════
        • Vue d'ensemble: Les 5-8 sous-problèmes principaux de la catégorie à {$countryName}
        • Spécificités locales: Ce qui est DIFFÉRENT à {$countryName} vs d'autres pays
        • Guide étapes: Processus concret (5-7 étapes) pour résoudre les problèmes de cette catégorie
        • FAQ longue (7-10 questions): Vraies recherches Google, Answer-First, 50-70 mots/réponse
        • Données locales: Chiffres officiels, délais réels, coûts typiques à {$countryName}
        • CTA: "Parler à un expert {$categoryLabel} à {$countryName} maintenant"

        ══════════════════════════════════════════════════════
        SECTIONS À GÉNÉRER (ordre exact)
        ══════════════════════════════════════════════════════
        {$this->formatSections($template['sections'])}

        RÈGLES PAR SECTION:
        • hero          → badge (★ chiffre fiable), h1 (5-9 mots: keyword catégorie + pays),
                          subtitle (15-25 mots, bénéfice principal), cta_text (3-5 mots EN {$language}),
                          cta_subtext (≤12 mots, friction killer)
        • guide_steps   → headline H2 (reformulation PAA), 5-7 étapes concrètes (verbe impératif, 20-35 mots)
        • faq           → headline H2, 7-10 questions ultra-spécifiques à {$countryName} + catégorie,
                          Answer-First, 50-70 mots/réponse
        • local_info    → headline H2, 4-5 infos RÉELLES sur {$countryName}: contacts officiels,
                          délais typiques, coûts, numéros utiles, ressources en ligne
        • trust_signals → 4-5 preuves sociales SOS-Expat avec chiffres précis
        • cta           → headline urgence ≤10 mots + button 3-5 mots EN {$language} + subtext ≤12 mots

        ══════════════════════════════════════════════════════
        STRUCTURE JSON (tout en {$language})
        ══════════════════════════════════════════════════════
        {
          "title": "...(55-75 chars, [Catégorie] à [Pays]: [bénéfice])",
          "keywords_primary": "...(requête HEAD: '{$categoryLabel} expatriés {$countryName}', en {$language})",
          "keywords_secondary": ["...", "...", "...", "...", "..."],
          "sections": [
            {"type": "hero", "content": {"badge": "★ ...", "h1": "...", "subtitle": "...", "cta_text": "...", "cta_subtext": "..."}},
            {"type": "guide_steps", "content": {"headline": "...", "steps": [{"num": 1, "title": "...", "text": "..."}]}},
            {"type": "faq", "content": {"headline": "...", "items": [{"q": "...", "a": "..."}]}},
            {"type": "local_info", "content": {"headline": "...", "items": [{"icon": "📋", "title": "...", "text": "..."}]}},
            {"type": "trust_signals", "content": {"items": [{"icon": "⭐", "text": "..."}]}},
            {"type": "cta", "content": {"headline": "...", "button": "...", "subtext": "..."}}
          ],
          "meta_title": "...(EXACTEMENT 55-60 chars, EN {$language}, keyword + pays + marque)",
          "meta_description": "...(EXACTEMENT 148-155 chars, EN {$language}, répond à l'intent + bénéfice)",
          "cta_links": [
            {"label": "...(3-5 mots EN {$language})", "url": "#contact", "style": "primary", "position": "hero"}
          ],
          "lsi_keywords": ["...", "...", "...(8-12 termes sémantiques liés à la catégorie {$categoryLabel}, EN {$language})"],
          "internal_links": [
            {"anchor": "...(3-6 mots EN {$language}, sous-problème de la catégorie)", "topic": "page SOS-Expat sur ce sous-problème"},
            {"anchor": "...", "topic": "..."},
            {"anchor": "...", "topic": "..."}
          ]
        }
        PROMPT;

        $model       = ($params['use_cheap_model'] ?? false) ? 'gpt-4o-mini' : 'gpt-4o';
        $temperature = ($params['use_cheap_model'] ?? false) ? 0.4 : 0.7;
        $result      = $this->openAiWithCost($systemPrompt, $userPrompt, [
            'model'       => $model,
            'max_tokens'  => 4000,
            'json_mode'   => true,
            'temperature' => $temperature,
        ], $shell);

        if (empty($result['content'])) {
            Log::warning('LandingGenerationService: OpenAI failed, fallback to Claude', ['audience' => 'category_pillar']);
            $result = $this->claudeWithCost($systemPrompt, $userPrompt, ['model' => 'claude-sonnet-4-6', 'max_tokens' => 3500], $shell);
        }

        $parsed = $this->parseResponse($result['content'] ?? '');
        $slug   = $this->buildSlug('category_pillar', $language, $countrySlug, $categorySlug, $templateId, null);

        return $this->saveLandingPage([
            'audience_type'     => 'category_pillar',
            'template_id'       => $templateId,
            'country_code'      => $countryCode,
            'category_slug'     => $categorySlug,
            'problem_id'        => $categorySlug, // Compatibilité avec la colonne existante
            'language'          => $language,
            'country'           => $countryName,
            'generation_source' => 'ai_generated',
            'generation_params' => [
                'audience_type' => 'category_pillar',
                'category_slug' => $categorySlug,
                'template_id'   => $templateId,
                'language'      => $language,
                'country_code'  => $countryCode,
            ],
            'parent_id'         => $params['parent_id'] ?? null,
            'created_by'        => $createdBy,
        ], $parsed, $slug, $countryName, $countryCode, 'category_pillar', $params, $shell);
    }

    private function generateProfileLanding(
        string $userProfile,
        string $templateId,
        string $countryCode,
        string $language,
        ?int $createdBy,
        array $params = [],
        ?LandingPage $shell = null,
    ): LandingPage {
        $template    = self::TEMPLATES['profile'][$templateId] ?? self::TEMPLATES['profile']['profile_general'];
        $countryName = $this->getCountryName($countryCode, $language);
        $countrySlug = $this->getCountrySlug($countryCode, $language);

        // Libellé du profil + contexte spécifique
        $profileContext = $this->getProfileContext($userProfile, $language, $countryName);

        // Récupérer les problèmes pertinents pour ce profil
        $relevantProblems = LandingProblem::active()
            ->whereJsonContains('user_profiles', $userProfile)
            ->orderBy('urgency_score', 'desc')
            ->limit(8)
            ->pluck('title')
            ->toArray();
        $problemsList = empty($relevantProblems)
            ? 'Non disponible'
            : implode(', ', $relevantProblems);

        $systemPrompt = $this->buildSystemPrompt('profile', $countryCode, $countryName, $language);
        $profileSlug  = str_replace('_', '-', $userProfile); // digital_nomade → digital-nomade

        $userPrompt = <<<PROMPT
        TÂCHE: Générer une landing page ciblant un profil expatrié spécifique en {$language} pour SOS-Expat.
        Répondre UNIQUEMENT en JSON valide. TOUT le contenu en {$language}.

        ══════════════════════════════════════════════════════
        PROFIL CIBLE — QUI EST CE VISITEUR ?
        ══════════════════════════════════════════════════════
        {$profileContext}

        Pays de destination : {$countryName} ({$countryCode})
        Template             : {$template['label']}
        Ton                  : {$template['tone']}
        Langue OBLIGATOIRE   : {$language} — TOUT en {$language}, SANS EXCEPTION

        ══════════════════════════════════════════════════════
        PROBLÈMES LES PLUS PERTINENTS POUR CE PROFIL
        (issus de notre base de 417 problèmes validés)
        ══════════════════════════════════════════════════════
        {$problemsList}

        → La page doit répondre à CES problèmes spécifiques, PAS à des problèmes génériques.
        → Le vocabulaire doit être celui que ce profil utilise naturellement.

        ══════════════════════════════════════════════════════
        SECTIONS À GÉNÉRER (ordre exact)
        ══════════════════════════════════════════════════════
        {$this->formatSections($template['sections'])}

        RÈGLES PAR SECTION:
        • hero      → badge (★ chiffre confiance), h1 (5-9 mots: profil + pays + bénéfice clé),
                      subtitle (15-25 mots: comprendre la situation spécifique du profil),
                      cta_text (3-5 mots EN {$language}), cta_subtext (≤12 mots, frein #1 levé)
        • features  → headline H2 (PAA), 4-5 problèmes typiques de ce profil + solution SOS-Expat,
                      chaque item: icon + title (5-8 mots) + text (15-25 mots)
        • guide_steps → 4-6 étapes concrètes du parcours typique de ce profil à {$countryName}
        • faq       → 6-8 questions SPÉCIFIQUES au profil ET à {$countryName}, Answer-First, 45-65 mots
        • trust_signals → 4-5 preuves SOS-Expat adaptées au niveau d'exigence de ce profil
        • cta       → headline ≤10 mots (appel à l'action adapté au profil) + button 3-5 mots EN {$language}

        ══════════════════════════════════════════════════════
        STRUCTURE JSON (tout en {$language})
        ══════════════════════════════════════════════════════
        {
          "title": "...(55-75 chars, [Profil] à [Pays]: guide complet SOS-Expat)",
          "keywords_primary": "...(requête: '[profil] [pays]' ou '[problème profil] [pays]', en {$language})",
          "keywords_secondary": ["...", "...", "...", "..."],
          "sections": [
            {"type": "hero", "content": {"badge": "★ ...", "h1": "...", "subtitle": "...", "cta_text": "...", "cta_subtext": "..."}},
            {"type": "features", "content": {"headline": "...", "items": [{"icon": "🎯", "title": "...", "text": "..."}]}},
            {"type": "faq", "content": {"headline": "...", "items": [{"q": "...", "a": "..."}]}},
            {"type": "trust_signals", "content": {"items": [{"icon": "⭐", "text": "..."}]}},
            {"type": "cta", "content": {"headline": "...", "button": "...", "subtext": "..."}}
          ],
          "meta_title": "...(EXACTEMENT 55-60 chars, EN {$language})",
          "meta_description": "...(EXACTEMENT 148-155 chars, EN {$language})",
          "cta_links": [
            {"label": "...(3-5 mots EN {$language})", "url": "#contact", "style": "primary", "position": "hero"}
          ],
          "lsi_keywords": ["...", "...", "...(8-12 termes liés au profil {$userProfile} et à {$countryName}, EN {$language})"],
          "internal_links": [
            {"anchor": "...(3-6 mots EN {$language}, problème spécifique du profil)", "topic": "page SOS-Expat liée"},
            {"anchor": "...", "topic": "..."}
          ]
        }
        PROMPT;

        $model       = ($params['use_cheap_model'] ?? false) ? 'gpt-4o-mini' : 'gpt-4o';
        $temperature = ($params['use_cheap_model'] ?? false) ? 0.4 : 0.7;
        $result      = $this->openAiWithCost($systemPrompt, $userPrompt, [
            'model'       => $model,
            'max_tokens'  => 3500,
            'json_mode'   => true,
            'temperature' => $temperature,
        ], $shell);

        if (empty($result['content'])) {
            Log::warning('LandingGenerationService: OpenAI failed, fallback to Claude', ['audience' => 'profile']);
            $result = $this->claudeWithCost($systemPrompt, $userPrompt, ['model' => 'claude-sonnet-4-6', 'max_tokens' => 3000], $shell);
        }

        $parsed = $this->parseResponse($result['content'] ?? '');
        $slug   = $this->buildSlug('profile', $language, $countrySlug, $profileSlug, $templateId, null);

        return $this->saveLandingPage([
            'audience_type'     => 'profile',
            'template_id'       => $templateId,
            'country_code'      => $countryCode,
            'user_profile'      => $userProfile,
            'problem_id'        => $userProfile,
            'language'          => $language,
            'country'           => $countryName,
            'generation_source' => 'ai_generated',
            'generation_params' => [
                'audience_type' => 'profile',
                'user_profile'  => $userProfile,
                'template_id'   => $templateId,
                'language'      => $language,
                'country_code'  => $countryCode,
            ],
            'parent_id'         => $params['parent_id'] ?? null,
            'created_by'        => $createdBy,
        ], $parsed, $slug, $countryName, $countryCode, 'profile', $params, $shell);
    }

    private function generateEmergencyLanding(
        string $templateId,
        string $countryCode,
        string $language,
        ?int $createdBy,
        array $params = [],
        ?LandingPage $shell = null,
    ): LandingPage {
        $template    = self::TEMPLATES['emergency'][$templateId] ?? self::TEMPLATES['emergency']['emergency'];
        $countryName = $this->getCountryName($countryCode, $language);
        $countrySlug = $this->getCountrySlug($countryCode, $language);

        $systemPrompt = $this->buildSystemPrompt('emergency', $countryCode, $countryName, $language);

        $userPrompt = <<<PROMPT
        TÂCHE: Générer une page d'urgence SOS-Expat ultra-courte et percutante en {$language}.
        Répondre UNIQUEMENT en JSON valide. TOUT le contenu en {$language}.

        ══════════════════════════════════════════════════════
        CONTEXTE — PAGE URGENCE
        ══════════════════════════════════════════════════════
        Pays      : {$countryName} ({$countryCode})
        Langue OBLIGATOIRE : {$language}

        OBJECTIF: Quelqu'un est en urgence à {$countryName}. Il doit trouver de l'aide
        en MOINS DE 10 SECONDES. Chaque mot compte. Zéro intro. Zéro fioritures.
        CTA immédiat, visible au-dessus du fold.

        ══════════════════════════════════════════════════════
        RÈGLES URGENCE (CRITIQUES)
        ══════════════════════════════════════════════════════
        • H1: 5-7 mots max — action immédiate — ex: "Urgence à {$countryName} ? Appelez maintenant."
        • Subtitle: 10-15 mots max — "Expert disponible en moins de 5 minutes. 24h/24."
        • Trust signals: 4 items UNIQUEMENT — vitesse réponse, disponibilité, prix, confidentialité
        • FAQ: 5 questions courtes d'urgence (Que faire si arresté ? Urgence médicale ? etc.)
          → Réponses DIRECTES, 30-40 mots, numéros locaux si connus
        • CTA: 3-4 mots max — verbe d'action fort — ex: "Appeler maintenant", "Obtenir de l'aide"

        ══════════════════════════════════════════════════════
        SECTIONS À GÉNÉRER (ordre exact — PAGE COURTE)
        ══════════════════════════════════════════════════════
        hero → trust_signals → faq → cta

        • hero          → badge (⚡ disponibilité + vitesse), h1 (5-7 mots, URGENCE visible),
                          subtitle (10-15 mots), cta_text (3-4 mots EN {$language}),
                          cta_subtext (≤8 mots: "Gratuit · Immédiat · Confidentiel")
        • trust_signals → 4 items UNIQUEMENT: réponse <5 min, disponible 24h/24, prix fixe, 100% confidentiel
        • faq           → 5 situations d'urgence typiques à {$countryName}, réponses directes 30-40 mots,
                          INCLURE numéros d'urgence locaux si connus (police, ambulance, ambassade)
        • cta           → headline ≤8 mots (urgence absolue) + button 3-4 mots EN {$language} + subtext ≤8 mots

        ══════════════════════════════════════════════════════
        STRUCTURE JSON (tout en {$language})
        ══════════════════════════════════════════════════════
        {
          "title": "...(40-55 chars, Urgence [Pays] — SOS-Expat)",
          "keywords_primary": "...(urgence expatrié {$countryName}, en {$language})",
          "keywords_secondary": ["...", "...", "..."],
          "sections": [
            {"type": "hero", "content": {"badge": "⚡ ...", "h1": "...", "subtitle": "...", "cta_text": "...", "cta_subtext": "..."}},
            {"type": "trust_signals", "content": {"items": [{"icon": "⚡", "text": "..."}, {"icon": "🕐", "text": "..."}, {"icon": "💰", "text": "..."}, {"icon": "🔒", "text": "..."}]}},
            {"type": "faq", "content": {"headline": "...", "items": [{"q": "...", "a": "..."}]}},
            {"type": "cta", "content": {"headline": "...", "button": "...", "subtext": "..."}}
          ],
          "meta_title": "...(EXACTEMENT 55-60 chars, EN {$language}, urgence + pays + SOS-Expat)",
          "meta_description": "...(EXACTEMENT 148-155 chars, EN {$language}, urgence + réponse immédiate)",
          "cta_links": [
            {"label": "...(3-4 mots EN {$language}, verbe fort)", "url": "#contact", "style": "primary", "position": "hero"}
          ],
          "lsi_keywords": ["...", "...", "...(8-10 termes urgence expatriés {$countryName}, EN {$language})"],
          "internal_links": [
            {"anchor": "...(3-5 mots EN {$language}, situation urgence)", "topic": "page SOS-Expat liée à ce type d'urgence"},
            {"anchor": "...", "topic": "..."}
          ]
        }
        PROMPT;

        // Emergency: modèle standard (pas de cheap_model car ultra-court = rapide de toute façon)
        $model       = ($params['use_cheap_model'] ?? false) ? 'gpt-4o-mini' : 'gpt-4o';
        $temperature = 0.5; // Moins de créativité = plus de clarté pour l'urgence
        $result      = $this->openAiWithCost($systemPrompt, $userPrompt, [
            'model'       => $model,
            'max_tokens'  => 2000,
            'json_mode'   => true,
            'temperature' => $temperature,
        ], $shell);

        if (empty($result['content'])) {
            Log::warning('LandingGenerationService: OpenAI failed, fallback to Claude', ['audience' => 'emergency']);
            $result = $this->claudeWithCost($systemPrompt, $userPrompt, ['model' => 'claude-sonnet-4-6', 'max_tokens' => 1800], $shell);
        }

        $parsed = $this->parseResponse($result['content'] ?? '');
        // Emergency: slug = fr/urgence/thailande (pas de sous-clé)
        $slug   = $this->buildSlug('emergency', $language, $countrySlug, null, $templateId, null);

        return $this->saveLandingPage([
            'audience_type'     => 'emergency',
            'template_id'       => $templateId,
            'country_code'      => $countryCode,
            'language'          => $language,
            'country'           => $countryName,
            'generation_source' => 'ai_generated',
            'generation_params' => [
                'audience_type' => 'emergency',
                'template_id'   => $templateId,
                'language'      => $language,
                'country_code'  => $countryCode,
            ],
            'parent_id'         => $params['parent_id'] ?? null,
            'created_by'        => $createdBy,
        ], $parsed, $slug, $countryName, $countryCode, 'emergency', $params, $shell);
    }

    private function generateNationalityLanding(
        string $originNationality,
        string $templateId,
        string $countryCode,
        string $language,
        ?int $createdBy,
        array $params = [],
        ?LandingPage $shell = null,
    ): LandingPage {
        $template    = self::TEMPLATES['nationality'][$templateId] ?? self::TEMPLATES['nationality']['nationality_general'];
        $countryName = $this->getCountryName($countryCode, $language);
        $countrySlug = $this->getCountrySlug($countryCode, $language);

        // Libellé de la nationalité dans la langue cible
        $nationalityName = $this->getNationalityName($originNationality, $language);
        // Slug ASCII pour l'URL (ex: FR → 'francais' en FR, 'french' en EN)
        $nationalitySlug = $this->getNationalitySlug($originNationality, $language);

        $systemPrompt = $this->buildSystemPrompt('nationality', $countryCode, $countryName, $language);

        $userPrompt = <<<PROMPT
        TÂCHE: Générer une landing page pour les ressortissants {$nationalityName} à {$countryName} en {$language}.
        Répondre UNIQUEMENT en JSON valide. TOUT le contenu en {$language}.

        ══════════════════════════════════════════════════════
        CONTEXTE — PAGE NATIONALITÉ × PAYS
        ══════════════════════════════════════════════════════
        Nationalité d'origine : {$nationalityName} (code: {$originNationality})
        Pays de destination   : {$countryName} ({$countryCode})
        Template              : {$template['label']}
        Ton                   : {$template['tone']}
        Langue OBLIGATOIRE    : {$language} — TOUT en {$language}, SANS EXCEPTION

        ══════════════════════════════════════════════════════
        SPÉCIFICITÉS BILATÉRALES À INTÉGRER
        ══════════════════════════════════════════════════════
        Adapter le contenu à ces points clés spécifiques à la paire {$originNationality}↔{$countryCode} :
        • Conditions de visa (exemption? visa on arrival? e-visa? durée max?)
        • Convention fiscale entre les deux pays (double imposition? résidence fiscale?)
        • Accord de sécurité sociale / prise en charge santé
        • Procédures d'immatriculation auprès de l'ambassade {$originNationality} à {$countryName}
        • Points de vigilance culturels ou juridiques pour cette nationalité dans ce pays
        • Services consulaires disponibles (numéro, adresse, horaires)
        → Si information inconnue: rester générique mais PRÉCIS, ne PAS inventer de chiffres faux.

        ══════════════════════════════════════════════════════
        SECTIONS À GÉNÉRER (ordre exact)
        ══════════════════════════════════════════════════════
        {$this->formatSections($template['sections'])}

        RÈGLES PAR SECTION:
        • hero          → badge (★ chiffre confiance), h1 (5-9 mots: nationalité + pays + bénéfice),
                          subtitle (15-25 mots: spécificité pour cette nationalité),
                          cta_text (3-5 mots EN {$language}), cta_subtext (≤12 mots)
        • local_info    → headline H2, 4-5 infos bilatérales concrètes: ambassade, visa, convention fiscale,
                          numéros consulaires, ressources officielles
        • guide_steps   → 4-6 étapes pour un ressortissant {$nationalityName} s'installant à {$countryName}
        • faq           → 6-8 questions SPÉCIFIQUES à cette paire nationalité/pays, Answer-First, 45-60 mots
                          (ex: "Un ressortissant {$nationalityName} a-t-il besoin d'un visa pour {$countryName}?")
        • trust_signals → 4-5 preuves SOS-Expat avec focus sur la spécificité de ce couloir migratoire
        • cta           → headline ≤10 mots + button 3-5 mots EN {$language} + subtext ≤12 mots

        ══════════════════════════════════════════════════════
        STRUCTURE JSON (tout en {$language})
        ══════════════════════════════════════════════════════
        {
          "title": "...(55-75 chars, [Nationalité] à [Pays]: guide SOS-Expat)",
          "keywords_primary": "...(ex: 'expatrié {$nationalityName} {$countryName}', en {$language})",
          "keywords_secondary": ["...", "...", "...", "...", "..."],
          "sections": [
            {"type": "hero", "content": {"badge": "★ ...", "h1": "...", "subtitle": "...", "cta_text": "...", "cta_subtext": "..."}},
            {"type": "local_info", "content": {"headline": "...", "items": [{"icon": "🏛️", "title": "...", "text": "..."}]}},
            {"type": "guide_steps", "content": {"headline": "...", "steps": [{"num": 1, "title": "...", "text": "..."}]}},
            {"type": "faq", "content": {"headline": "...", "items": [{"q": "...", "a": "..."}]}},
            {"type": "trust_signals", "content": {"items": [{"icon": "⭐", "text": "..."}]}},
            {"type": "cta", "content": {"headline": "...", "button": "...", "subtext": "..."}}
          ],
          "meta_title": "...(EXACTEMENT 55-60 chars, EN {$language})",
          "meta_description": "...(EXACTEMENT 148-155 chars, EN {$language})",
          "cta_links": [
            {"label": "...(3-5 mots EN {$language})", "url": "#contact", "style": "primary", "position": "hero"}
          ],
          "lsi_keywords": ["...", "...", "...(8-12 termes liés à la paire {$originNationality}↔{$countryCode}, EN {$language})"],
          "internal_links": [
            {"anchor": "...(3-6 mots EN {$language}, spécificité bilatérale)", "topic": "page SOS-Expat sur ce sujet bilatéral"},
            {"anchor": "...", "topic": "..."},
            {"anchor": "...", "topic": "..."}
          ]
        }
        PROMPT;

        $model       = ($params['use_cheap_model'] ?? false) ? 'gpt-4o-mini' : 'gpt-4o';
        $temperature = ($params['use_cheap_model'] ?? false) ? 0.4 : 0.7;
        $result      = $this->openAiWithCost($systemPrompt, $userPrompt, [
            'model'       => $model,
            'max_tokens'  => 4000,
            'json_mode'   => true,
            'temperature' => $temperature,
        ], $shell);

        if (empty($result['content'])) {
            Log::warning('LandingGenerationService: OpenAI failed, fallback to Claude', ['audience' => 'nationality']);
            $result = $this->claudeWithCost($systemPrompt, $userPrompt, ['model' => 'claude-sonnet-4-6', 'max_tokens' => 3500], $shell);
        }

        $parsed = $this->parseResponse($result['content'] ?? '');
        $slug   = $this->buildSlug('nationality', $language, $countrySlug, $nationalitySlug, $templateId, null);

        return $this->saveLandingPage([
            'audience_type'      => 'nationality',
            'template_id'        => $templateId,
            'country_code'       => $countryCode,
            'origin_nationality' => $originNationality,
            'problem_id'         => strtolower($originNationality),
            'language'           => $language,
            'country'            => $countryName,
            'generation_source'  => 'ai_generated',
            'generation_params'  => [
                'audience_type'      => 'nationality',
                'origin_nationality' => $originNationality,
                'template_id'        => $templateId,
                'language'           => $language,
                'country_code'       => $countryCode,
            ],
            'parent_id'          => $params['parent_id'] ?? null,
            'created_by'         => $createdBy,
        ], $parsed, $slug, $countryName, $countryCode, 'nationality', $params, $shell);
    }

    // ============================================================
    // Helpers privés
    // ============================================================

    private function buildSystemPrompt(string $audienceType, string $countryCode, string $countryName, string $language): string
    {
        // Recherche intent selon audience
        $searchIntent = match ($audienceType) {
            'clients'         => 'urgency',
            'lawyers'         => 'commercial_investigation',
            'helpers'         => 'commercial_investigation',
            'matching'        => 'transactional',
            'category_pillar' => 'informational',
            'profile'         => 'informational',
            'emergency'       => 'urgency',
            'nationality'     => 'transactional',
            default           => 'informational',
        };

        // Base: Knowledge Base complète SOS-Expat (Brand Voice, AEO, SEO 2026, Schema rules, etc.)
        $system = $this->kb->getSystemPrompt('landing', $countryCode, $language, $searchIntent);

        // Injection données pays vérifiées (World Bank / OECD / Eurostat)
        $statsBlock = $this->stats->getCountryDataBlock($countryCode);
        if ($statsBlock) {
            $system .= "\n\n" . $statsBlock;
        }

        // ── Règles landing page haute-conversion 2026 ──────────────
        $system .= "\n\n" . $this->buildLandingPageRules($language, $countryName);

        // ── Données sondage (statistiques réelles de nos utilisateurs) ──
        $sondageBlock = $this->getSondageStatsBlock($language);
        if ($sondageBlock) {
            $system .= "\n\n" . $sondageBlock;
        }

        // ── Contexte audience spécifique ──────────────────────────
        $audienceContext = match ($audienceType) {
            'clients'  => "MISSION: Générer une landing page haute-conversion pour des expatriés/voyageurs en difficulté à {$countryName}. Service SOS-Expat: mise en relation 24h/24 avec avocats locaux et expatriés aidants expérimentés. Prix fixe transparent, disponibilité immédiate, 0 bureaucratie, réponse en moins de 5 minutes.",
            'lawyers'  => "MISSION: Recruter des avocats partenaires à {$countryName} pour rejoindre le réseau SOS-Expat. Proposition de valeur unique: 30€ par consultation de 20 min, paiement garanti sous 24h, zéro prospection (les clients viennent à vous), liberté totale des horaires, inscription en 5 minutes. Angle: complément de revenus sans contraintes.",
            'helpers'  => "MISSION: Recruter des expatriés déjà installés à {$countryName} pour devenir 'expatriés aidants' SOS-Expat. Ils aident les nouveaux arrivants sur le pratique (logement, administration, intégration — pas juridique). 10€ par appel de 20 min. Angle: valoriser son expérience, aider la communauté, gagner un complément flexible.",
            'matching' => "MISSION: Convertir immédiatement un visiteur en appel SOS-Expat à {$countryName}. Page ultra-courte et percutante. CTA unique, confiance maximale. USPs: réponse en moins de 5 min, prix fixe et transparent, expert local disponible 24h/24, 100% confidentiel.",
            'category_pillar' => "MISSION: Créer LA page de référence SEO (pilier thématique) sur une catégorie de problèmes à {$countryName}. Page exhaustive, longue, couvrant TOUS les sous-problèmes de la catégorie. Doit se positionner sur les requêtes HEAD ('visa {$countryName}', 'santé expatriés {$countryName}'). Structure pilier: vue d'ensemble → étapes → ressources → FAQ longue (7-10 questions). Chiffres réels, liens vers ressources officielles locales.",
            'profile' => "MISSION: Créer une landing page ciblant un profil expatrié spécifique à {$countryName}. Vocabulaire et angle 100% adapté à ce profil (ex: digital nomade → visa nomade, coworking, impôts; retraité → retraite à l'étranger, SS, testament; entrepreneur → création société, fiscalité). Les problèmes présentés sont UNIQUEMENT ceux pertinents pour ce profil. CTA adapté au niveau d'urgence typique de ce profil.",
            'emergency' => "MISSION: Créer la page d'urgence SOS-Expat pour {$countryName}. ULTRA-PRIORITAIRE: quelqu'un en situation d'urgence à {$countryName} doit trouver de l'aide en <10 secondes. Numéros d'urgence locaux (police, ambulance, ambassade). CTA immédiat au-dessus du fold. Zéro friction, zéro texte inutile. Chaque ligne doit aider à AGIR immédiatement.",
            'nationality' => "MISSION: Créer une landing page pour les ressortissants d'une nationalité spécifique se trouvant à {$countryName}. Prendre en compte les spécificités bilatérales: accords fiscaux entre les deux pays, accords de sécurité sociale, procédures d'ambassade, restrictions douanières particulières, exigences de visa selon la nationalité. Référencer les procédures officielles du pays d'origine applicable à {$countryName}.",
            default    => "MISSION: Générer du contenu SOS-Expat pour {$countryName}.",
        };

        $system .= "\n\n=== CONTEXTE AUDIENCE ===\n{$audienceContext}";

        return $system;
    }

    /**
     * Retourne un bloc de statistiques issues de nos sondages fermés.
     * Ces données réelles (E-E-A-T + preuves sociales) sont injectées dans le system prompt
     * pour enrichir les landing pages avec des chiffres authentiques.
     *
     * Retourne null si aucun sondage fermé disponible (pas de crash, graceful degradation).
     */
    private function getSondageStatsBlock(string $language): ?string
    {
        try {
            // Ne charger que les sondages fermés (données finales)
            $sondages = Sondage::where('status', 'closed')
                ->with(['questions' => function ($q) {
                    $q->whereIn('type', ['single', 'multiple', 'scale']); // Questions à choix = chiffrables
                }])
                ->latest()
                ->limit(5)
                ->get();

            if ($sondages->isEmpty()) {
                return null;
            }

            $lines = [];
            $totalRespondents = 0;

            foreach ($sondages as $sondage) {
                if ($sondage->questions->isEmpty()) continue;

                $lines[] = "Sondage: {$sondage->title}";

                foreach ($sondage->questions->take(3) as $question) {
                    if (empty($question->options) || ! is_array($question->options)) continue;

                    // Extraire les options avec les percentages si disponibles
                    $opts = array_slice($question->options, 0, 3);
                    $optsStr = implode(' | ', array_map(function ($opt) {
                        if (is_array($opt)) {
                            $label = $opt['label'] ?? $opt['text'] ?? '';
                            $pct   = isset($opt['percentage']) ? " ({$opt['percentage']}%)" : '';
                            return $label . $pct;
                        }
                        return (string) $opt;
                    }, $opts));

                    if ($optsStr) {
                        $lines[] = "  • {$question->text}: {$optsStr}";
                    }
                }
            }

            if (empty($lines)) {
                return null;
            }

            $intro = match ($language) {
                'en' => "=== REAL SURVEY DATA (SOS-Expat users — use these statistics for E-E-A-T signals) ===",
                'es' => "=== DATOS DE ENCUESTA REALES (usuarios SOS-Expat — use estas estadísticas para señales E-E-A-T) ===",
                'de' => "=== ECHTE UMFRAGEDATEN (SOS-Expat-Nutzer — für E-E-A-T-Signale verwenden) ===",
                default => "=== DONNÉES SONDAGE RÉELLES (utilisateurs SOS-Expat — utilisez ces statistiques pour les signaux E-E-A-T) ===",
            };

            $footer = match ($language) {
                'en' => "→ Integrate these real statistics naturally into the landing page content (trust_signals, faq, hero badge).",
                'es' => "→ Integre estas estadísticas reales de forma natural en el contenido de la landing page.",
                'de' => "→ Integrieren Sie diese echten Statistiken natürlich in den Landing-Page-Inhalt.",
                default => "→ Intégrez ces statistiques réelles naturellement dans le contenu de la landing page (trust_signals, faq, badge hero).",
            };

            return $intro . "\n" . implode("\n", $lines) . "\n" . $footer;
        } catch (\Throwable $e) {
            Log::warning('LandingGenerationService: getSondageStatsBlock failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Règles de génération landing page 2026 — perfection absolue.
     * 13 règles couvrant : langue, conversion, SEO, AEO/GEO, E-E-A-T,
     * entités nommées, passage indexing, LSI, UTM, freshness, word counts.
     * Injectées dans chaque system prompt.
     */
    private function buildLandingPageRules(string $language, string $countryName): string
    {
        $langInstructions = match ($language) {
            'en' => 'English — neutral international (adapt: UK English for GB, Australian English for AU, Canadian for CA)',
            'es' => 'Spanish — neutral Latin American (Castilian ONLY if country=Spain). Avoid "vosotros".',
            'de' => 'German — formal but warm (Sie form). Avoid overly stiff bureaucratic register.',
            'pt' => 'Portuguese — European PT for Portugal, Brazilian PT (você, não tu) for all others.',
            'ar' => 'Arabic — Modern Standard Arabic (MSA). Formal, right-to-left. No dialect.',
            'hi' => 'Hindi — Devanagari script. Formal but accessible. Use Hindi terms when they exist, not Anglicisms.',
            'zh' => 'Simplified Chinese (zh-Hans) — natural mainland Chinese, contemporary business register.',
            'ru' => 'Russian — contemporary business Russian. Direct, concrete. No Soviet-era formality.',
            default => 'French — formal but warm. "Vous" form. Avoid bureaucratic jargon and anglicisms.',
        };

        $currentYear = date('Y');

        return <<<RULES
=== RÈGLES LANDING PAGE — PERFECTION ABSOLUE 2026 ===

── RÈGLE N°1 — LANGUE (CRITIQUE, ZÉRO EXCEPTION) ──────────────────────────
Langue : {$langInstructions}
• TOUT le contenu en {$language} : title, h1, subtitle, badge, CHAQUE section,
  CHAQUE question ET réponse FAQ, meta_title, meta_description, TOUS les CTA.
• url_slug : ASCII kebab-case translittéré dans la langue cible (jamais Unicode).
• Adaptation culturelle {$countryName} : devise locale (USD→US/CA, GBP→UK, AUD→AU),
  expressions idiomatiques locales, références culturelles ancrées dans le pays.
• ZÉRO texte résiduel dans une autre langue.

── RÈGLE N°2 — H1 & HERO (DÉCISION DE CONVERSION EN 3 SECONDES) ───────────
• H1 : 5-9 MOTS MAX — keyword dès le 1er mot — chiffre ou bénéfice concret si possible.
• INTERDITS en H1 : "Bienvenue", "Découvrez", "Notre service", "Nous vous aidons".
• Formules gagnantes :
  - [Problème résolu] + [Lieu] : "Visa refusé en Thaïlande ? Réponse en 5 min."
  - [Bénéfice chiffré] + [Contexte] : "Avocat local à Singapour en 4 minutes."
  - [Action immédiate] + [Pour qui] : "Parlez à un expert expatrié à Bangkok."
• Badge (au-dessus du H1) : trust anchor ≤10 mots avec 2-3 chiffres réels.
  Ex: "★ 4.9/5 · 12 847 expatriés aidés · Réponse < 4 min"
• CTA principal : VERBE D'ACTION + BÉNÉFICE — 3-5 mots — JAMAIS "Envoyer"/"OK".
• cta_subtext (friction killer) : ≤12 mots — lève le frein #1 du visiteur.
  Ex: "Sans engagement · Confidentiel · Disponible 24h/24"

── RÈGLE N°3 — PREUVES SOCIALES & CHIFFRES PRÉCIS ─────────────────────────
• PRÉCISION OBLIGATOIRE : jamais de fourchettes vagues.
  ✓ "12 847 expatriés aidés en {$currentYear}" ✗ "des milliers d'expatriés"
  ✓ "Réponse en 4 min 23 en moyenne" ✗ "réponse rapide"
  ✓ "4.9/5 sur 2 340 avis vérifiés" ✗ "excellentes notes"
• Minimum 4 chiffres distincts dans la page (vitesse · volume · satisfaction · prix).
• Données pays : utiliser les statistiques injectées en context (World Bank/OECD).

── RÈGLE N°4 — AEO/GEO (Generative Engine Optimization 2026) ──────────────
• ANSWER-FIRST OBLIGATOIRE : chaque réponse FAQ commence par la réponse directe.
  Format cible : [Réponse directe en 1 phrase] + [Détail + contexte {$countryName}] = 40-60 mots.
• Questions FAQ = vraies recherches Google/vocales ("Que faire si...", "Combien coûte...", "Est-ce légal...").
• PASSAGE INDEXING : chaque section DOIT commencer par une phrase autonome et complète
  qui répond à l'intent du visiteur SANS lire le reste de la page.
  → Google extrait des "passages" individuels — chaque H2+1ère phrase doit être self-contained.
• Les AI overviews (Google AIO, ChatGPT, Perplexity) citent les pages qui répondent
  en <30 mots à une question précise → FAQ ultra-courtes dans la 1ère phrase.
• Minimum 5 FAQ ultra-spécifiques à {$countryName} ET au problème traité. Zéro FAQ générique.

── RÈGLE N°5 — SEO 2026 ────────────────────────────────────────────────────
• meta_title : [Keyword Principal] | SOS-Expat — EXACTEMENT 55-60 caractères.
  Compter caractère par caractère. Inclure le nom du pays.
• meta_description : verbe d'action en début — keyword + pays + bénéfice + urgence implicite.
  EXACTEMENT 148-155 caractères. Compter précisément.
• H2 de chaque section : reformulation d'une vraie question "People Also Ask" Google.
• Keyword density : 0.8-1.5% — naturelle, variée — pas de répétition mécanique.
• FRESHNESS : mentionner "{$currentYear}" dans le contenu (ex: "Mis à jour {$currentYear}",
  "Données {$currentYear}", "Réglementation en vigueur {$currentYear}").

── RÈGLE N°6 — E-E-A-T (Experience · Expertise · Authoritativeness · Trust) ──
CRITIQUE pour Google 2026 : les pages YMYL (santé/argent/juridique) sans E-E-A-T
sont pénalisées ou exclues des résultats. OBLIGATOIRE pour SOS-Expat :
• EXPERIENCE : mentionner l'expérience terrain de SOS-Expat dans ce pays.
  Ex: "Depuis 2019, SOS-Expat a traité plus de 3 200 cas à {$countryName}."
• EXPERTISE : référencer les types d'experts disponibles (avocats locaux certifiés,
  expatriés expérimentés, partenaires vérifiés).
• AUTORITÉ : mentionner les partenariats officiels ou accréditations si pertinent.
• TRUST : prix fixe et transparent affiché, confidentialité RGPD, disponibilité 24h/24,
  satisfaction garantie ou remboursement.
→ Ces éléments doivent apparaître naturellement dans hero, trust_signals et faq.

── RÈGLE N°7 — ENTITÉS NOMMÉES (Knowledge Graph Google) ────────────────────
Google 2026 utilise les entités pour comprendre le contexte et citer les pages dans AIO.
OBLIGATOIRE d'inclure des entités RÉELLES et VÉRIFIABLES :
• Nom de l'ambassade/consulat du pays d'origine à {$countryName} (si pertinent).
• Nom de la loi ou réglementation locale applicable (ex: "Immigration Act B.E. 2522" en Thaïlande).
• Nom du ministère ou organisme officiel local.
• Site web officiel (.gov, .gouv, etc.) — URL complète si connu.
• Numéro d'urgence local réel (police, ambulance, ambassade).
• Noms de quartiers, zones ou villes clés dans {$countryName}.
→ Si une entité n'est pas connue avec certitude : l'omettre plutôt qu'inventer.

── RÈGLE N°8 — LIENS INTERNES (Topical Authority) ─────────────────────────
Générer dans "internal_links" des suggestions de liens vers d'autres contenus SOS-Expat :
• 2-3 liens vers des articles blog SOS-Expat liés au même pays ou problème.
  Format : {"anchor": "texte ancre descriptif (3-6 mots)", "topic": "sujet cible descriptif"}
• 1 lien vers la page d'accueil SOS-Expat si pertinent.
• Ancres variées (pas "cliquez ici"), descriptives du contenu cible.
• Ces liens renforcent l'autorité thématique du domaine entier.

── RÈGLE N°9 — LSI KEYWORDS (Sémantique) ───────────────────────────────────
Générer dans "lsi_keywords" une liste de 8-12 synonymes et termes sémantiquement liés :
• Synonymes naturels du keyword principal (ex: "visa expiré" → "titre de séjour périmé",
  "overstay", "dépassement de visa", "régularisation visa").
• Termes connexes utilisés par les vrais utilisateurs dans ce pays.
• Ces termes DOIVENT apparaître naturellement dans le texte (pas de bourrage).
• Format : ["terme1", "terme2", ...] en {$language}.

── RÈGLE N°10 — WORD COUNTS PAR SECTION ────────────────────────────────────
Respecter scrupuleusement ces cibles (comptage des mots du contenu textuel) :
• hero → badge: ≤10 mots | h1: 5-9 mots | subtitle: 15-25 mots | cta_text: 3-5 mots | cta_subtext: ≤12 mots
• trust_signals → chaque item: 5-8 mots (chiffre + contexte)
• features → chaque item title: 5-8 mots | text: 15-25 mots
• guide_steps/process → chaque step title: 4-8 mots | text: 20-35 mots
• faq → question: 6-12 mots | réponse: 40-60 mots TOTAL (Answer-First)
• local_info → chaque item title: 4-8 mots | text: 20-40 mots (données concrètes)
• earnings → headline: ≤10 mots | chiffre principal: visible seul | badges: ≤6 mots chacun
• cta → headline: ≤10 mots | button: 3-5 mots | subtext: ≤12 mots
• testimonial_proof → citation: 20-40 mots | attribution: Prénom + situation (≤8 mots)
• why_us/no_pressure → chaque item: headline 4-8 mots + text 15-25 mots

── RÈGLE N°11 — UTM TRACKING (ROI Mesurable) ───────────────────────────────
Tous les liens CTA doivent inclure des paramètres UTM pour mesurer le ROI.
Format dans cta_links.url :
  "#contact?utm_source=landing&utm_medium={AUDIENCE_TYPE}&utm_campaign={COUNTRY_CODE}"
• utm_source = "landing"
• utm_medium = type d'audience (clients, emergency, nationality, etc.)
• utm_campaign = code pays ISO en minuscules (ex: th, fr, sg)
→ Permet de tracker précisément quelle LP convertit le mieux.

── RÈGLE N°12 — FRESHNESS & ACTUALITÉ ─────────────────────────────────────
• Mentionner "{$currentYear}" dans le contenu une fois naturellement.
• "Mis à jour {$currentYear}" dans le hero ou local_info si réglementation concernée.
• Pour visa/immigration/fiscalité : indiquer "Réglementation en vigueur au {$currentYear}".
• Éviter toute référence à des années passées (pas de "en 2023", "en 2024").

── RÈGLE N°13 — QUALITÉ ABSOLUE PAR TYPE DE SECTION ────────────────────────
• hero : badge + H1 percutant + subtitle amplifiant + CTA + sous-texte. Section la + importante.
• trust_signals : 4-5 items UNIQUEMENT FACTUELS, chiffres précis, ≤8 mots/item.
• guide_steps : verbe impératif en début, chronologie logique, résultat concret à chaque étape.
• local_info : données RÉELLES {$countryName} — ambassade, numéros, site officiel, délais.
• faq : Answer-First + passage indexing + entités nommées dans les réponses.
• cta : headline urgence + button verbe fort + friction killer.
• earnings : chiffre principal mis en avant, délai paiement, badges réassurance financière.
RULES;
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
            throw new \RuntimeException('La réponse AI n\'est pas un JSON valide ou est vide.');
        }

        // Valider les champs obligatoires
        if (empty($data['title'])) {
            throw new \RuntimeException('La réponse AI ne contient pas de "title".');
        }
        if (empty($data['sections']) || ! is_array($data['sections'])) {
            throw new \RuntimeException('La réponse AI ne contient pas de "sections" valides.');
        }

        return $data;
    }

    private function saveLandingPage(
        array $baseData,
        array $parsed,
        string $slug,
        string $countryName = '',
        string $countryCode = '',
        string $audienceType = '',
        array $params = [],
        ?LandingPage $shell = null,
    ): LandingPage {
        // Déduplication. Deux modes :
        //
        // 1. DÉFAUT (force_update=false) : on cherche par slug strict. Si une LP
        //    existe avec exactement ce slug, on la retourne sans modif (évite
        //    d'écraser une version manuelle par une version AI moins bonne).
        //
        // 2. force_update=true : on cherche aussi par SIGNATURE (audience_type +
        //    template_id + country_code + language + problem/category/profile/
        //    nationality). Ça permet d'enrichir les landings existantes VIDES
        //    même si leur slug diffère (ex: slug historique "fr-vn/help/expatrie/
        //    vietnam" alors que buildSlug produit "fr/aide/expatrie/vietnam").
        $forceUpdate = ! empty($params['force_update']);

        $existing = LandingPage::where('slug', $slug)
            ->when($shell, fn ($q) => $q->where('id', '!=', $shell->id))
            ->first();

        if ($forceUpdate && ! $existing) {
            // Tentative de match par signature au lieu du slug
            $signatureQuery = LandingPage::query()
                ->where('audience_type', $baseData['audience_type'] ?? null)
                ->where('template_id',   $baseData['template_id']   ?? null)
                ->where('country_code',  $baseData['country_code']  ?? null)
                ->where('language',      $baseData['language']      ?? null);

            // Discriminateur selon audience
            $audience = $baseData['audience_type'] ?? '';
            if ($audience === 'clients' && ! empty($baseData['problem_id'])) {
                $signatureQuery->where('problem_id', $baseData['problem_id']);
            } elseif ($audience === 'category_pillar' && ! empty($baseData['category_slug'])) {
                $signatureQuery->where('category_slug', $baseData['category_slug']);
            } elseif ($audience === 'profile' && ! empty($baseData['user_profile'])) {
                $signatureQuery->where('user_profile', $baseData['user_profile']);
            } elseif ($audience === 'nationality' && ! empty($baseData['origin_nationality'])) {
                $signatureQuery->where('origin_nationality', $baseData['origin_nationality']);
            }

            if ($shell) {
                $signatureQuery->where('id', '!=', $shell->id);
            }

            $existing = $signatureQuery->first();
        }

        if ($existing && ! $forceUpdate) {
            $shell?->forceDelete();
            return $existing;
        }

        // force_update=true → on va UPDATE l'existante plus bas via $shell = $existing
        // (ça garde l'id + parent_id + ctaLinks + hreflang_map, mais remplace sections,
        // title, meta, seo_score, json_ld, images si pas déjà définies).
        if ($existing && $forceUpdate) {
            $shell?->forceDelete();
            $shell = $existing;
        }

        $seoScore    = $this->calculateSeoScore($parsed);
        $hreflangMap = $this->buildHreflangMap($baseData, $parsed);

        // OG locale
        $ogLocale = GeoMetaService::OG_LOCALE_MAP[$baseData['language'] ?? 'fr'] ?? 'fr_FR';

        // Geo metadata depuis CountryGeo
        $geo = CountryGeo::findByCode($countryCode);
        $canonicalUrl = rtrim(config('services.blog.site_url', 'https://sos-expat.com'), '/') . '/' . $slug;

        $geoFields = [];
        if ($geo) {
            $geoFields = [
                'geo_region'    => strtoupper($countryCode),
                'geo_placename' => $baseData['country'] ?? '',
                'geo_position'  => ($geo->latitude && $geo->longitude) ? "{$geo->latitude};{$geo->longitude}" : null,
                'icbm'          => ($geo->latitude && $geo->longitude) ? "{$geo->latitude}, {$geo->longitude}" : null,
            ];
        }

        // ── Image Unsplash ─────────────────────────────────────────
        // Si le caller passe une image (variante langue d'un parent FR) → réutiliser.
        // Sinon → fetch Unsplash (uniquement pour la version primaire FR).
        $imageData = [];
        if (! empty($params['featured_image_url'])) {
            // Variante langue : copier l'image du parent
            $imageData = [
                'featured_image_url'         => $params['featured_image_url'],
                'featured_image_alt'         => $params['featured_image_alt'] ?? null,
                'featured_image_attribution' => $params['featured_image_attribution'] ?? null,
                'photographer_name'          => $params['photographer_name'] ?? null,
                'photographer_url'           => $params['photographer_url'] ?? null,
            ];
        } else {
            // Version primaire : appel Unsplash
            $fetchedImage = $this->fetchFeaturedImage($countryName, $audienceType, $countryCode);
            if ($fetchedImage) {
                $imageData = $fetchedImage;
            }
        }

        $lang           = $baseData['language'] ?? 'fr';
        $featuredImgUrl = $imageData['featured_image_url'] ?? null;

        // ── Enforce strict SEO character limits (meta_title ≤60, meta_description ≤155)
        $metaTitle = mb_substr(trim($parsed['meta_title'] ?? ($parsed['title'] ?? $slug)), 0, 60);
        $metaDesc  = mb_substr(trim($parsed['meta_description'] ?? ''), 0, 155);
        $ogTitle   = $metaTitle ?: null;
        $ogDesc    = $metaDesc  ?: null;

        $designTemplate = $this->determineDesignTemplate($audienceType, $baseData['template_id'] ?? '');
        $nowTs          = now();

        // ── Build + validate JSON-LD (non-blocking) ────────────────
        $jsonLd = $this->buildJsonLd(
            $parsed,
            $audienceType,
            $baseData['country'] ?? '',
            $canonicalUrl,
            $lang,
            $featuredImgUrl,
        );
        $jsonLdResult = app(JsonLdService::class)->validate($jsonLd);
        if (! $jsonLdResult['valid']) {
            Log::warning('LandingPage JSON-LD invalide', [
                'slug'   => $slug,
                'errors' => $jsonLdResult['errors'],
            ]);
        }

        $fullData = array_merge($baseData, $imageData, $geoFields, [
            'title'              => $parsed['title'] ?? $slug,
            'slug'               => $slug,
            'meta_title'         => $metaTitle ?: null,
            'meta_description'   => $metaDesc  ?: null,
            'keywords_primary'   => $parsed['keywords_primary'] ?? null,
            'keywords_secondary' => is_array($parsed['keywords_secondary'] ?? null)
                                    ? $parsed['keywords_secondary']
                                    : null,
            'sections'           => $parsed['sections'] ?? [],
            'seo_score'          => $seoScore,
            'status'             => 'draft',
            'hreflang_map'       => $hreflangMap,
            'json_ld'            => $jsonLd,
            'canonical_url'      => $canonicalUrl,
            'og_locale'          => $ogLocale,
            'og_type'            => 'WebPage',
            'og_url'             => $canonicalUrl,
            'og_site_name'       => 'SOS-Expat & Travelers',
            'og_title'           => $ogTitle,
            'og_description'     => $ogDesc,
            'og_image'           => $featuredImgUrl,
            'twitter_card'       => 'summary_large_image',
            'twitter_title'      => $ogTitle,
            'twitter_description'=> $ogDesc,
            'twitter_image'      => $featuredImgUrl,
            'robots'             => 'index,follow',
            'design_template'    => $designTemplate,
            'date_published_at'  => $nowTs,
            'date_modified_at'   => $nowTs,
            'content_language'   => $lang,
        ]);

        if ($shell !== null) {
            // Hydrate le shell (créé en amont pour rattacher les api_costs via costable_id)
            $shell->update($fullData);
            $landing = $shell->refresh();
        } else {
            $landing = LandingPage::create($fullData);
        }

        // Somme des ApiCost rattachés au shell pendant la génération (OpenAI + Claude).
        // Sans shell, impossible de lier rétroactivement → reste à 0 (legacy path).
        if ($shell !== null) {
            $costCents = (int) ApiCost::where('costable_type', LandingPage::class)
                ->where('costable_id', $landing->id)
                ->sum('cost_cents');
            if ($costCents > 0) {
                $landing->update(['generation_cost_cents' => $costCents]);
            }
        }

        // CTAs — avec injection UTM automatique
        if (! empty($parsed['cta_links'])) {
            $utmParams = "utm_source=landing&utm_medium={$audienceType}&utm_campaign=" . strtolower($countryCode);
            foreach ($parsed['cta_links'] as $i => $cta) {
                $url = $cta['url'] ?? '#contact';
                // Injecter UTM si pas déjà présent
                if (! str_contains($url, 'utm_source=')) {
                    $separator = str_contains($url, '?') ? '&' : '?';
                    // Extraire le fragment (#contact) et coller l'UTM juste après
                    if (str_starts_with($url, '#')) {
                        $url = $url . $separator . $utmParams;
                    } else {
                        $url = $url . $separator . $utmParams;
                    }
                }
                $landing->ctaLinks()->create([
                    'url'        => $url,
                    'text'       => $cta['label'] ?? 'Contacter un expert',
                    'position'   => $cta['position'] ?? 'hero',
                    'style'      => $cta['style'] ?? 'primary',
                    'sort_order' => $i,
                ]);
            }
        }

        // Sauvegarder les métadonnées SEO générées (lsi_keywords, internal_links)
        $seoMeta = [];
        if (! empty($parsed['lsi_keywords']) && is_array($parsed['lsi_keywords'])) {
            $seoMeta['lsi_keywords'] = $parsed['lsi_keywords'];
        }
        if (! empty($parsed['internal_links']) && is_array($parsed['internal_links'])) {
            $seoMeta['internal_links'] = $parsed['internal_links'];
        }
        if (! empty($seoMeta)) {
            $currentParams = $landing->generation_params ?? [];
            $landing->update(['generation_params' => array_merge($currentParams, $seoMeta)]);
        }

        return $landing;
    }

    /**
     * Cherche une image Unsplash adaptée au pays + audience.
     * 4 strategies de fallback pour maximiser les chances de trouver une image.
     * Retourne null si Unsplash n'est pas configuré ou rate-limité.
     */
    private function fetchFeaturedImage(string $countryName, string $audienceType, string $countryCode): ?array
    {
        if (! $this->unsplash->isConfigured()) {
            return null;
        }

        // Stratégies par ordre de préférence
        $audienceKeyword = match ($audienceType) {
            'clients'         => 'expat help abroad',
            'lawyers'         => 'lawyer professional office',
            'helpers'         => 'community help volunteer',
            'matching'        => 'international assistance',
            'category_pillar' => 'expatriate guide information',
            'profile'         => 'expat lifestyle abroad',
            'emergency'       => 'emergency help rescue',
            'nationality'     => 'international travel passport',
            default           => 'expatriate international',
        };

        $strategies = [
            "{$countryName} expatriate life",
            "{$countryName} city travel",
            "expatriate {$countryName} {$audienceKeyword}",
            $audienceKeyword,
            'expatriate international travel',
        ];

        foreach ($strategies as $query) {
            $result = $this->unsplash->searchUnique($query, 1, 'landscape', 3);

            if (! empty($result['success']) && ! empty($result['images'])) {
                $img = $result['images'][0];
                // Marquer utilisée pour éviter réutilisation sur d'autres LP
                $this->unsplashTracker->markUsed(
                    photoUrl: $img['url'] ?? '',
                    language: 'landing_page',   // champ language = type de contenu
                    country: $countryCode,
                    sourceQuery: $query,
                    photographerName: $img['photographer_name'] ?? null,
                    photographerUrl: $img['photographer_url'] ?? null,
                );

                Log::info('LandingGenerationService: image Unsplash trouvée', [
                    'query'       => $query,
                    'country'     => $countryName,
                    'audience'    => $audienceType,
                    'photographer' => $img['photographer_name'] ?? '',
                ]);

                return [
                    'featured_image_url'         => $img['url'],
                    'featured_image_alt'         => $img['alt_text'] ?? "{$countryName} expatriate",
                    'featured_image_attribution' => $img['attribution'] ?? null,
                    'photographer_name'          => $img['photographer_name'] ?? null,
                    'photographer_url'           => $img['photographer_url'] ?? null,
                ];
            }
        }

        Log::info('LandingGenerationService: aucune image Unsplash disponible', [
            'country'  => $countryName,
            'audience' => $audienceType,
        ]);

        return null;
    }

    /**
     * Génère la hreflang_map pour une landing page.
     *
     * At generation time, we only know the current language's canonical_url.
     * The full 9-language map is built AFTER all translations are generated,
     * by syncHreflangMap() which reads actual canonical_urls from DB.
     *
     * This method now returns a partial map (current lang only) as a placeholder.
     * The BlogPublisher calls syncHreflangMap() before publishing.
     */
    private function buildHreflangMap(array $baseData, array $parsedResponse = []): array
    {
        $lang        = $baseData['language'] ?? 'fr';
        $countryCode = $baseData['country_code'] ?? '';
        $siteUrl     = rtrim(config('services.blog.site_url', 'https://sos-expat.com'), '/');

        // Build only the current language's URL (the only one we know for certain)
        $countrySlug      = $countryCode ? $this->getCountrySlug($countryCode, $lang) : '';
        $audienceType     = $baseData['audience_type'];
        $problemSlug      = $baseData['generation_params']['problem_slug'] ?? null;
        $templateId       = $baseData['template_id'] ?? null;
        $localizedUrlSlug = $parsedResponse['url_slug'] ?? null;

        $slug = $this->buildSlug($audienceType, $lang, $countrySlug, $problemSlug, $templateId, $localizedUrlSlug);
        $currentUrl = "{$siteUrl}/{$slug}";

        // Placeholder map — will be completed by syncHreflangMap() before publish
        return [$lang => $currentUrl];
    }

    /**
     * Rebuild the hreflang_map from actual canonical_urls of all sibling translations.
     * Called after all language variants are generated, and before publishing.
     *
     * Uses parent_id to find siblings: parent (FR) + all children (EN, DE, ES, etc.)
     */
    public function syncHreflangMap(LandingPage $landing): array
    {
        // Find the root (parent) landing page
        $root = $landing->parent_id
            ? LandingPage::find($landing->parent_id)
            : $landing;

        if (! $root) {
            return $landing->hreflang_map ?? [];
        }

        // Collect all siblings: root + all children with parent_id = root.id
        $siblings = LandingPage::where('parent_id', $root->id)
            ->whereNotNull('canonical_url')
            ->get()
            ->keyBy('language');

        // Include root itself
        if ($root->canonical_url) {
            $siblings[$root->language] = $root;
        }

        // Build the definitive map from actual canonical_urls
        $map = [];
        foreach ($siblings as $lang => $sibling) {
            $map[$lang] = $sibling->canonical_url;
        }

        // x-default → French first, then English, then first available
        $map['x-default'] = $map['fr'] ?? $map['en'] ?? reset($map) ?: '';

        // Update all siblings with the same complete map
        foreach ($siblings as $sibling) {
            $sibling->update(['hreflang_map' => $map]);
        }

        return $map;
    }

    private function calculateSeoScore(array $parsed): int
    {
        $score = 0;

        // Méta SEO (40 pts)
        if (! empty($parsed['title'])) $score += 10;
        if (! empty($parsed['meta_title'])) {
            $len = mb_strlen($parsed['meta_title']);
            $score += ($len >= 50 && $len <= 65) ? 15 : 8; // Pleine note si 50-65 chars
        }
        if (! empty($parsed['meta_description'])) {
            $len = mb_strlen($parsed['meta_description']);
            $score += ($len >= 145 && $len <= 160) ? 15 : 8; // Pleine note si 145-160 chars
        }

        // Sections obligatoires (30 pts)
        $sections = $parsed['sections'] ?? [];
        $types    = array_column($sections, 'type');
        if (in_array('hero', $types)) $score += 12;
        if (in_array('faq', $types))  $score += 10;
        if (in_array('cta', $types))  $score += 8;

        // Signaux E-E-A-T & AEO 2026 (30 pts)
        // LSI keywords (signaux sémantiques)
        $lsiKeywords = $parsed['lsi_keywords'] ?? [];
        if (! empty($lsiKeywords) && count($lsiKeywords) >= 5) {
            $score += (count($lsiKeywords) >= 8) ? 8 : 4;
        }
        // Liens internes (autorité thématique)
        $internalLinks = $parsed['internal_links'] ?? [];
        if (! empty($internalLinks)) {
            $score += min(7, count($internalLinks) * 2);
        }
        // Richesse des sections (guide_steps = HowTo schema, local_info = entités nommées)
        if (in_array('guide_steps', $types) || in_array('process', $types)) $score += 5;
        if (in_array('local_info', $types))  $score += 5;
        // Keyword primary (SEO fondamental)
        if (! empty($parsed['keywords_primary'])) $score += 5;
        // trust_signals (E-E-A-T social proof)
        if (in_array('trust_signals', $types)) $score += 5;

        return min(100, $score);
    }

    /**
     * Détermine le template design à utiliser pour le rendu blog selon l'audience et le template contenu.
     *
     * 5 templates visuels distincts :
     * - urgency      : rouge/orange, CTA sticky, 3 sections, chiffres XXL (clients/urgent, matching, emergency)
     * - informational: blanc épuré, sidebar, FAQ accordéon, table des matières (clients/seo, category_pillar, nationality)
     * - trust        : témoignages proéminents, étoiles visibles (clients/trust, profile)
     * - recruitment  : chiffres revenus en avant, process visuel (lawyers, helpers)
     * - conversion   : page ultra-courte, single CTA, friction zéro (matching)
     * - pillar       : long-form, table des matières flottante, rich snippets (category_pillar/overview)
     * - profile      : story-driven, empathique, vocabulaire du profil (profile)
     * - emergency    : minimal, haut contraste, CTA au-dessus du fold (emergency)
     */
    private function determineDesignTemplate(string $audienceType, string $templateId): string
    {
        return match(true) {
            $audienceType === 'emergency'                                    => 'emergency',
            $audienceType === 'category_pillar' && $templateId === 'overview'=> 'pillar',
            $audienceType === 'category_pillar'                              => 'informational',
            $audienceType === 'profile'                                      => 'profile',
            $audienceType === 'nationality'                                  => 'informational',
            in_array($audienceType, ['lawyers', 'helpers'])                  => 'recruitment',
            $audienceType === 'matching'                                     => 'conversion',
            $templateId === 'urgent'                                         => 'urgency',
            $templateId === 'trust'                                          => 'trust',
            $templateId === 'seo'                                            => 'informational',
            default                                                          => 'informational',
        };
    }

    /**
     * Construit le @graph JSON-LD complet pour la perfection SEO 2026.
     *
     * Inclut :
     * - WebPage avec @id, datePublished, dateModified, inLanguage, keywords, image
     * - Organization avec sameAs (E-E-A-T Author entity)
     * - FAQPage (rich snippets questions)
     * - HowTo (rich snippets étapes)
     * - Service/EmergencyService avec AggregateRating (étoiles dans SERP)
     * - SpeakableSpecification (Google TTS / AI voice)
     * - BreadcrumbList (navigation enrichie)
     */
    private function buildJsonLd(
        array   $parsed,
        string  $audienceType,
        string  $countryName,
        string  $canonicalUrl   = '',
        string  $language       = 'fr',
        ?string $featuredImage  = null,
    ): array {
        $siteUrl   = rtrim(config('services.blog.site_url', 'https://sos-expat.com'), '/');
        $orgUrl    = 'https://sos-expat.com';
        $orgId     = $orgUrl . '/#organization';
        $orgName   = 'SOS-Expat';
        $title     = $parsed['title'] ?? '';
        $desc      = $parsed['meta_description'] ?? '';
        $sections  = $parsed['sections'] ?? [];
        $lsiKw     = $parsed['lsi_keywords'] ?? [];
        $now       = now()->toIso8601String();
        $pageId    = $canonicalUrl ? $canonicalUrl . '#webpage' : $orgUrl . '/#webpage';
        $serviceId = $canonicalUrl ? $canonicalUrl . '#service' : $orgUrl . '/#service';

        $graph = [];

        // ── 1. Organization (E-E-A-T — entité auteur identifiable) ──────────
        // sameAs = signaux d'autorité cross-web : LinkedIn, Trustpilot, réseaux sociaux
        $graph[] = [
            '@type'        => 'Organization',
            '@id'          => $orgId,
            'name'         => $orgName,
            'url'          => $orgUrl,
            'foundingDate' => '2019',
            'logo'         => [
                '@type' => 'ImageObject',
                'url'   => $orgUrl . '/logo.png',
                'width' => 180,
                'height'=> 60,
            ],
            'sameAs' => [
                'https://www.linkedin.com/company/sos-expat',
                'https://www.facebook.com/sosexpat',
                'https://twitter.com/sosexpat',
                'https://www.trustpilot.com/review/sos-expat.com',
            ],
            'contactPoint' => [
                '@type'             => 'ContactPoint',
                'contactType'       => 'customer support',
                'availableLanguage' => ['French', 'English', 'Spanish'],
                'hoursAvailable'    => 'Mo-Su 00:00-24:00',
            ],
        ];

        // ── 2. WebPage (avec datePublished, dateModified, freshness signals) ──
        $webPage = [
            '@type'            => 'WebPage',
            '@id'              => $pageId,
            'url'              => $canonicalUrl ?: $orgUrl,
            'name'             => $title,
            'description'      => $desc,
            'inLanguage'       => $language,
            'datePublished'    => $now,
            'dateModified'     => $now,
            'isPartOf'         => ['@type' => 'WebSite', '@id' => $orgUrl . '/#website', 'name' => $orgName, 'url' => $orgUrl],
            'publisher'        => ['@id' => $orgId],
            'author'           => ['@id' => $orgId],
        ];
        // keywords depuis LSI (sémantique renforcée)
        if (! empty($lsiKw)) {
            $webPage['keywords'] = implode(', ', array_slice($lsiKw, 0, 10));
        }
        // Image principale (Open Graph + WebPage image)
        $imageUrl = $featuredImage ?? ($parsed['featured_image_url'] ?? null);
        if ($imageUrl) {
            $webPage['image'] = [
                '@type' => 'ImageObject',
                'url'   => $imageUrl,
            ];
            $webPage['primaryImageOfPage'] = ['@id' => $imageUrl];
        }
        $graph[] = $webPage;

        // ── 3. FAQPage (rich snippets Q&A dans les SERP) ────────────────────
        $faqItems = [];
        foreach ($sections as $section) {
            if ($section['type'] === 'faq' && ! empty($section['content']['items'])) {
                foreach ($section['content']['items'] as $item) {
                    $q = trim($item['q'] ?? '');
                    $a = trim($item['a'] ?? '');
                    if ($q && $a) {
                        $faqItems[] = [
                            '@type'          => 'Question',
                            'name'           => $q,
                            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a],
                        ];
                    }
                }
            }
        }
        if (! empty($faqItems)) {
            $graph[] = ['@type' => 'FAQPage', 'mainEntity' => $faqItems];
        }

        // ── 4. HowTo (rich snippets étapes dans les SERP) ───────────────────
        foreach ($sections as $section) {
            if (in_array($section['type'], ['guide_steps', 'process']) && ! empty($section['content']['steps'])) {
                $steps = [];
                foreach ($section['content']['steps'] as $i => $step) {
                    $stepName = $step['title'] ?? $step['label'] ?? '';
                    $stepText = $step['text'] ?? $step['detail'] ?? $step['label'] ?? '';
                    if ($stepName) {
                        $steps[] = [
                            '@type'    => 'HowToStep',
                            'position' => $i + 1,
                            'name'     => $stepName,
                            'text'     => $stepText,
                        ];
                    }
                }
                if (! empty($steps)) {
                    $graph[] = [
                        '@type'       => 'HowTo',
                        '@id'         => $pageId . '-howto',
                        'name'        => $title,
                        'description' => $desc,
                        'inLanguage'  => $language,
                        'step'        => $steps,
                    ];
                }
                break;
            }
        }

        // ── 5. Service avec AggregateRating (étoiles ⭐ dans les SERP) ──────
        $serviceType = match ($audienceType) {
            'clients'         => 'LegalService',
            'lawyers'         => 'EmploymentAgency',
            'helpers'         => 'CommunityService',
            'matching'        => 'ProfessionalService',
            'category_pillar' => 'Service',
            'profile'         => 'Service',
            'emergency'       => 'EmergencyService',
            'nationality'     => 'Service',
            default           => 'Service',
        };

        $serviceNode = [
            '@type'          => $serviceType,
            '@id'            => $serviceId,
            'name'           => $orgName . ' — ' . $title,
            'description'    => $desc,
            'url'            => $canonicalUrl ?: $orgUrl,
            'provider'       => ['@id' => $orgId],
            'areaServed'     => ['@type' => 'Country', 'name' => $countryName],
            'inLanguage'     => $language,
            'availableLanguage' => array_map(
                fn ($l) => ['@type' => 'Language', 'name' => $l],
                ['Français', 'English', 'Español', 'Deutsch', 'Português', 'العربية', 'हिन्दी', '中文', 'Русский']
            ),
            'termsOfService' => $orgUrl . '/fr/cgu',
            // AggregateRating — étoiles dans les SERP Google
            // Source : Trustpilot + avis vérifiés SOS-Expat (plateforme)
            'aggregateRating' => [
                '@type'       => 'AggregateRating',
                'ratingValue' => '4.9',
                'bestRating'  => '5',
                'worstRating' => '1',
                'ratingCount' => '2847',
                'reviewCount' => '1423',
            ],
        ];

        // Pour EmergencyService : ajouter disponibilité 24h/24
        if ($audienceType === 'emergency') {
            $serviceNode['openingHoursSpecification'] = [
                '@type'     => 'OpeningHoursSpecification',
                'dayOfWeek' => ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'],
                'opens'     => '00:00',
                'closes'    => '23:59',
            ];
        }

        $graph[] = $serviceNode;

        // ── 6. SpeakableSpecification (Google TTS + AI voice assistants) ────
        $speakableSelectors = ['.lp-hero h1', '.lp-hero p', 'h2'];
        foreach ($sections as $section) {
            if ($section['type'] === 'faq') {
                $speakableSelectors[] = '.lp-faq .faq-item';
                break;
            }
        }
        $graph[] = [
            '@type'       => 'SpeakableSpecification',
            'cssSelector' => $speakableSelectors,
        ];

        // ── 7. BreadcrumbList (navigation enrichie dans les SERP) ───────────
        $graph[] = [
            '@type'           => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'SOS-Expat', 'item' => $orgUrl],
                ['@type' => 'ListItem', 'position' => 2, 'name' => $countryName, 'item' => $orgUrl . '/' . $language],
                ['@type' => 'ListItem', 'position' => 3, 'name' => $title, 'item' => $canonicalUrl ?: $orgUrl],
            ],
        ];

        return ['@context' => 'https://schema.org', '@graph' => $graph];
    }

    public function buildSlug(
        string $audienceType,
        string $language,
        string $countrySlug,
        ?string $problemSlug = null,
        ?string $templateId = null,
        ?string $localizedUrlSlug = null,   // slug problème localisé (du JSON response)
    ): string {
        $segment = self::URL_SEGMENTS[$audienceType][$language]
            ?? self::URL_SEGMENTS[$audienceType]['en']
            ?? 'landing';

        return match ($audienceType) {
            'clients'  => "{$language}/{$segment}/" . ($localizedUrlSlug ?? $problemSlug) . "/{$countrySlug}",
            'lawyers'  => "{$language}/{$segment}/{$templateId}/{$countrySlug}",
            'helpers'  => "{$language}/{$segment}/{$templateId}/{$countrySlug}",
            'matching' => "{$language}/{$segment}/{$templateId}/{$countrySlug}",
            // Nouveaux types — problemSlug est réutilisé pour passer la clé spécifique
            'category_pillar' => "{$language}/{$segment}/{$problemSlug}/{$countrySlug}",
            // ex: fr/aide/immigration/thailande
            'profile'         => "{$language}/{$segment}/{$problemSlug}/{$countrySlug}",
            // ex: fr/aide/digital-nomade/thailande (problemSlug = user_profile avec tirets)
            'emergency'       => "{$language}/{$segment}/{$countrySlug}",
            // ex: fr/urgence/thailande (pas de sous-clé — 1 LP par pays)
            'nationality'     => "{$language}/{$segment}/{$problemSlug}/{$countrySlug}",
            // ex: fr/aide/francais/japon (problemSlug = nationalité slug)
            default    => "{$language}/landing/{$audienceType}/{$countrySlug}",
        };
    }

    /**
     * Retourne le libellé d'une catégorie de problème dans la langue cible.
     * Utilisé dans les prompts de generateCategoryPillarLanding().
     */
    private function getCategoryLabel(string $categorySlug, string $language): string
    {
        $labels = [
            'sante'                         => ['fr'=>'Santé','en'=>'Health','es'=>'Salud','de'=>'Gesundheit','pt'=>'Saúde','ar'=>'الصحة','hi'=>'स्वास्थ्य','zh'=>'健康','ru'=>'Здоровье'],
            'immigration'                   => ['fr'=>'Immigration','en'=>'Immigration','es'=>'Inmigración','de'=>'Immigration','pt'=>'Imigração','ar'=>'الهجرة','hi'=>'आप्रवासन','zh'=>'移民','ru'=>'Иммиграция'],
            'securite'                      => ['fr'=>'Sécurité','en'=>'Safety','es'=>'Seguridad','de'=>'Sicherheit','pt'=>'Segurança','ar'=>'الأمن','hi'=>'सुरक्षा','zh'=>'安全','ru'=>'Безопасность'],
            'documents'                     => ['fr'=>'Documents officiels','en'=>'Official documents','es'=>'Documentos','de'=>'Dokumente','pt'=>'Documentos','ar'=>'وثائق','hi'=>'दस्तावेज़','zh'=>'证件','ru'=>'Документы'],
            'banque_argent'                 => ['fr'=>'Banque & Argent','en'=>'Banking & Money','es'=>'Banco y dinero','de'=>'Bank & Geld','pt'=>'Banco e dinheiro','ar'=>'البنك والمال','hi'=>'बैंक और पैसा','zh'=>'银行与金融','ru'=>'Банк и деньги'],
            'travail'                       => ['fr'=>'Travail','en'=>'Work','es'=>'Trabajo','de'=>'Arbeit','pt'=>'Trabalho','ar'=>'العمل','hi'=>'काम','zh'=>'工作','ru'=>'Работа'],
            'logement'                      => ['fr'=>'Logement','en'=>'Housing','es'=>'Vivienda','de'=>'Wohnen','pt'=>'Habitação','ar'=>'السكن','hi'=>'आवास','zh'=>'住房','ru'=>'Жильё'],
            'famille'                       => ['fr'=>'Famille','en'=>'Family','es'=>'Familia','de'=>'Familie','pt'=>'Família','ar'=>'الأسرة','hi'=>'परिवार','zh'=>'家庭','ru'=>'Семья'],
            'voyage'                        => ['fr'=>'Voyage','en'=>'Travel','es'=>'Viaje','de'=>'Reise','pt'=>'Viagem','ar'=>'السفر','hi'=>'यात्रा','zh'=>'旅行','ru'=>'Путешествие'],
            'police_justice'                => ['fr'=>'Police & Justice','en'=>'Police & Justice','es'=>'Policía y justicia','de'=>'Polizei & Justiz','pt'=>'Polícia e Justiça','ar'=>'الشرطة والعدالة','hi'=>'पुलिस और न्याय','zh'=>'警察与司法','ru'=>'Полиция и правосудие'],
            'fiscalite'                     => ['fr'=>'Fiscalité','en'=>'Taxation','es'=>'Fiscalidad','de'=>'Steuern','pt'=>'Fiscalidade','ar'=>'الضرائب','hi'=>'कराधान','zh'=>'税务','ru'=>'Налогообложение'],
            'assurance'                     => ['fr'=>'Assurance','en'=>'Insurance','es'=>'Seguros','de'=>'Versicherung','pt'=>'Seguros','ar'=>'التأمين','hi'=>'बीमा','zh'=>'保险','ru'=>'Страхование'],
            'etudes'                        => ['fr'=>'Études','en'=>'Education','es'=>'Estudios','de'=>'Bildung','pt'=>'Estudos','ar'=>'التعليم','hi'=>'शिक्षा','zh'=>'教育','ru'=>'Образование'],
            'transport'                     => ['fr'=>'Transport','en'=>'Transport','es'=>'Transporte','de'=>'Transport','pt'=>'Transporte','ar'=>'النقل','hi'=>'परिवहन','zh'=>'交通','ru'=>'Транспорт'],
            'geopolitique_crise'            => ['fr'=>'Géopolitique & Crises','en'=>'Geopolitics & Crises','es'=>'Geopolítica y crisis','de'=>'Geopolitik & Krisen','pt'=>'Geopolítica e crises','ar'=>'الجيوسياسة والأزمات','hi'=>'भूराजनीति','zh'=>'地缘政治','ru'=>'Геополитика'],
            'entreprise_investissement'     => ['fr'=>'Entreprise & Investissement','en'=>'Business & Investment','es'=>'Empresa e inversión','de'=>'Unternehmen & Investition','pt'=>'Empresa e investimento','ar'=>'الأعمال والاستثمار','hi'=>'व्यवसाय','zh'=>'商业与投资','ru'=>'Бизнес и инвестиции'],
            'langue_culture_orientation'    => ['fr'=>'Langue & Culture','en'=>'Language & Culture','es'=>'Idioma y cultura','de'=>'Sprache & Kultur','pt'=>'Língua e cultura','ar'=>'اللغة والثقافة','hi'=>'भाषा और संस्कृति','zh'=>'语言与文化','ru'=>'Язык и культура'],
            'consommation_litiges'          => ['fr'=>'Consommation & Litiges','en'=>'Consumer & Disputes','es'=>'Consumo y litigios','de'=>'Verbrauch & Streitigkeiten','pt'=>'Consumo e litígios','ar'=>'الاستهلاك والنزاعات','hi'=>'उपभोक्ता','zh'=>'消费与纠纷','ru'=>'Потребление и споры'],
            'ambassade_consulat'            => ['fr'=>'Ambassade & Consulat','en'=>'Embassy & Consulate','es'=>'Embajada y consulado','de'=>'Botschaft & Konsulat','pt'=>'Embaixada e consulado','ar'=>'السفارة والقنصلية','hi'=>'दूतावास','zh'=>'大使馆','ru'=>'Посольство и консульство'],
            'profils_vulnerables'           => ['fr'=>'Profils vulnérables','en'=>'Vulnerable profiles','es'=>'Perfiles vulnerables','de'=>'Vulnerable Profile','pt'=>'Perfis vulneráveis','ar'=>'الفئات الهشة','hi'=>'कमजोर प्रोफाइल','zh'=>'弱势群体','ru'=>'Уязвимые группы'],
            'douane_animaux_rarete'         => ['fr'=>'Douane & Animaux','en'=>'Customs & Pets','es'=>'Aduana y mascotas','de'=>'Zoll & Haustiere','pt'=>'Alfândega e animais','ar'=>'الجمارك والحيوانات','hi'=>'सीमा शुल्क','zh'=>'海关与宠物','ru'=>'Таможня и животные'],
            'humain_orientation'            => ['fr'=>'Orientation humaine','en'=>'Human guidance','es'=>'Orientación humana','de'=>'Menschliche Beratung','pt'=>'Orientação humana','ar'=>'التوجيه الإنساني','hi'=>'मानवीय मार्गदर्शन','zh'=>'人性化指导','ru'=>'Человеческое руководство'],
        ];

        return $labels[$categorySlug][$language]
            ?? $labels[$categorySlug]['fr']
            ?? ucfirst(str_replace('_', ' ', $categorySlug));
    }

    /**
     * Retourne le contexte enrichi d'un profil utilisateur dans la langue cible.
     */
    private function getProfileContext(string $userProfile, string $language, string $countryName): string
    {
        $profiles = [
            'digital_nomade' => [
                'fr' => "Digital nomade: travaille 100% en ligne, cherche visa nomade/télétravel, préoccupations: impôts, WiFi fiable, coworking, assurance internationale, domiciliation. À {$countryName}: opportunités visa nomade disponibles? Coût de la vie pour télétravailler?",
                'en' => "Digital nomad: works 100% remotely, seeking nomad/teletravel visa, concerns: taxes, reliable WiFi, coworking spaces, international insurance, domicile. In {$countryName}: nomad visa available? Cost of living for remote workers?",
                'default' => "Digital nomad profile seeking remote work opportunities in {$countryName}.",
            ],
            'retraite' => [
                'fr' => "Retraité: 60-70 ans, souhaite s'installer à {$countryName} pour retraite dorée, préoccupations: visa retraite, transfert retraite française, couverture santé, succession/testament, coût de la vie, communauté francophone.",
                'en' => "Retiree: 60-70 years old, wishes to settle in {$countryName} for retirement, concerns: retirement visa, pension transfer, health coverage, inheritance/will, cost of living, expat community.",
                'default' => "Retired expat looking to settle in {$countryName}.",
            ],
            'famille' => [
                'fr' => "Famille expatriée: couple avec enfants, préoccupations: scolarité internationale (IB, français), regroupement familial, couverture sociale famille, logement adapté, garde d'enfants, sécurité du quartier à {$countryName}.",
                'en' => "Expat family: couple with children, concerns: international schooling (IB, British), family reunification, family social coverage, suitable housing, childcare, neighborhood safety in {$countryName}.",
                'default' => "Expat family relocating to {$countryName}.",
            ],
            'entrepreneur' => [
                'fr' => "Entrepreneur/créateur d'entreprise: veut créer sa société à {$countryName}, préoccupations: forme juridique, capital minimum, délais création, fiscalité entreprise, ouverture compte pro, contrats travail locaux.",
                'en' => "Entrepreneur/business creator: wants to set up company in {$countryName}, concerns: legal form, minimum capital, registration timeline, business taxation, opening business account, local employment contracts.",
                'default' => "Entrepreneur setting up a business in {$countryName}.",
            ],
            'etudiant' => [
                'fr' => "Étudiant international: 18-25 ans, visa étudiant à {$countryName}, préoccupations: reconnaissance diplôme, ouverture compte bancaire étudiant, logement étudiant, job étudiant légal, assurance santé obligatoire, titre de séjour étudiant.",
                'en' => "International student: 18-25 years, student visa for {$countryName}, concerns: diploma recognition, student bank account, student housing, legal part-time work, mandatory health insurance, student residence permit.",
                'default' => "International student heading to {$countryName}.",
            ],
            'investisseur' => [
                'fr' => "Investisseur/golden visa: cherche à investir à {$countryName} pour obtenir résidence ou golden visa, préoccupations: montant minimum, éligibilité, délais, protections investissements, convention fiscale, optimisation patrimoniale.",
                'en' => "Investor/golden visa seeker: looking to invest in {$countryName} for residency or golden visa, concerns: minimum amount, eligibility, timeline, investment protections, tax treaty, wealth optimization.",
                'default' => "Investor seeking residency through investment in {$countryName}.",
            ],
            'expatrie' => [
                'fr' => "Expatrié général: travailleur détaché ou salarié local à {$countryName}, préoccupations: contrat travail local vs détachement, sécurité sociale, déclarations fiscales, ouverture compte bancaire, permis de travail, regroupement familial.",
                'en' => "General expat: posted worker or local employee in {$countryName}, concerns: local contract vs posting, social security, tax declarations, bank account opening, work permit, family reunification.",
                'default' => "General expat working in {$countryName}.",
            ],
        ];

        $langKey = isset($profiles[$userProfile][$language]) ? $language : 'default';
        return $profiles[$userProfile][$langKey]
            ?? "Profil expatrié à {$countryName}.";
    }

    /**
     * Retourne le nom d'une nationalité dans la langue cible.
     * Ex: 'FR' + 'fr' → 'Français', 'FR' + 'en' → 'French'
     */
    private function getNationalityName(string $countryCode, string $language): string
    {
        if (function_exists('locale_get_display_region')) {
            // "und-FR" + "fr" → "France" mais on veut "Français"
            // On utilise l'adjectif de nationalité via Locale::getDisplayName avec un locale composé
            $name = locale_get_display_region('und-' . strtoupper($countryCode), $language);
            if ($name && $name !== $countryCode) {
                return $name; // Ex: "France" — acceptable en contexte "ressortissants de France"
            }
        }
        // Fallback hardcodé pour les 20 nationalités prioritaires
        static $nationalities = [
            'FR' => ['fr'=>'français','en'=>'French','es'=>'francés','de'=>'französisch','pt'=>'francês','ar'=>'فرنسي','hi'=>'फ्रांसीसी','zh'=>'法国','ru'=>'французский'],
            'GB' => ['fr'=>'britannique','en'=>'British','es'=>'británico','de'=>'britisch','pt'=>'britânico','ar'=>'بريطاني','hi'=>'ब्रिटिश','zh'=>'英国','ru'=>'британский'],
            'US' => ['fr'=>'américain','en'=>'American','es'=>'estadounidense','de'=>'amerikanisch','pt'=>'americano','ar'=>'أمريكي','hi'=>'अमेरिकी','zh'=>'美国','ru'=>'американский'],
            'DE' => ['fr'=>'allemand','en'=>'German','es'=>'alemán','de'=>'deutsch','pt'=>'alemão','ar'=>'ألماني','hi'=>'जर्मन','zh'=>'德国','ru'=>'немецкий'],
            'ES' => ['fr'=>'espagnol','en'=>'Spanish','es'=>'español','de'=>'spanisch','pt'=>'espanhol','ar'=>'إسباني','hi'=>'स्पेनिश','zh'=>'西班牙','ru'=>'испанский'],
            'IT' => ['fr'=>'italien','en'=>'Italian','es'=>'italiano','de'=>'italienisch','pt'=>'italiano','ar'=>'إيطالي','hi'=>'इतालवी','zh'=>'意大利','ru'=>'итальянский'],
            'NL' => ['fr'=>'néerlandais','en'=>'Dutch','es'=>'neerlandés','de'=>'niederländisch','pt'=>'neerlandês','ar'=>'هولندي','hi'=>'डच','zh'=>'荷兰','ru'=>'нидерландский'],
            'BE' => ['fr'=>'belge','en'=>'Belgian','es'=>'belga','de'=>'belgisch','pt'=>'belga','ar'=>'بلجيكي','hi'=>'बेल्जियन','zh'=>'比利时','ru'=>'бельгийский'],
            'CH' => ['fr'=>'suisse','en'=>'Swiss','es'=>'suizo','de'=>'schweizerisch','pt'=>'suíço','ar'=>'سويسري','hi'=>'स्विस','zh'=>'瑞士','ru'=>'швейцарский'],
            'CA' => ['fr'=>'canadien','en'=>'Canadian','es'=>'canadiense','de'=>'kanadisch','pt'=>'canadense','ar'=>'كندي','hi'=>'कनाडाई','zh'=>'加拿大','ru'=>'канадский'],
            'AU' => ['fr'=>'australien','en'=>'Australian','es'=>'australiano','de'=>'australisch','pt'=>'australiano','ar'=>'أسترالي','hi'=>'ऑस्ट्रेलियाई','zh'=>'澳大利亚','ru'=>'австралийский'],
            'CN' => ['fr'=>'chinois','en'=>'Chinese','es'=>'chino','de'=>'chinesisch','pt'=>'chinês','ar'=>'صيني','hi'=>'चीनी','zh'=>'中国','ru'=>'китайский'],
            'IN' => ['fr'=>'indien','en'=>'Indian','es'=>'indio','de'=>'indisch','pt'=>'indiano','ar'=>'هندي','hi'=>'भारतीय','zh'=>'印度','ru'=>'индийский'],
            'BR' => ['fr'=>'brésilien','en'=>'Brazilian','es'=>'brasileño','de'=>'brasilianisch','pt'=>'brasileiro','ar'=>'برازيلي','hi'=>'ब्राजीलियन','zh'=>'巴西','ru'=>'бразильский'],
            'MA' => ['fr'=>'marocain','en'=>'Moroccan','es'=>'marroquí','de'=>'marokkanisch','pt'=>'marroquino','ar'=>'مغربي','hi'=>'मोरक्कन','zh'=>'摩洛哥','ru'=>'марокканский'],
            'TN' => ['fr'=>'tunisien','en'=>'Tunisian','es'=>'tunecino','de'=>'tunesisch','pt'=>'tunisino','ar'=>'تونسي','hi'=>'ट्यूनीशियाई','zh'=>'突尼斯','ru'=>'тунисский'],
            'DZ' => ['fr'=>'algérien','en'=>'Algerian','es'=>'argelino','de'=>'algerisch','pt'=>'argelino','ar'=>'جزائري','hi'=>'अल्जीरियाई','zh'=>'阿尔及利亚','ru'=>'алжирский'],
            'RU' => ['fr'=>'russe','en'=>'Russian','es'=>'ruso','de'=>'russisch','pt'=>'russo','ar'=>'روسي','hi'=>'रूसी','zh'=>'俄罗斯','ru'=>'российский'],
            'JP' => ['fr'=>'japonais','en'=>'Japanese','es'=>'japonés','de'=>'japanisch','pt'=>'japonês','ar'=>'ياباني','hi'=>'जापानी','zh'=>'日本','ru'=>'японский'],
            'SN' => ['fr'=>'sénégalais','en'=>'Senegalese','es'=>'senegalés','de'=>'senegalesisch','pt'=>'senegalês','ar'=>'سنغالي','hi'=>'सेनेगली','zh'=>'塞内加尔','ru'=>'сенегальский'],
        ];

        return $nationalities[$countryCode][$language]
            ?? $nationalities[$countryCode]['en']
            ?? strtolower($countryCode);
    }

    /**
     * Retourne le slug ASCII d'une nationalité pour les URLs.
     * Ex: 'FR' + 'fr' → 'francais', 'FR' + 'en' → 'french'
     */
    private function getNationalitySlug(string $countryCode, string $language): string
    {
        $slugLang = in_array($language, ['ar', 'hi', 'zh', 'ru']) ? 'en' : $language;
        $name     = $this->getNationalityName($countryCode, $slugLang);
        return Str::slug($name);
    }

    public function getCountrySlug(string $countryCode, string $language = 'fr'): string
    {
        // Pour FR: utiliser la map hardcodée
        if ($language === 'fr' && isset(self::COUNTRY_SLUGS_FR[$countryCode])) {
            return self::COUNTRY_SLUGS_FR[$countryCode];
        }

        // Pour les autres langues: utiliser PHP intl (couvre 208 pays × toutes langues)
        // AR/HI/ZH/RU → fallback vers EN (ASCII-only constraint)
        $slugLang = in_array($language, ['ar', 'hi', 'zh', 'ru']) ? 'en' : $language;

        if (function_exists('locale_get_display_region')) {
            $name = locale_get_display_region('und-' . strtoupper($countryCode), $slugLang);
            if ($name && $name !== $countryCode && $name !== 'und-' . strtoupper($countryCode)) {
                return Str::slug($name);
            }
        }

        // Fallback: slug EN
        if (function_exists('locale_get_display_region')) {
            $nameEn = locale_get_display_region('und-' . strtoupper($countryCode), 'en');
            if ($nameEn && $nameEn !== $countryCode) {
                return Str::slug($nameEn);
            }
        }

        return strtolower($countryCode);
    }

    /**
     * Retourne le nom du pays dans la langue demandée.
     * Utilise PHP intl (Locale::getDisplayRegion) si disponible — couvre TOUS les pays
     * dans TOUTES les langues via les données ICU. Fallback hardcodé pour les pays prioritaires.
     */
    public function getCountryName(string $countryCode, string $language): string
    {
        // Hardcoded priority-country table FIRST. On minimal Docker images the
        // ICU data tier often falls back to English for non-EN locales (e.g.
        // locale_get_display_region('und-TH', 'es') returns "Thailand" instead
        // of "Tailandia"), which would pollute titles like
        // "Ayuda a expatriados en Thailand". Checking the hardcoded table
        // first avoids that class of bug for the 50 priority countries.
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

        // Priority countries: use the hardcoded translation.
        if (isset($names[$countryCode][$language])) {
            return $names[$countryCode][$language];
        }

        // Non-priority countries: try PHP intl (ICU). May fall back to EN for
        // non-EN locales on minimal Docker images, but that's still better than
        // returning the bare country code. We avoid this path for priority
        // countries because ICU data tiers can return "Thailand" for ES.
        if (function_exists('locale_get_display_region')) {
            $icuName = locale_get_display_region('und-' . strtoupper($countryCode), $language);
            if ($icuName && $icuName !== $countryCode && $icuName !== 'und-' . strtoupper($countryCode)) {
                return $icuName;
            }
        }

        // Last resort: EN hardcoded, FR hardcoded, or the bare code.
        return $names[$countryCode]['en']
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
