<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Connects the 14-source system to ArticleGenerationService.
 *
 * Picks N "ready" items from generation_source_items for a given source,
 * adapts params per source type AND per item input_quality,
 * then dispatches GenerateArticleJob for each item.
 *
 * source  → determines content_type, blog_category, keyword strategy
 * item    → determines input_quality, source_content, topic
 */
class GenerateFromSourceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries   = 2;

    // Set to true to disable ALL generation (scraping-only mode).
    // Set to false to enable auto-generation via orchestrator.
    private const SKIP_ALL_GENERATION = false;

    // Sources to skip (only non-article sources like directory scraping)
    private const SKIP_SOURCES = [
        'annuaires',       // directory data, not articles
    ];

    public function __construct(
        public readonly string $sourceSlug,
        public readonly int    $quota = 3,
    ) {
        $this->onQueue('content');
    }

    public function handle(): void
    {
        // Pipeline is now scraping-only. All generation managed via UI tabs.
        if (self::SKIP_ALL_GENERATION) {
            Log::info("GenerateFromSourceJob: ALL generation disabled — pipeline is scraping-only. Source: [{$this->sourceSlug}]");
            return;
        }

        if (in_array($this->sourceSlug, self::SKIP_SOURCES, true)) {
            Log::info("GenerateFromSourceJob: [{$this->sourceSlug}] skipped");
            return;
        }

        // Check source exists and is not paused
        $category = DB::table('generation_source_categories')
            ->where('slug', $this->sourceSlug)
            ->first();

        if (!$category) {
            Log::warning("GenerateFromSourceJob: source '{$this->sourceSlug}' not found in DB");
            return;
        }

        $config = json_decode($category->config_json ?? '{}', true);
        if ($config['is_paused'] ?? false) {
            Log::info("GenerateFromSourceJob: source '{$this->sourceSlug}' is paused — skip");
            return;
        }

        // Effective quota: use argument OR source config
        $effectiveQuota = $this->quota > 0
            ? $this->quota
            : (int) ($config['daily_quota'] ?? 3);

        if ($effectiveQuota === 0) {
            Log::info("GenerateFromSourceJob: quota=0 for '{$this->sourceSlug}' — skip");
            return;
        }

        // Pick ready items randomly (diversifies content instead of always picking the same ones)
        $items = DB::table('generation_source_items')
            ->where('category_slug', $this->sourceSlug)
            ->where('processing_status', 'ready')
            ->where('is_cleaned', true)
            ->inRandomOrder()
            ->limit($effectiveQuota)
            ->get();

        if ($items->isEmpty()) {
            // Fallback: try unclean items if no clean ones available
            $items = DB::table('generation_source_items')
                ->where('category_slug', $this->sourceSlug)
                ->where('processing_status', 'ready')
                ->inRandomOrder()
                ->limit($effectiveQuota)
                ->get();
        }

        if ($items->isEmpty()) {
            Log::info("GenerateFromSourceJob: no ready items for '{$this->sourceSlug}'");
            return;
        }

        // Lock items immediately to prevent double-dispatch
        DB::table('generation_source_items')
            ->whereIn('id', $items->pluck('id')->toArray())
            ->update(['processing_status' => 'processing', 'updated_at' => now()]);

        $dispatched = 0;
        foreach ($items as $item) {
            try {
                $params = $this->buildParams($item);
                if ($params === null) {
                    DB::table('generation_source_items')
                        ->where('id', $item->id)
                        ->update(['processing_status' => 'ready']);
                    continue;
                }

                GenerateArticleJob::dispatch($params)->onQueue('content');
                $dispatched++;

                Log::info("GenerateFromSourceJob: dispatched item #{$item->id} [{$item->title}]", [
                    'source'       => $this->sourceSlug,
                    'content_type' => $params['content_type'],
                    'input_quality'=> $params['input_quality'],
                    'has_content'  => !empty($params['source_content']),
                ]);
            } catch (\Throwable $e) {
                Log::error("GenerateFromSourceJob: error on item #{$item->id}", [
                    'error' => $e->getMessage(),
                ]);
                // Roll back so item can be retried
                DB::table('generation_source_items')
                    ->where('id', $item->id)
                    ->update(['processing_status' => 'ready']);
            }
        }

        Log::info("GenerateFromSourceJob: done [{$this->sourceSlug}] dispatched={$dispatched}/{$items->count()}");
    }

    // ─────────────────────────────────────────────────────────────
    // Params builder
    // ─────────────────────────────────────────────────────────────

    /**
     * Build ArticleGenerationService $params for one item.
     * Two-level decision: source slug → content_type, item → input_quality + content.
     */
    private function buildParams(object $item): ?array
    {
        [$contentType] = $this->resolveContentType($this->sourceSlug);

        // Level 2: per-item input quality
        $inputQuality  = $item->input_quality ?? $this->deriveInputQuality($item);
        $sourceContent = $this->loadSourceContent($item, $inputQuality);
        $rawCountry    = $item->country ?? null;
        $rawTitle      = $item->title ?? '';

        // Normalize country to ISO 2-letter code (critical for Blog country association)
        // 4-step resolution (most reliable first):
        //   1. item->country normalized via map
        //   2. item->title scanned for any country name in the map
        //   3. item->original_title (if present) scanned likewise
        //   4. default to 'FR' (NEVER pick a random country — that's the
        //      historical bug that caused articles about Nouvelle-Calédonie
        //      to be tagged country=CH and serve Swiss images + Swiss
        //      internal links)
        $country = $rawCountry ? $this->normalizeCountryCode($rawCountry) : null;
        if (!$country && !empty($rawTitle)) {
            $country = $this->extractCountryFromText($rawTitle);
        }
        if (!$country && !empty($item->original_title ?? '')) {
            $country = $this->extractCountryFromText($item->original_title);
        }
        if (!$country) {
            // Deterministic safe default — better to be wrong consistently
            // (always FR) than randomly (one in fifteen chance per article).
            $country = 'FR';
            \Illuminate\Support\Facades\Log::warning('GenerateFromSourceJob: country could not be determined, defaulting to FR', [
                'item_id' => $item->id,
                'raw_country' => $rawCountry,
                'title' => mb_substr($rawTitle, 0, 80),
            ]);
        }

        $topic         = $this->buildTopic($item, $this->sourceSlug);
        $keywords      = $this->buildKeywords($item, $this->sourceSlug);

        // Resolve template variables {pays}, {country}, {annee}, {ville} in topic
        $countryName = $this->countryName($country);
        $topic = str_replace(
            ['{pays}', '{country}', '{Land}', '{país}', '{annee}', '{year}'],
            [$countryName, $countryName, $countryName, $countryName, date('Y'), date('Y')],
            $topic
        );

        return [
            'topic'               => $topic,
            'content_type'        => $contentType,
            'language'            => $item->language ?? 'fr',
            'country'             => $country,
            'keywords'            => $keywords,
            'source_slug'         => $this->sourceSlug,
            'source_item_id'      => $item->id,
            'input_quality'       => $inputQuality,
            'source_content'      => $sourceContent,
            'structured_data'     => $inputQuality === 'structured'
                                     ? json_decode($item->data_json ?? '{}', true)
                                     : null,
            'auto_internal_links' => true,
            'auto_affiliate_links'=> in_array($contentType, [
                'affiliation',
            ]),
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // Level 1 — Source slug → content_type
    // ─────────────────────────────────────────────────────────────

    private function resolveContentType(string $slug): array
    {
        return match ($slug) {
            'fiche-pays'       => ['guide'],
            'fiche-villes'     => ['guide_city'],
            'qa'               => ['qa'],
            'besoins-reels'    => ['qa_needs'],
            'fiches-pratiques' => ['article'],
            'comparatifs'      => ['comparative'],
            'temoignages'      => ['testimonial'],
            'chatters'         => ['outreach'],
            'bloggeurs'        => ['outreach'],
            'admin-groups'     => ['outreach'],
            'avocats'          => ['outreach'],
            'expats-aidants'   => ['outreach'],
            'affiliation'      => ['affiliation'],
            default            => ['article'],
        };
    }

    // ─────────────────────────────────────────────────────────────
    // Level 2 — Item data → input_quality, content, topic, keywords
    // ─────────────────────────────────────────────────────────────

    /**
     * Derive input_quality from item fields when not explicitly stored.
     */
    private function deriveInputQuality(object $item): string
    {
        if (!empty($item->data_json)) {
            $data = json_decode($item->data_json ?? '{}', true);
            if (!empty($data['article_ids']) || !empty($data['structured'])) {
                return 'structured';
            }
        }
        return ($item->word_count ?? 0) >= 200 ? 'full_content' : 'title_only';
    }

    /**
     * Build the article topic from item title + source context.
     */
    private function buildTopic(object $item, string $slug): string
    {
        $title   = trim($item->title ?? '');
        $country = $item->country ?? '';

        // Outreach sources: enrich title with programme context
        return match ($slug) {
            'chatters'     => "Devenir Chatter SOS-Expat"
                            . ($country ? " en {$country}" : '')
                            . " : guide complet, missions et revenus",
            'bloggeurs'    => "Devenir Bloggeur Affilié SOS-Expat"
                            . ($country ? " en {$country}" : '')
                            . " : comment gagner de l'argent avec votre blog",
            'admin-groups' => "Devenir Admin de Groupe WhatsApp SOS-Expat"
                            . ($country ? " en {$country}" : '')
                            . " : rôle, missions et rémunération",
            default        => !empty($title)
                            ? $title
                            : "Guide complet" . ($country ? " — {$country}" : ''),
        };
    }

    /**
     * Build keyword array tuned per source type.
     */
    private function buildKeywords(object $item, string $slug): array
    {
        $keywords = [];
        $country  = $item->country ?? '';
        $theme    = $item->theme   ?? '';
        $title    = $item->title   ?? '';

        // Primary keyword from title
        if (!empty($title)) {
            $keywords[] = mb_strtolower(trim(strip_tags($title)));
        }

        // Country secondary
        if ($country) {
            $keywords[] = "expatrié {$country}";
        }

        // Source-specific keyword set
        $extra = match ($slug) {
            'fiche-pays'       => ['guide expatrié', 'vivre à l\'étranger', "s'installer {$country}"],
            'fiche-villes'     => ['vivre en ville expatrié', 'logement expat', "déménager {$country}"],
            'qa'               => ['question expatrié', 'conseil expat', 'aide juridique'],
            'besoins-reels'    => ['besoin expat', 'aide expatrié', 'problème à l\'étranger'],
            'fiches-pratiques' => ['démarche pratique', 'guide pratique expat'],
            'comparatifs'      => ['comparatif', 'meilleur choix', 'quelle option choisir'],
            'temoignages'      => ['témoignage expatrié', 'expérience à l\'étranger'],
            'chatters'         => ['chatter rémunéré', 'gagner argent téléphone', 'travail flexible domicile'],
            'bloggeurs'        => ['blogueur affilié', 'gagner argent blog', 'revenu passif blog'],
            'admin-groups'     => ['admin WhatsApp rémunéré', 'gérer communauté expat'],
            'avocats'          => ['avocat expatrié', 'aide juridique', 'conseil juridique international'],
            'expats-aidants'   => ['services expat', 'assistance expatriation', 'prestataire expat'],
            'affiliation'      => ['affiliation', 'lien partenaire', 'recommandation rémunérée'],
            default            => [],
        };

        $keywords = array_merge($keywords, array_filter($extra));

        if (!empty($theme)) {
            $keywords[] = $theme;
        }

        return array_values(array_unique(array_filter($keywords)));
    }

    /**
     * Load source article/question content for full_content items.
     * Returns null for title_only and structured items.
     */
    private function loadSourceContent(object $item, string $inputQuality): ?string
    {
        if ($inputQuality !== 'full_content' || empty($item->source_id)) {
            return null;
        }

        if ($item->source_type === 'article') {
            $row = DB::table('content_articles')
                ->where('id', $item->source_id)
                ->select('content_text', 'title', 'meta_description')
                ->first();

            return ($row && !empty($row->content_text)) ? $row->content_text : null;
        }

        if ($item->source_type === 'question') {
            $row = DB::table('content_questions')
                ->where('id', $item->source_id)
                ->select('title', 'country', 'replies', 'views')
                ->first();

            if ($row) {
                return "Question expat : {$row->title}\n"
                     . "Pays : {$row->country}\n"
                     . "Vues : {$row->views} · Réponses : {$row->replies}";
            }
        }

        return null;
    }

    // ─────────────────────────────────────────────────────────────
    // Country normalization — converts any format to ISO 2-letter code
    // ─────────────────────────────────────────────────────────────

    private static ?array $countryMap = null;

    private function normalizeCountryCode(string $input): ?string
    {
        $input = trim($input);

        // Already a 2-letter code
        if (preg_match('/^[A-Z]{2}$/', strtoupper($input))) {
            return strtoupper($input);
        }

        // Fix unicode escapes first: \u00e9 → é
        $input = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($m) {
            return mb_convert_encoding(pack('H*', $m[1]), 'UTF-8', 'UCS-2BE');
        }, $input);

        // Build mapping from name → code (cached in static property)
        if (self::$countryMap === null) {
            self::$countryMap = [];
            $names = [
                'AF'=>['Afghanistan'],'AL'=>['Albanie','Albania'],'DZ'=>['Algérie','Algeria','Algerie'],
                'AD'=>['Andorre','Andorra'],'AO'=>['Angola'],'AE'=>['Émirats arabes unis','UAE','Emirats'],
                'AR'=>['Argentine','Argentina'],'AU'=>['Australie','Australia'],'AT'=>['Autriche','Austria'],
                'BE'=>['Belgique','Belgium'],'BJ'=>['Bénin','Benin'],'BO'=>['Bolivie','Bolivia'],
                'BR'=>['Brésil','Brazil','Bresil'],'BG'=>['Bulgarie','Bulgaria'],
                'BF'=>['Burkina Faso'],'BI'=>['Burundi'],'KH'=>['Cambodge','Cambodia'],
                'CM'=>['Cameroun','Cameroon'],'CA'=>['Canada'],'CF'=>['Centrafrique'],
                'CL'=>['Chili','Chile'],'CN'=>['Chine','China'],'CO'=>['Colombie','Colombia'],
                'KM'=>['Comores','Comoros'],'CG'=>['Congo'],'CD'=>['RDC','Congo-Kinshasa'],
                'KR'=>['Corée du Sud','South Korea','Coree du Sud'],'CI'=>['Côte d\'Ivoire','Cote d\'Ivoire','Ivory Coast'],
                'HR'=>['Croatie','Croatia'],'CU'=>['Cuba'],'DK'=>['Danemark','Denmark'],
                'DJ'=>['Djibouti'],'EG'=>['Égypte','Egypt','Egypte'],'ES'=>['Espagne','Spain'],
                'EE'=>['Estonie','Estonia'],'US'=>['États-Unis','USA','Etats-Unis','United States'],
                'ET'=>['Éthiopie','Ethiopia'],'FI'=>['Finlande','Finland'],'FR'=>['France'],
                'GA'=>['Gabon'],'GE'=>['Géorgie','Georgia','Georgie'],'GH'=>['Ghana'],
                'GR'=>['Grèce','Greece','Grece'],'GT'=>['Guatemala'],'GN'=>['Guinée','Guinea'],
                'HT'=>['Haïti','Haiti'],'HN'=>['Honduras'],'HK'=>['Hong Kong'],
                'HU'=>['Hongrie','Hungary'],'IN'=>['Inde','India'],'ID'=>['Indonésie','Indonesia','Indonesie'],
                'IQ'=>['Irak','Iraq'],'IR'=>['Iran'],'IE'=>['Irlande','Ireland'],
                'IS'=>['Islande','Iceland'],'IL'=>['Israël','Israel'],'IT'=>['Italie','Italy'],
                'JM'=>['Jamaïque','Jamaica'],'JP'=>['Japon','Japan'],'JO'=>['Jordanie','Jordan'],
                'KE'=>['Kenya'],'KW'=>['Koweït','Kuwait'],'LA'=>['Laos'],
                'LV'=>['Lettonie','Latvia'],'LB'=>['Liban','Lebanon'],'LY'=>['Libye','Libya'],
                'LT'=>['Lituanie','Lithuania'],'LU'=>['Luxembourg'],'MG'=>['Madagascar'],
                'MY'=>['Malaisie','Malaysia'],'ML'=>['Mali'],'MT'=>['Malte','Malta'],
                'MA'=>['Maroc','Morocco'],'MU'=>['Maurice','Île Maurice','Ile Maurice','Mauritius'],
                'MR'=>['Mauritanie','Mauritania'],'MX'=>['Mexique','Mexico'],
                'MD'=>['Moldavie','Moldova'],'MC'=>['Monaco'],'MN'=>['Mongolie','Mongolia'],
                'ME'=>['Monténégro','Montenegro'],'MZ'=>['Mozambique'],
                'MM'=>['Myanmar','Birmanie'],'NA'=>['Namibie','Namibia'],
                'NP'=>['Népal','Nepal'],'NI'=>['Nicaragua'],'NE'=>['Niger'],
                'NG'=>['Nigeria','Nigéria'],'NO'=>['Norvège','Norway','Norvege'],
                'NZ'=>['Nouvelle-Zélande','New Zealand'],'OM'=>['Oman'],
                'UG'=>['Ouganda','Uganda'],'UZ'=>['Ouzbékistan','Uzbekistan'],
                'PK'=>['Pakistan'],'PA'=>['Panama'],'PY'=>['Paraguay'],
                'NL'=>['Pays-Bas','Netherlands'],'PE'=>['Pérou','Peru','Perou'],
                'PH'=>['Philippines'],'PL'=>['Pologne','Poland'],'PT'=>['Portugal'],
                'QA'=>['Qatar'],'RO'=>['Roumanie','Romania'],'GB'=>['Royaume-Uni','UK','United Kingdom'],
                'RU'=>['Russie','Russia'],'RW'=>['Rwanda'],'SN'=>['Sénégal','Senegal'],
                'RS'=>['Serbie','Serbia'],'SG'=>['Singapour','Singapore'],
                'SK'=>['Slovaquie','Slovakia'],'SI'=>['Slovénie','Slovenia'],
                'SD'=>['Soudan','Sudan'],'LK'=>['Sri Lanka'],'SE'=>['Suède','Sweden','Suede'],
                'CH'=>['Suisse','Switzerland'],'SR'=>['Suriname'],
                'SY'=>['Syrie','Syria'],'TW'=>['Taïwan','Taiwan'],'TZ'=>['Tanzanie','Tanzania'],
                'TD'=>['Tchad','Chad'],'CZ'=>['Tchéquie','Czech Republic','Republique Tcheque'],
                'TH'=>['Thaïlande','Thailand','Thailande'],'TG'=>['Togo'],
                'TN'=>['Tunisie','Tunisia'],'TR'=>['Turquie','Turkey'],
                'UA'=>['Ukraine'],'UY'=>['Uruguay'],'VE'=>['Venezuela'],
                'VN'=>['Vietnam','Viêt Nam'],'YE'=>['Yémen','Yemen'],
                'ZM'=>['Zambie','Zambia'],'ZW'=>['Zimbabwe'],
                'ST'=>['São Tomé-et-Príncipe','Sao Tomé et Principe','Sao Tome'],
                'YT'=>['Mayotte'],'PF'=>['Polynésie française','Polynesie francaise','French Polynesia'],
                'GI'=>['Gibraltar'],'AW'=>['Aruba'],
                'SL'=>['Sierra Leone'],'BB'=>['Barbade','Barbados'],
                'MO'=>['Macao','Macau'],'SB'=>['Îles Salomon','Iles Salomon'],
                // ── French overseas territories (DOM-TOM) ──
                'NC'=>['Nouvelle-Calédonie','Nouvelle-Caledonie','New Caledonia'],
                'RE'=>['La Réunion','La Reunion','Réunion','Reunion','Reunion Island'],
                'MQ'=>['Martinique'],
                'GP'=>['Guadeloupe'],
                'GF'=>['Guyane','Guyane française','Guyane francaise','French Guiana'],
                'PM'=>['Saint-Pierre-et-Miquelon','Saint Pierre et Miquelon'],
                'WF'=>['Wallis-et-Futuna','Wallis et Futuna'],
                'BL'=>['Saint-Barthélemy','Saint Barthelemy'],
                'MF'=>['Saint-Martin'],
                'TF'=>['Terres australes','TAAF'],
                // ── United Kingdom constituent countries (NI commonly grouped with GB) ──
                // 'Irlande du Nord' is part of the UK politically, so we map it to GB.
                // Source items historically used "Irlande du Nord" as a label.
                'GB'=>['Royaume-Uni','UK','United Kingdom','Grande-Bretagne','Irlande du Nord','Northern Ireland','Écosse','Ecosse','Scotland','Pays de Galles','Wales'],
                // ── Misc additions for source items observed in production ──
                'BS'=>['Bahamas'],'BZ'=>['Belize'],'BW'=>['Botswana'],
                'CR'=>['Costa Rica'],'DO'=>['République dominicaine','Republique dominicaine','Dominican Republic'],
                'EC'=>['Équateur','Equateur','Ecuador'],'SV'=>['Salvador','El Salvador'],
                'FJ'=>['Fidji','Fiji'],'GM'=>['Gambie','Gambia'],
                'IS'=>['Islande','Iceland'],'KZ'=>['Kazakhstan'],
                'MK'=>['Macédoine','Macedonia','North Macedonia'],
                'MV'=>['Maldives'],'MT'=>['Malte','Malta'],
                'NA'=>['Namibie','Namibia'],'PG'=>['Papouasie','Papua New Guinea'],
                'PY'=>['Paraguay'],'SC'=>['Seychelles'],
                'SO'=>['Somalie','Somalia'],'ZA'=>['Afrique du Sud','South Africa'],
                'VU'=>['Vanuatu'],'ZW'=>['Zimbabwe'],
            ];
            foreach ($names as $code => $variants) {
                foreach ($variants as $name) {
                    self::$countryMap[mb_strtolower($name)] = $code;
                }
            }
        }

        return self::$countryMap[mb_strtolower($input)] ?? null;
    }

    /**
     * Scan free text (article title, original_title, description) for any
     * country name present in the countryMap. Used as a fallback when the
     * source item lacks a clean country field.
     *
     * Matches longest names first so "Nouvelle-Calédonie" wins over a stray
     * "Calédonie" alias, and word boundaries prevent false hits like
     * "France" matching inside "Francais".
     */
    private function extractCountryFromText(?string $text): ?string
    {
        if (empty($text)) {
            return null;
        }

        // Make sure the map is built
        if (self::$countryMap === null) {
            $this->normalizeCountryCode('init');
        }

        $haystack = ' ' . mb_strtolower($text) . ' ';

        // Sort names by length DESC so the most specific match wins
        $names = array_keys(self::$countryMap);
        usort($names, fn($a, $b) => mb_strlen($b) - mb_strlen($a));

        foreach ($names as $name) {
            // Skip ultra-short or ambiguous tokens (>=4 chars to avoid "uk" / "us" inside random words)
            if (mb_strlen($name) < 4) {
                continue;
            }
            // Word-boundary-ish check using surrounding non-letter chars
            $pattern = '/(?<![\p{L}])' . preg_quote($name, '/') . '(?![\p{L}])/iu';
            if (preg_match($pattern, $haystack)) {
                return self::$countryMap[$name];
            }
        }

        return null;
    }

    private function countryName(string $code): string
    {
        // Reverse lookup: code → French name
        if (self::$countryMap === null) {
            $this->normalizeCountryCode('init'); // init the map
        }
        foreach (self::$countryMap as $name => $c) {
            if ($c === strtoupper($code)) {
                return mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
            }
        }
        return $code;
    }

    // ─────────────────────────────────────────────────────────────
    // Error handler
    // ─────────────────────────────────────────────────────────────

    public function failed(\Throwable $e): void
    {
        Log::error("GenerateFromSourceJob failed for '{$this->sourceSlug}'", [
            'error' => $e->getMessage(),
            'trace' => mb_substr($e->getTraceAsString(), 0, 500),
        ]);

        // Roll back any items stuck in "processing" from this run
        DB::table('generation_source_items')
            ->where('category_slug', $this->sourceSlug)
            ->where('processing_status', 'processing')
            ->where('updated_at', '>=', now()->subMinutes(10))
            ->update(['processing_status' => 'ready']);
    }
}
