<?php

namespace App\Console\Commands;

use App\Models\LandingPage;
use App\Services\Content\LandingGenerationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Backfill missing language translations for published landing pages.
 *
 * ┌───────────────────────────────────────────────────────────────────────────
 * │  DETERMINISTIC — no LLM, no Anthropic API call. Uses:                     │
 * │    • LandingGenerationService::buildSlug()                                │
 * │    • LandingGenerationService::getCountryName() + getCountrySlug()        │
 * │    • Canonical sentences hard-coded from config/sos_expat_facts.php       │
 * │    • Blade templates fall back to translations/*.php when sections/json_ld│
 * │      are NULL → diverse FAQ rotation + KB-grounded hero already shipped.  │
 * ├───────────────────────────────────────────────────────────────────────────
 * │  Flow:                                                                     │
 * │    1. Build coverage matrix (root landings × 9 languages).                │
 * │    2. For each (root, missing-lang) combo: insert a minimal sibling row.  │
 * │    3. After all inserts, re-sync hreflang_map for every touched root.     │
 * └───────────────────────────────────────────────────────────────────────────
 *
 * Usage:
 *   php artisan landings:backfill-translations --report
 *   php artisan landings:backfill-translations --dry-run
 *   php artisan landings:backfill-translations           (actually writes)
 *   php artisan landings:backfill-translations --language=en --limit=10
 */
class BackfillLandingTranslationsCommand extends Command
{
    protected $signature = 'landings:backfill-translations
        {--report           : Only print coverage matrix, do not generate}
        {--dry-run          : Log what would be created without writing}
        {--language=        : Only generate missing rows for this target language (xx)}
        {--country=         : Only process roots for this country (ISO2, eg TH)}
        {--limit=           : Max number of roots to process}';

    protected $description = 'Create DB rows for missing language siblings of published landing pages — deterministic (no LLM)';

    private const ALL_LANGS = ['fr', 'en', 'es', 'de', 'pt', 'ru', 'zh', 'hi', 'ar'];

    /**
     * Per-language canonical sentence templates.
     * Mirrors Blog_sos-expat_frontend/config/sos_expat_facts.php so both repos
     * stay in sync on phrasing. `%country%` is interpolated.
     */
    private const CANONICAL = [
        'fr' => [
            'meta_title_fmt'       => 'Aide expatriés en %country% — rappel en 5 min · SOS-Expat',
            'meta_description_fmt' => 'Rappel téléphonique en moins de 5 minutes avec un avocat local ou un expat expert en %country%. Prix fixe 49€ / 19€, paiement prépayé, 24h/24.',
            'title_fmt'            => 'Aide aux expatriés en %country%',
        ],
        'en' => [
            'meta_title_fmt'       => 'Expat help in %country% — callback in 5 min · SOS-Expat',
            'meta_description_fmt' => 'Direct phone callback in under 5 minutes with a local lawyer or expert expat in %country%. Fixed price 49€ / 19€, prepaid, 24/7.',
            'title_fmt'            => 'Expat help in %country%',
        ],
        'es' => [
            'meta_title_fmt'       => 'Ayuda a expatriados en %country% — llamada en 5 min · SOS-Expat',
            'meta_description_fmt' => 'Llamada telefónica directa en menos de 5 minutos con un abogado local o un expatriado experto en %country%. Precio fijo 49€ / 19€, prepago, 24h/24.',
            'title_fmt'            => 'Ayuda a expatriados en %country%',
        ],
        'de' => [
            'meta_title_fmt'       => 'Hilfe für Expats in %country% — Rückruf in 5 Min · SOS-Expat',
            'meta_description_fmt' => 'Direkter Rückruf in unter 5 Minuten mit einem lokalen Anwalt oder erfahrenen Expat in %country%. Festpreis 49€ / 19€, Vorauszahlung, rund um die Uhr.',
            'title_fmt'            => 'Hilfe für Expats in %country%',
        ],
        'pt' => [
            'meta_title_fmt'       => 'Ajuda a expatriados em %country% — chamada em 5 min · SOS-Expat',
            'meta_description_fmt' => 'Chamada de retorno direta em menos de 5 minutos com um advogado local ou um expatriado expert em %country%. Preço fixo 49€ / 19€, pré-pago, 24h/24.',
            'title_fmt'            => 'Ajuda a expatriados em %country%',
        ],
        'ru' => [
            'meta_title_fmt'       => 'Помощь экспатам в %country% — звонок за 5 мин · SOS-Expat',
            'meta_description_fmt' => 'Прямой обратный звонок менее чем за 5 минут с местным адвокатом или опытным экспатом в %country%. Фиксированная цена 49€ / 19€, предоплата, круглосуточно.',
            'title_fmt'            => 'Помощь экспатам в %country%',
        ],
        'zh' => [
            'meta_title_fmt'       => '%country% 海外华人援助 — 5 分钟内回拨 · SOS-Expat',
            'meta_description_fmt' => '%country% 当地律师或资深侨胞 5 分钟内直接电话回拨。固定价格 49€ / 19€，预付，全天候服务。',
            'title_fmt'            => '%country% 海外华人援助',
        ],
        'hi' => [
            'meta_title_fmt'       => '%country% में प्रवासी सहायता — 5 मिनट में कॉल · SOS-Expat',
            'meta_description_fmt' => '%country% में स्थानीय वकील या अनुभवी प्रवासी के साथ 5 मिनट से भी कम में सीधा फ़ोन कॉल। निश्चित मूल्य 49€ / 19€, प्रीपेड, 24 घंटे।',
            'title_fmt'            => '%country% में प्रवासी सहायता',
        ],
        'ar' => [
            'meta_title_fmt'       => 'مساعدة المغتربين في %country% — مكالمة خلال 5 دقائق · SOS-Expat',
            'meta_description_fmt' => 'اتصال هاتفي مباشر في أقل من 5 دقائق مع محامٍ محلي أو مغترب خبير في %country%. سعر ثابت 49€ / 19€، دفع مسبق، على مدار الساعة.',
            'title_fmt'            => 'مساعدة المغتربين في %country%',
        ],
    ];

    public function handle(LandingGenerationService $service): int
    {
        $reportOnly = (bool) $this->option('report');
        $dryRun     = (bool) $this->option('dry-run');
        $filterLang = $this->option('language');
        $filterCty  = $this->option('country');
        $limit      = $this->option('limit') ? (int) $this->option('limit') : null;

        if ($filterLang && ! in_array($filterLang, self::ALL_LANGS, true)) {
            $this->error("--language must be one of: " . implode(', ', self::ALL_LANGS));
            return self::FAILURE;
        }

        // ─── Coverage report ───────────────────────────────────────────────
        $this->info("\n=== Landing translation coverage report ===\n");

        $rootsQuery = LandingPage::published()
            ->whereNull('parent_id')
            ->orderBy('id');
        if ($filterCty) {
            $rootsQuery->where('country_code', strtoupper($filterCty));
        }
        $roots = $rootsQuery->get();

        if ($roots->isEmpty()) {
            $this->warn('No published root landings found.');
            return self::SUCCESS;
        }

        $matrix     = [];
        $missingSet = [];

        foreach ($roots as $root) {
            $siblings = LandingPage::where('parent_id', $root->id)
                ->orWhere('id', $root->id)
                ->pluck('language')
                ->toArray();
            $existing = array_values(array_unique(array_merge([$root->language], $siblings)));
            $missing  = array_values(array_diff(self::ALL_LANGS, $existing));
            $matrix[$root->id] = [
                'slug'     => $root->slug,
                'country'  => $root->country_code,
                'audience' => $root->audience_type,
                'existing' => $existing,
                'missing'  => $missing,
            ];
            foreach ($missing as $m) $missingSet[$m] = ($missingSet[$m] ?? 0) + 1;
        }

        $this->table(
            ['Language', 'Missing on # roots'],
            array_map(fn($l) => [$l, $missingSet[$l] ?? 0], self::ALL_LANGS)
        );

        $totalRoots = $roots->count();
        $fullCoverage = count(array_filter($matrix, fn($m) => empty($m['missing'])));
        $this->line("Roots total           : {$totalRoots}");
        $this->line("Roots with 9/9 langs  : {$fullCoverage}");
        $this->line("Roots with gaps       : " . ($totalRoots - $fullCoverage));
        $totalGaps = array_sum($missingSet);
        $this->line("Total missing rows    : {$totalGaps}");
        $this->newLine();

        if ($reportOnly) return self::SUCCESS;

        // ─── Generation ─────────────────────────────────────────────────────
        $this->info("\n=== Generating missing translations (deterministic) ===\n");

        if ($dryRun) {
            $this->warn("DRY RUN — no DB writes.");
        }

        $processed = 0;
        $created   = 0;
        $skipped   = 0;
        $touchedRoots = [];

        foreach ($matrix as $rootId => $info) {
            if ($limit !== null && $processed >= $limit) break;

            if (empty($info['missing'])) continue;

            $langs = $filterLang ? array_intersect($info['missing'], [$filterLang]) : $info['missing'];
            if (empty($langs)) continue;

            $root = $roots->firstWhere('id', $rootId);
            if (! $root) continue;

            $processed++;
            $this->line("\n[root #{$rootId}] {$root->slug} ({$root->audience_type}, {$root->country_code}) — missing: " . implode(',', $info['missing']));

            foreach ($langs as $lang) {
                try {
                    $payload = $this->buildTranslationPayload($service, $root, $lang);
                    if (! $payload) {
                        $this->warn("  - [{$lang}] could not build payload, skipped");
                        $skipped++;
                        continue;
                    }

                    $this->line("  - [{$lang}] slug={$payload['slug']} title={$payload['title']}");

                    if (! $dryRun) {
                        $new = LandingPage::create($payload);
                        $created++;
                        $this->info("    ✓ created #{$new->id}");
                    } else {
                        $created++;
                    }

                    $touchedRoots[$rootId] = true;
                } catch (\Throwable $e) {
                    $this->error("  - [{$lang}] FAILED: {$e->getMessage()}");
                    $skipped++;
                }
            }
        }

        // ─── Re-sync hreflang_map on all touched roots ─────────────────────
        if (! $dryRun && ! empty($touchedRoots)) {
            $this->info("\n=== Re-syncing hreflang_map for " . count($touchedRoots) . " roots ===");
            foreach (array_keys($touchedRoots) as $rootId) {
                $root = LandingPage::find($rootId);
                if (! $root) continue;
                try {
                    $service->syncHreflangMap($root);
                    $this->line("  ✓ root #{$rootId}");
                } catch (\Throwable $e) {
                    $this->warn("  ! root #{$rootId}: {$e->getMessage()}");
                }
            }
        }

        $this->newLine();
        $this->info("Summary: processed={$processed} roots, created={$created} rows, skipped={$skipped}" . ($dryRun ? ' (dry run)' : ''));

        return self::SUCCESS;
    }

    /**
     * Build the array passed to LandingPage::create() for a missing-lang sibling.
     *
     * Keeps sections/json_ld NULL on purpose — the Blade templates then fall
     * back to resources/views/landings/translations/*.php, which already
     * contain the full 9-language copy + deterministic FAQ rotation.
     */
    private function buildTranslationPayload(
        LandingGenerationService $service,
        LandingPage $root,
        string $targetLang
    ): ?array {
        if (empty($root->country_code)) {
            return null;
        }

        $countryCode = strtoupper($root->country_code);
        $countryName = $service->getCountryName($countryCode, $targetLang);
        $countrySlug = $service->getCountrySlug($countryCode, $targetLang);

        $audienceType = $root->audience_type ?? 'clients';
        $problemSlug  = $root->problem_id
            ?? $root->category_slug
            ?? $root->user_profile
            ?? $root->origin_nationality;

        $slug = $service->buildSlug(
            $audienceType,
            $targetLang,
            $countrySlug,
            $problemSlug ? strtolower(str_replace('_', '-', $problemSlug)) : null,
            $root->template_id,
            null,
        );

        $canonical = rtrim(config('services.site_url', 'https://sos-expat.com'), '/') . '/' . $slug;

        $canon = self::CANONICAL[$targetLang] ?? self::CANONICAL['fr'];
        $metaTitle = str_replace('%country%', $countryName, $canon['meta_title_fmt']);
        $metaDesc  = str_replace('%country%', $countryName, $canon['meta_description_fmt']);
        $title     = str_replace('%country%', $countryName, $canon['title_fmt']);

        return [
            'parent_id'          => $root->id,
            'audience_type'      => $audienceType,
            'template_id'        => $root->template_id,
            'problem_id'         => $root->problem_id,
            'category_slug'      => $root->category_slug,
            'user_profile'       => $root->user_profile,
            'origin_nationality' => $root->origin_nationality,
            'country'            => $countryName,
            'country_code'       => $countryCode,
            'language'           => $targetLang,
            'content_language'   => $targetLang,
            'title'              => $title,
            'slug'               => $slug,
            'meta_title'         => $metaTitle,
            'meta_description'   => $metaDesc,
            'canonical_url'      => $canonical,
            // sections + json_ld intentionally NULL → Blade template fallback.
            'sections'           => null,
            'json_ld'            => null,
            // hreflang_map is recomputed below via service->syncHreflangMap().
            'hreflang_map'       => [],
            'status'             => 'published',
            'published_at'       => now(),
            'date_published_at'  => now(),
            'date_modified_at'   => now(),
            'generation_source'  => 'deterministic_backfill',
            'generation_cost_cents' => 0,
            'seo_score'          => 70,
            'featured_image_url' => $root->featured_image_url,
            'featured_image_alt' => $metaTitle,
            'og_type'            => $root->og_type ?? 'article',
            'og_title'           => $metaTitle,
            'og_description'     => $metaDesc,
            'og_image'           => $root->og_image ?? $root->featured_image_url,
            'og_site_name'       => 'SOS-Expat',
            'og_locale'          => $targetLang . '_' . $countryCode,
            'twitter_card'       => 'summary_large_image',
            'twitter_title'      => $metaTitle,
            'twitter_description'=> $metaDesc,
            'twitter_image'      => $root->og_image ?? $root->featured_image_url,
            'robots'             => 'index,follow',
            'geo_region'         => $root->geo_region,
            'geo_placename'      => $countryName,
            'geo_position'       => $root->geo_position,
            'icbm'               => $root->icbm,
        ];
    }
}
