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
 * If daily_target = 200, that's ~3.1 per cycle → 3 articles per cycle
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

        // Determine which types to generate based on % distribution
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

            try {
                $success = $this->generateOne($typeInfo['type'], $config);

                if ($success) {
                    $orchestrator->recordGeneration(0);
                    $generated++;
                    Log::info("Orchestrator: generated {$typeInfo['type']}", ['label' => $typeInfo['label']]);
                }
            } catch (\Throwable $e) {
                $errorMsg = $e->getMessage();
                $errors[] = "{$typeInfo['label']}: {$errorMsg}";
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
        $countries = $config['priority_countries'] ?? [];

        // Empty countries list = all 197 countries — pick random from DB
        if (empty($countries)) {
            $allCountries = \App\Models\ContentCountry::pluck('country_code')->filter()->toArray();
            $countries = !empty($allCountries) ? $allCountries : ['FR'];
        }

        // Pick a random priority country for country-specific content
        $country = $countries[array_rand($countries)] ?? 'FR';

        // Map orchestrator type → actual generation method
        return match ($type) {
            'qa' => $this->triggerGeneration('qa', null, null),
            'art_mots_cles' => $this->triggerGeneration('article', 'art-mots-cles', $country),
            'art_longues_traines' => $this->triggerGeneration('article', 'longues-traines', $country),
            'guide' => $this->triggerFichesPays('general', $country, $blogUrl),
            'guide_expat' => $this->triggerFichesPays('expatriation', $country, $blogUrl),
            'guide_vacances' => $this->triggerFichesPays('vacances', $country, $blogUrl),
            'guide_city' => $this->triggerGeneration('guide_city', 'villes', $country),
            'comparative' => $this->triggerGeneration('comparative', 'comparatives', null),
            'affiliation' => $this->triggerGeneration('affiliation', 'affiliate-comparatives', null),
            'outreach_chatters' => $this->triggerGeneration('outreach', 'chatters', $country),
            'outreach_influenceurs' => $this->triggerGeneration('outreach', 'bloggeurs', $country),
            'outreach_admin_groupes' => $this->triggerGeneration('outreach', 'admin-groups', $country),
            'outreach_avocats' => $this->triggerGeneration('outreach', 'avocats', $country),
            'outreach_expats' => $this->triggerGeneration('outreach', 'expats-aidants', $country),
            'testimonial' => $this->triggerGeneration('testimonial', 'temoignages', $country),
            'brand_content' => $this->triggerGeneration('article', 'brand-content', null),
            default => false,
        };
    }

    /**
     * Trigger article generation via internal API.
     */
    private function triggerGeneration(string $contentType, ?string $sourceSlug, ?string $country): bool
    {
        try {
            // Find a pending item from the source and generate
            if ($sourceSlug) {
                $response = Http::timeout(300)
                    ->withToken(config('services.internal_api_key', 'internal'))
                    ->post(config('app.url') . "/api/generation-sources/{$sourceSlug}/trigger", [
                        'content_type' => $contentType,
                        'country' => $country,
                        'limit' => 1,
                        'auto_publish' => true,
                    ]);

                return $response->successful();
            }

            // For Q/R: use the dedicated generator
            if ($contentType === 'qa') {
                \Illuminate\Support\Facades\Artisan::call('qr:from-articles', ['--limit' => 1]);
                return true;
            }

            return false;
        } catch (\Throwable $e) {
            Log::error("Orchestrator trigger failed: {$contentType}/{$sourceSlug}", ['error' => $e->getMessage()]);
            throw $e;
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
}
