<?php

namespace App\Jobs;

use App\Services\Content\ContentOrchestratorService;
use App\Services\Content\GenerationSchedulerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Auto-pilot worker — runs every 15 minutes via cron.
 *
 * Each cycle:
 * 1. Reads orchestrator config from DB (daily_target, %, status)
 * 2. Calculates how many articles to generate this cycle
 * 3. Determines which TYPE to generate (based on % distribution + what's been done today)
 * 4. Dispatches the actual generation job
 * 5. Records the generation
 * 6. Sends Telegram alert on error
 *
 * Spreading logic: 15min intervals × ~16h active window = ~64 cycles/day
 * If daily_target = 50, that's ~0.78 articles per cycle → 1 article per cycle
 * If daily_target = 240, that's ~3.7 per cycle → 4 articles per cycle
 */
class RunOrchestratorCycleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600; // 10 minutes max per cycle
    public int $tries = 1;

    // Active generation window: 06:00 - 22:00 UTC (16 hours)
    private const ACTIVE_HOUR_START = 6;
    private const ACTIVE_HOUR_END = 22;
    private const CYCLES_PER_DAY = 64; // 16h × 4 cycles/hour

    public function handle(): void
    {
        $orchestrator = app(ContentOrchestratorService::class);
        $config = $orchestrator->getConfig();

        // Check if auto-pilot is active
        if ($config['status'] !== 'running' || !$config['auto_pilot']) {
            return;
        }

        // Check active hours (don't generate at night — looks unnatural to Google)
        $currentHour = (int) now()->format('H');
        if ($currentHour < self::ACTIVE_HOUR_START || $currentHour >= self::ACTIVE_HOUR_END) {
            return;
        }

        // Check daily quota
        $remaining = $config['daily_target'] - $config['today_generated'];
        if ($remaining <= 0) {
            Log::info('Orchestrator: daily target reached', ['generated' => $config['today_generated'], 'target' => $config['daily_target']]);
            return;
        }

        // Calculate articles for this cycle (spread across the day)
        $articlesThisCycle = $this->calculateCycleCount($config['daily_target'], $config['today_generated']);

        if ($articlesThisCycle <= 0) {
            return;
        }

        Log::info("Orchestrator: generating {$articlesThisCycle} article(s) this cycle", [
            'today' => $config['today_generated'],
            'target' => $config['daily_target'],
        ]);

        // Check stock levels and alert if sources running low
        $this->checkStockLevels($orchestrator);

        // ── CAMPAIGN MODE: if a country campaign is active, use its plan instead of random distribution ──
        $campaignCountry = $this->getCurrentCampaignCountry();
        $useCampaignPlan = $campaignCountry !== null;

        if ($useCampaignPlan) {
            // CRITICAL: dispatch exactly 1 article per cycle to prevent:
            // 1. Rate limit 429 (30K TPM with GPT-4o tier 1)
            // 2. Duplicate generation (next cycle will pick the next plan item)
            $campaignArticles = $this->getNextCampaignArticles($campaignCountry, 1);
            if (!empty($campaignArticles)) {
                Log::info("Orchestrator: CAMPAIGN MODE — {$campaignCountry}, dispatching " . count($campaignArticles) . " from plan");
                $this->dispatchCampaignArticles($campaignArticles, $campaignCountry, $orchestrator);
                return;
            }
            // If campaign plan is exhausted (all 100 done), fall through to normal distribution
            Log::info("Orchestrator: campaign plan exhausted for {$campaignCountry}, falling back to distribution");
        }

        // Determine which types to generate based on % distribution (normal mode)
        $typesToGenerate = $this->selectTypes($config, $articlesThisCycle);

        $generated = 0;
        $errors = [];
        $blockedApis = []; // Track which AI providers are down

        foreach ($typesToGenerate as $typeInfo) {
            // Skip types whose AI provider is known to be down
            $provider = $this->getAiProvider($typeInfo['type']);
            if (in_array($provider, $blockedApis, true)) {
                Log::info("Orchestrator: skipping {$typeInfo['type']} — {$provider} is down, trying fallback");

                // Try a fallback type that uses a different AI
                $fallbackType = $this->getFallbackType($typeInfo['type'], $blockedApis, $config);
                if ($fallbackType) {
                    $typeInfo = $fallbackType;
                    Log::info("Orchestrator: fallback to {$typeInfo['type']}");
                } else {
                    continue; // No fallback available
                }
            }

            // Check per-type daily limits
            $scheduler = app(GenerationSchedulerService::class);
            $canGen = $scheduler->canGenerate($typeInfo['type']);
            if (!$canGen['allowed']) {
                Log::info("Orchestrator: skipping {$typeInfo['type']} — {$canGen['reason']}");
                continue;
            }

            try {
                $dispatched = $this->generateOne($typeInfo['type'], $config);

                if ($dispatched) {
                    // IMPORTANT: We do NOT call recordGeneration() here anymore.
                    //
                    // The previous behavior was to increment today_generated at
                    // dispatch time (before the actual generation completed).
                    // When OpenAI/Claude failed (quota, timeout, network), the
                    // counter was inflated and the orchestrator would stop
                    // dispatching long before the daily target was actually
                    // reached, blocking the system until midnight.
                    //
                    // ContentOrchestratorService::getConfig() now derives
                    // today_generated from a live COUNT() of generated_articles
                    // with word_count > 0 — i.e. articles that actually have
                    // content. This is self-healing: failed generations no
                    // longer pollute the quota.
                    //
                    // We still update the daily log for observability (errors
                    // are tracked separately in the catch block below).
                    $orchestrator->updateDailyLog($typeInfo['type'], 1, 0, 0, 0);
                    $generated++;
                    Log::info("Orchestrator: dispatched {$typeInfo['type']}", ['label' => $typeInfo['label']]);
                }
            } catch (\Throwable $e) {
                $errorMsg = $e->getMessage();
                $errors[] = "{$typeInfo['label']}: {$errorMsg}";
                $orchestrator->updateDailyLog($typeInfo['type'], 0, 1, 0, 0);
                Log::error("Orchestrator: error generating {$typeInfo['type']}", ['error' => $errorMsg]);

                // Detect API credit/quota errors → block that provider for this cycle
                if ($this->isApiCreditError($errorMsg)) {
                    $blockedApis[] = $provider;
                    Log::warning("Orchestrator: {$provider} blocked for this cycle (credit/quota issue)");
                }
            }

            // Pause between generations (natural spacing)
            if ($generated < count($typesToGenerate)) {
                sleep(rand(5, 15));
            }
        }

        // Send Telegram alerts
        if (!empty($errors)) {
            $orchestrator->sendTelegramAlert(
                "Erreur(s) pendant la generation :\n" . implode("\n", array_map(fn($e) => "• {$e}", $errors))
                . (!empty($blockedApis) ? "\n\n🚨 API bloquees : " . implode(', ', $blockedApis) . "\n→ Recharger ces comptes" : ''),
                'error'
            );
        }

        // Check if daily target reached
        $newConfig = $orchestrator->getConfig();
        if ($newConfig['today_generated'] >= $newConfig['daily_target']) {
            $orchestrator->sendTelegramAlert(
                "Objectif journalier atteint : {$newConfig['today_generated']}/{$newConfig['daily_target']} articles generes.\nCout: \${$this->formatCents($newConfig['today_cost_cents'])}",
                'success'
            );
        }
    }

    /**
     * Calculate how many articles to generate this cycle to spread evenly across the day.
     */
    private function calculateCycleCount(int $dailyTarget, int $todayGenerated): int
    {
        $remaining = $dailyTarget - $todayGenerated;
        if ($remaining <= 0) return 0;

        $currentHour = (int) now()->format('H');
        $hoursLeft = max(1, self::ACTIVE_HOUR_END - $currentHour);
        $cyclesLeft = $hoursLeft * 4; // 4 cycles per hour (every 15 min)

        // Spread remaining across remaining cycles
        $perCycle = $remaining / max(1, $cyclesLeft);

        // At least 1, at most 5 per cycle (prevent bursts)
        return max(1, min(5, (int) ceil($perCycle)));
    }

    /**
     * Select which content types to generate based on % distribution
     * and what's been done today (balance towards underrepresented types).
     */
    private function selectTypes(array $config, int $count): array
    {
        $distribution = $config['type_distribution'] ?? [];
        $labels = $config['type_labels'] ?? [];

        if (empty($distribution)) {
            return [];
        }

        // Sort by % descending — prioritize highest % first
        arsort($distribution);

        $selected = [];
        $totalPct = array_sum($distribution);

        if ($totalPct <= 0) return [];

        for ($i = 0; $i < $count; $i++) {
            // Weighted random selection based on %
            $rand = rand(1, $totalPct);
            $cumulative = 0;

            foreach ($distribution as $type => $pct) {
                $cumulative += $pct;
                if ($rand <= $cumulative) {
                    $selected[] = [
                        'type' => $type,
                        'label' => $labels[$type] ?? $type,
                        'pct' => $pct,
                    ];
                    break;
                }
            }
        }

        return $selected;
    }

    /**
     * Generate one article of the given type.
     * Maps orchestrator types to actual generation endpoints.
     */
    private function generateOne(string $type, array $config): bool
    {
        $blogUrl = rtrim(config('services.blog.url', ''), '/');

        // ── COUNTRY CAMPAIGN MODE (2026-04-12) ──
        // Focus on ONE country at a time until it has N articles (threshold from DB).
        // This builds topical authority per country cluster (2026 SEO best practice).
        $country = $this->getCurrentCampaignCountry();

        if (!$country) {
            // All campaign countries have 50+ articles — fall back to random from config
            $countries = $config['priority_countries'] ?? [];
            if (empty($countries)) {
                $allCountries = \App\Models\ContentCountry::pluck('country_code')->filter()->toArray();
                $countries = !empty($allCountries) ? $allCountries : ['FR'];
            }
            $country = $countries[array_rand($countries)] ?? 'FR';
        }

        // Map orchestrator type → actual generation method
        return match ($type) {
            'qa' => $this->triggerGeneration('qa', null, null),
            'art_mots_cles' => $this->triggerGeneration('article', 'articles-piliers', $country),
            'art_longues_traines' => $this->triggerGeneration('article', 'longues-traines', $country),
            'guide' => $this->triggerGeneration('guide', 'fiches-pratiques', $country),
            'guide_expat' => $this->triggerGeneration('guide', 'fiches-pratiques', $country),
            'guide_vacances' => $this->triggerGeneration('guide', 'fiches-pratiques', $country),
            'guide_city' => $this->triggerGeneration('guide_city', 'villes', $country),
            'comparative' => $this->triggerGeneration('comparative', 'comparatifs', null),
            'affiliation' => $this->triggerGeneration('affiliation', 'comparatifs', null),
            'outreach_chatters' => $this->triggerGeneration('outreach', 'chatters', $country),
            'outreach_influenceurs' => $this->triggerGeneration('outreach', 'bloggeurs', $country),
            'outreach_admin_groupes' => $this->triggerGeneration('outreach', 'admin-groups', $country),
            'outreach_avocats' => $this->triggerGeneration('outreach', 'avocats', $country),
            'outreach_expats' => $this->triggerGeneration('outreach', 'expats-aidants', $country),
            'testimonial' => $this->triggerGeneration('testimonial', 'temoignages', $country),
            'brand_content' => $this->triggerGeneration('article', 'brand-content', null),
            'statistiques' => $this->triggerGeneration('statistics', 'statistiques', $country),
            'pain_point' => $this->triggerGeneration('pain_point', 'pain-point', $country),
            default => false,
        };
    }

    /**
     * Trigger article generation via internal API.
     */
    private function triggerGeneration(string $contentType, ?string $sourceSlug, ?string $country): bool
    {
        try {
            // Dispatch generation job directly (no HTTP — avoids auth issues)
            if ($sourceSlug) {
                $category = \Illuminate\Support\Facades\DB::table('generation_source_categories')
                    ->where('slug', $sourceSlug)
                    ->first();

                if (!$category) {
                    Log::warning("Orchestrator: source '{$sourceSlug}' not found, skipping");
                    return false;
                }

                $config = json_decode($category->config_json ?? '{}', true);
                if ($config['is_paused'] ?? false) {
                    Log::info("Orchestrator: source '{$sourceSlug}' is paused, skipping");
                    return false;
                }

                \App\Jobs\GenerateFromSourceJob::dispatch($sourceSlug, 1);
                Log::info("Orchestrator: dispatched GenerateFromSourceJob", [
                    'source' => $sourceSlug,
                    'content_type' => $contentType,
                    'country' => $country,
                ]);

                return true;
            }

            // For Q/R: use the dedicated generator
            if ($contentType === 'qa') {
                \Illuminate\Support\Facades\Artisan::call('qr:from-articles', ['--limit' => 1]);
                return true;
            }

            return false;
        } catch (\Throwable $e) {
            Log::error("Orchestrator trigger failed: {$contentType}/{$sourceSlug}", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Trigger fiches pays generation via Blog API.
     */
    private function triggerFichesPays(string $type, string $country, string $blogUrl): bool
    {
        if (!$blogUrl) return false;

        try {
            $response = Http::timeout(300)
                ->withToken(config('services.blog.api_key', ''))
                ->post("{$blogUrl}/api/v1/fiches/{$type}/generate", [
                    'country' => $country,
                    'draft' => false,
                ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error("Orchestrator fiches trigger failed: {$type}/{$country}", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Check stock levels for each source. Alert if running low.
     * Skip types with 0 stock in the distribution.
     */
    private function checkStockLevels(ContentOrchestratorService $orchestrator): void
    {
        // Only check once per day (first cycle)
        $cacheKey = 'orchestrator_stock_check_' . now()->toDateString();
        if (\Illuminate\Support\Facades\Cache::has($cacheKey)) return;
        \Illuminate\Support\Facades\Cache::put($cacheKey, true, now()->endOfDay());

        $lowStock = [];

        // Check comparatives
        $compPending = \App\Models\Comparative::where('status', 'draft')->whereNull('content_html')->count();
        if ($compPending < 10) $lowStock[] = "Comparatifs: {$compPending} restants";

        // Check keywords (art_mots_cles + longues_traines)
        $kwUnused = \Illuminate\Support\Facades\DB::table('keyword_tracking')
            ->where('articles_using_count', 0)
            ->count();
        if ($kwUnused < 20) $lowStock[] = "Mots-cles: {$kwUnused} inutilises";

        // Check content templates (pending items not yet generated)
        $templatesPending = \Illuminate\Support\Facades\DB::table('content_template_items')
            ->where('status', 'pending')
            ->count();
        if ($templatesPending < 50) $lowStock[] = "Templates items: {$templatesPending} en attente";

        // Auto-discover new keywords if stock is low
        if ($kwUnused < 50) {
            try {
                \Illuminate\Support\Facades\Artisan::call('keywords:discover', ['--limit' => 10]);
                Log::info('Orchestrator: auto-discovered keywords (stock was low)');
            } catch (\Throwable $e) {
                Log::warning("Orchestrator: keywords:discover failed", ['error' => $e->getMessage()]);
            }
        }

        // Alert if any source is running low
        if (!empty($lowStock)) {
            $orchestrator->sendTelegramAlert(
                "⚠️ Sources de contenu basses :\n" . implode("\n", array_map(fn($s) => "• {$s}", $lowStock))
                . "\n\nLa decouverte automatique de mots-cles est activee.",
                'warning'
            );
        }
    }

    /**
     * Map content type → AI provider used.
     */
    private function getAiProvider(string $type): string
    {
        return match ($type) {
            // Claude (Anthropic) — used for Q/R, News, Fiches Pays, Auto Q/R, Testimonials
            'qa', 'testimonial' => 'anthropic',
            // Fiches pays use Claude (Blog-side) + Tavily
            'guide', 'guide_expat', 'guide_vacances' => 'anthropic+tavily',
            // GPT-4o (OpenAI) — used for Articles, Comparatives, City guides
            'art_mots_cles', 'art_longues_traines', 'guide_city', 'comparative', 'affiliation', 'brand_content' => 'openai',
            // Outreach types — use GPT-4o via ArticleGenerationService
            'outreach_chatters', 'outreach_influenceurs', 'outreach_admin_groupes', 'outreach_avocats', 'outreach_expats' => 'openai',
            default => 'openai',
        };
    }

    /**
     * Find a fallback content type that uses a different AI provider.
     */
    private function getFallbackType(string $failedType, array $blockedApis, array $config): ?array
    {
        $distribution = $config['type_distribution'] ?? [];
        $labels = $config['type_labels'] ?? [];

        foreach ($distribution as $type => $pct) {
            if ($pct <= 0) continue;
            if ($type === $failedType) continue;

            $provider = $this->getAiProvider($type);
            $isBlocked = false;
            foreach ($blockedApis as $blocked) {
                if (str_contains($provider, $blocked)) {
                    $isBlocked = true;
                    break;
                }
            }

            if (!$isBlocked) {
                return ['type' => $type, 'label' => $labels[$type] ?? $type, 'pct' => $pct];
            }
        }

        return null; // All providers down
    }

    /**
     * Detect if error is a credit/quota API error.
     */
    private function isApiCreditError(string $message): bool
    {
        $lower = strtolower($message);
        return str_contains($lower, 'credit balance')
            || str_contains($lower, 'quota')
            || str_contains($lower, 'exceeded')
            || str_contains($lower, 'billing')
            || str_contains($lower, 'insufficient')
            || str_contains($lower, 'rate limit');
    }

    private function formatCents(int $cents): string
    {
        return number_format($cents / 100, 2);
    }

    /**
     * Get next articles from the CountryCampaignCommand plan that haven't been generated yet.
     *
     * Quota-aware selection: the plan is structured as N items per type (e.g. 72 article,
     * 40 brand_content, 29 tutorial, 27 comparative, ...). We count how many of each type
     * already exist in the DB and SKIP any plan item whose type quota is exhausted. This
     * ensures every type in the plan gets its fair share, instead of letting the types at
     * the START of the plan monopolize all slots (which was the bug: the old code broke
     * after 1 match and never reached types at the END of the plan).
     */
    private function getNextCampaignArticles(string $countryCode, int $count): array
    {
        $command = new \App\Console\Commands\CountryCampaignCommand();
        $name = \App\Console\Commands\CountryCampaignCommand::COUNTRY_ORDER[$countryCode] ?? $countryCode;

        $plan = $command->getContentPlan($countryCode, $name);

        // ─────────────────────────────────────────────────────────────────
        // QUOTA BY TYPE — two modes (configurable via DB)
        //   'fixed_plan' (default): use the plan's hand-tuned per-type counts
        //   'percentage': recompute per-type targets from type_distribution
        //                 (set via dashboard "Configuration" page), applied
        //                 to campaign_articles_per_country (262).
        // ─────────────────────────────────────────────────────────────────
        $orchConfig = \Illuminate\Support\Facades\DB::table('content_orchestrator_config')->first();
        $mode = $orchConfig->campaign_distribution_mode ?? 'fixed_plan';
        $threshold = (int) ($orchConfig->campaign_articles_per_country ?? 262);

        $planQuotaByType = [];

        if ($mode === 'percentage') {
            // Map config-side type keys to DB content_type values
            // (mirrors RunOrchestratorCycleJob::triggerGeneration mapping at line ~280)
            $typeMapping = [
                'qa'                     => null, // Q/R generated separately by GenerateQrBlogJob
                'art_mots_cles'          => 'article',
                'art_longues_traines'    => 'article',
                'guide'                  => 'guide',
                'guide_expat'            => 'guide',
                'guide_vacances'         => 'guide',
                'guide_city'             => 'guide_city',
                'comparative'            => 'comparative',
                'affiliation'            => 'affiliation',
                'outreach_chatters'      => 'outreach',
                'outreach_influenceurs'  => 'outreach',
                'outreach_admin_groupes' => 'outreach',
                'outreach_avocats'       => 'outreach',
                'outreach_expats'        => 'outreach',
                'testimonial'            => 'testimonial',
                'brand_content'          => 'article',
                'statistiques'           => 'statistics',
                'pain_point'             => 'pain_point',
            ];

            $distribution = json_decode($orchConfig->type_distribution ?? '{}', true) ?? [];

            foreach ($distribution as $configType => $pct) {
                if ($pct <= 0) continue;
                $dbType = $typeMapping[$configType] ?? null;
                if ($dbType === null) continue; // qa skipped (separate pipeline)
                $count = (int) round($threshold * $pct / 100);
                $planQuotaByType[$dbType] = ($planQuotaByType[$dbType] ?? 0) + $count;
            }

            Log::info("Orchestrator Campaign: percentage mode for {$countryCode}", [
                'threshold' => $threshold,
                'targets'   => $planQuotaByType,
            ]);
        } else {
            // fixed_plan: count per-type from the plan as before
            foreach ($plan as $item) {
                $t = $item['type'] ?? 'article';
                $planQuotaByType[$t] = ($planQuotaByType[$t] ?? 0) + 1;
            }
        }

        // Statuses that count as a "taken slot" for the quota. Includes in-flight
        // ('generating', 'draft') to prevent double-dispatch while a job is running,
        // as well as completed states. Excludes 'deleted' and 'failed' so we can retry.
        $activeStatuses = ['generating', 'draft', 'review', 'approved', 'published', 'translating', 'translated'];

        // Count existing articles per type for this country
        $existingByType = \App\Models\GeneratedArticle::where('country', $countryCode)
            ->where('language', 'fr')
            ->whereIn('status', $activeStatuses)
            ->groupBy('content_type')
            ->selectRaw('content_type, COUNT(*) as n')
            ->pluck('n', 'content_type')
            ->toArray();

        // Compute remaining quota per type (plan - existing)
        $remainingByType = [];
        foreach ($planQuotaByType as $type => $expected) {
            $existing = (int) ($existingByType[$type] ?? 0);
            $remainingByType[$type] = max(0, $expected - $existing);
        }

        // If every type is at or above its quota, nothing to generate for this country
        if (array_sum($remainingByType) === 0) {
            Log::info("Orchestrator Campaign: {$countryCode} plan complete (all types fulfilled)");
            return [];
        }

        // Get existing titles GROUPED BY content_type for scoped dedup.
        // We use EXACT title match (case-insensitive, trimmed) per type instead of the
        // previous keyword-based fuzzy match. Reason: the plan contains 262 hand-curated
        // distinct topics by design, so fuzzy matching only blocks legitimate items that
        // share template words ("Thailande 2026", "guide complet", "SOS-Expat.com", ...)
        // with existing titles. Exact match is more predictable and avoids false positives.
        $existingByTypeAndTitle = \App\Models\GeneratedArticle::where('country', $countryCode)
            ->where('language', 'fr')
            ->whereIn('status', array_merge($activeStatuses, ['deleted', 'failed']))
            ->get(['content_type', 'title']);

        $normalize = fn (string $s) => mb_strtolower(trim($s));

        $titleSetByType = [];
        foreach ($existingByTypeAndTitle as $row) {
            $titleSetByType[$row->content_type][$normalize($row->title)] = true;
        }

        // Also track in-process topics to prevent same-cycle duplicates (static per job run)
        static $dispatchedByType = [];
        foreach ($dispatchedByType as $type => $topics) {
            foreach ($topics as $t) {
                $titleSetByType[$type][$normalize($t)] = true;
            }
        }

        $toGenerate = [];
        foreach ($plan as $item) {
            $type = $item['type'] ?? 'article';

            // Skip: this type has already reached its plan quota
            if (($remainingByType[$type] ?? 0) <= 0) {
                continue;
            }

            // Skip: exact title already exists for this type (case-insensitive)
            $normalizedTopic = $normalize($item['topic']);
            if (isset($titleSetByType[$type][$normalizedTopic])) {
                continue;
            }

            // Anti-race-condition lock: 2 orchestrator cycles can fire in close
            // succession (cron 15 min, but worker timing varies) and both pick
            // the SAME topic before the first one creates its DB row in
            // 'generating' status. Cache::add() is atomic: returns true if the
            // key didn't exist (and creates it), false if it did. TTL=15 min
            // covers the worst-case time between dispatch and DB insert.
            $lockKey = "campaign:dispatched:{$countryCode}:{$type}:" . md5($normalizedTopic);
            if (!\Illuminate\Support\Facades\Cache::add($lockKey, true, 900)) {
                Log::info("Orchestrator: topic dispatch lock active, skip", [
                    'country' => $countryCode,
                    'type' => $type,
                    'topic' => $item['topic'],
                ]);
                continue;
            }

            // Accept this item
            $toGenerate[] = $item;
            $dispatchedByType[$type][] = $item['topic'];
            $titleSetByType[$type][$normalizedTopic] = true;
            $remainingByType[$type]--; // decrement in-memory so subsequent items in the same cycle respect quota

            if (count($toGenerate) >= $count) {
                break;
            }
        }

        return $toGenerate;
    }

    /**
     * Dispatch campaign articles directly via GenerateArticleJob.
     */
    private function dispatchCampaignArticles(array $articles, string $countryCode, $orchestrator): void
    {
        $command = new \App\Console\Commands\CountryCampaignCommand();
        $countryName = \App\Console\Commands\CountryCampaignCommand::COUNTRY_ORDER[$countryCode] ?? $countryCode;

        $generated = 0;
        $errors = [];

        foreach ($articles as $i => $item) {
            try {
                $keywords = $command->extractKeywords($item['topic'], $countryName);

                \App\Jobs\GenerateArticleJob::dispatch([
                    'topic'          => $item['topic'],
                    'content_type'   => $item['type'],
                    'language'       => 'fr',
                    'country'        => $countryCode,
                    'keywords'       => $keywords,
                    'search_intent'  => $item['intent'],
                    'force_generate' => true,
                    'image_source'   => 'unsplash',
                ])->delay(now()->addSeconds($i * 45)); // Stagger to avoid rate limits

                $orchestrator->updateDailyLog($item['type'], 1, 0, 0, 0);
                $generated++;
                Log::info("Orchestrator Campaign: dispatched [{$item['type']}] {$item['topic']}");
            } catch (\Throwable $e) {
                $errors[] = "{$item['topic']}: {$e->getMessage()}";
                $orchestrator->updateDailyLog($item['type'], 0, 1, 0, 0);
                Log::error("Orchestrator Campaign: error", ['topic' => $item['topic'], 'error' => $e->getMessage()]);
            }

            // Pause between dispatches
            if ($i < count($articles) - 1) {
                sleep(rand(3, 8));
            }
        }

        if (!empty($errors)) {
            $orchestrator->sendTelegramAlert(
                "Campaign {$countryCode}: {$generated} dispatches, " . count($errors) . " erreur(s)\n"
                . implode("\n", array_map(fn($e) => "• {$e}", array_slice($errors, 0, 5))),
                'error'
            );
        }

        if ($generated > 0) {
            Log::info("Orchestrator Campaign: {$generated} articles dispatched for {$countryCode}");
        }
    }

    /**
     * Country Campaign Mode: return the current focus country.
     *
     * Picks the first country in priority order that has < N articles (threshold from DB).
     * When a country reaches N, auto-advances to the next.
     * Returns null when all campaign countries are complete.
     */
    private function getCurrentCampaignCountry(): ?string
    {
        // Read campaign queue and threshold from DB (configurable via dashboard)
        $config = \Illuminate\Support\Facades\DB::table('content_orchestrator_config')->first();
        $campaignOrder = json_decode($config->campaign_country_queue ?? '[]', true);
        $threshold = (int) ($config->campaign_articles_per_country ?? 240);

        // Fallback: if queue is empty, use priority_countries from config
        if (empty($campaignOrder)) {
            $campaignOrder = json_decode($config->priority_countries ?? '[]', true);
        }

        if (empty($campaignOrder)) {
            return null;
        }

        // Cache the counts for 10 minutes to avoid querying on every cycle.
        // Status filter is aligned with getNextCampaignArticles() quota check: we count
        // every in-flight or completed article as a "slot taken", so the country-level
        // threshold check stays consistent with the per-type quota enforcement.
        $counts = \Illuminate\Support\Facades\Cache::remember('country_campaign_counts', 600, function () {
            return \App\Models\GeneratedArticle::where('language', 'fr')
                ->whereIn('status', ['generating', 'draft', 'review', 'approved', 'published', 'translating', 'translated'])
                ->whereNotNull('country')
                ->groupBy('country')
                ->selectRaw('country, COUNT(*) as total')
                ->pluck('total', 'country')
                ->toArray();
        });

        // Round-robin strategy on the priority list (top 12 countries):
        // pick the country with the FEWEST articles. Ties are broken by
        // the queue order so 1) early generations spread evenly across all
        // 12 priority countries instead of finishing one before starting
        // the next, and 2) once they're all balanced the queue order acts
        // as a natural tie-breaker (TH stays first when everyone is equal).
        //
        // After the 12 priority countries have all reached the threshold,
        // we fall through to the legacy sequential mode for the remaining
        // ~185 countries (one country at a time, by queue order).
        $priorityWindow = (int) ($config->campaign_priority_window_size ?? 12);
        $priorityCodes  = array_slice($campaignOrder, 0, $priorityWindow);

        $candidates = [];
        foreach ($priorityCodes as $idx => $code) {
            $existing = $counts[$code] ?? 0;
            if ($existing < $threshold) {
                $candidates[] = ['code' => $code, 'count' => $existing, 'order' => $idx];
            }
        }

        if (!empty($candidates)) {
            // Sort: fewest articles first, then queue order
            usort($candidates, fn ($a, $b) =>
                ($a['count'] <=> $b['count']) ?: ($a['order'] <=> $b['order'])
            );
            $picked = $candidates[0];
            Log::info("Orchestrator: Round-robin priority → {$picked['code']} ({$picked['count']}/{$threshold} articles, " . count($candidates) . " priority countries still in progress)");
            return $picked['code'];
        }

        // Priority window done — fall back to sequential on the long tail.
        foreach (array_slice($campaignOrder, $priorityWindow) as $code) {
            $existing = $counts[$code] ?? 0;
            if ($existing < $threshold) {
                Log::info("Orchestrator: tail-mode focus → {$code} ({$existing}/{$threshold} articles)");
                return $code;
            }
        }

        Log::info("Orchestrator: all campaign countries have {$threshold}+ articles");
        return null;
    }
}
