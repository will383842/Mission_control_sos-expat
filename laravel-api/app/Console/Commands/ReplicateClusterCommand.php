<?php

namespace App\Console\Commands;

use App\Models\LandingPage;
use App\Services\Content\LandingGenerationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Replicate a landing-page cluster from a source country to one or more
 * target countries — text substitution only, no LLM call.
 *
 * Use-case: we've authored rich Opus content for Thailand clusters (lawyers,
 * helpers, pillar-immigration, nationality-FR-in-TH, etc.). For every other
 * country the business cares about (VN, SG, MY, …), we want a first version
 * of the same cluster that at least:
 *   - has the right URL structure (/xx-yy/segment/slug)
 *   - has the target-country name substituted in title/meta/sections/slug
 *   - is published and reachable via the Blog frontend
 *
 * A later pass can manually enhance the text quality with Opus (same pipeline
 * as TH: landings:import + apply-patches.php), but the cluster already exists
 * and is indexable.
 *
 * ┌──────────────────────────────────────────────────────────────────────────
 * │  Usage:
 * │    php artisan landings:replicate-cluster --source=716 --target=VN
 * │    php artisan landings:replicate-cluster --source=716 --target=VN,SG,MY --dry-run
 * │    php artisan landings:replicate-cluster --source=716,717,718,719 --target=VN
 * │    php artisan landings:replicate-cluster --source-country=TH --target=VN,SG
 * │    php artisan landings:replicate-cluster --source-country=TH --target=all
 * │
 * │  Behaviour:
 * │    - source    : root landing_page id(s) to replicate from
 * │    - source-country : replicate ALL published roots of this country
 * │    - target    : ISO2 country codes, comma-separated, or "all" for 197
 * │    - dry-run   : print without writing
 * │
 * │  For each (source_root, target_country) it also replicates the sibling
 * │  language variants, so the new cluster has the same 9-language coverage.
 * └──────────────────────────────────────────────────────────────────────────
 */
class ReplicateClusterCommand extends Command
{
    protected $signature = 'landings:replicate-cluster
        {--source=          : Comma-separated landing_page ids (roots)}
        {--source-country=  : ISO2 — replicate all published roots of this country}
        {--target=          : Comma-separated ISO2 country codes, or "all"}
        {--dry-run          : Log without writing}
        {--limit=           : Max number of (source_root, target_country) combos to process}';

    protected $description = 'Replicate TH (or any) landing cluster to target countries — text substitution, no LLM.';

    public function handle(LandingGenerationService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit  = $this->option('limit') ? (int) $this->option('limit') : null;

        // 1. Resolve source roots
        $sourceIds = array_filter(array_map('trim', explode(',', (string) $this->option('source'))));
        $sourceCty = strtoupper((string) $this->option('source-country'));

        $sourceRoots = collect();
        if (! empty($sourceIds)) {
            $sourceRoots = LandingPage::published()
                ->whereNull('parent_id')
                ->whereIn('id', $sourceIds)
                ->get();
        } elseif ($sourceCty) {
            $sourceRoots = LandingPage::published()
                ->whereNull('parent_id')
                ->where('country_code', $sourceCty)
                ->get();
        } else {
            $this->error('Provide --source=<ids> OR --source-country=<ISO2>');
            return self::FAILURE;
        }

        if ($sourceRoots->isEmpty()) {
            $this->error('No source roots found.');
            return self::FAILURE;
        }

        // 2. Resolve target countries
        $targetRaw = (string) $this->option('target');
        if (! $targetRaw) {
            $this->error('--target is required (ISO2 codes or "all")');
            return self::FAILURE;
        }

        $targetCountries = $targetRaw === 'all'
            ? $this->allCountryCodes()
            : array_filter(array_map(fn($c) => strtoupper(trim($c)), explode(',', $targetRaw)));

        $this->info("Source roots: {$sourceRoots->count()} | Target countries: " . count($targetCountries));
        if ($dryRun) $this->warn('DRY RUN — no DB writes.');

        $processed = 0;
        $created   = 0;
        $skipped   = 0;

        foreach ($sourceRoots as $root) {
            foreach ($targetCountries as $tgtCc) {
                if ($tgtCc === $root->country_code) continue; // skip self
                if ($limit !== null && $processed >= $limit) break 2;

                $processed++;
                $this->line("\n[source #{$root->id} {$root->country_code}] → {$tgtCc}");

                // Does the target cluster already exist?
                $exists = LandingPage::where('country_code', $tgtCc)
                    ->whereNull('parent_id')
                    ->where('audience_type', $root->audience_type)
                    ->where('template_id',   $root->template_id)
                    ->when($root->category_slug, fn($q) => $q->where('category_slug', $root->category_slug))
                    ->when($root->user_profile,  fn($q) => $q->where('user_profile',  $root->user_profile))
                    ->when($root->origin_nationality, fn($q) => $q->where('origin_nationality', $root->origin_nationality))
                    ->exists();

                if ($exists) {
                    $this->line("  ~ skip: equivalent cluster already exists for {$tgtCc}");
                    $skipped++;
                    continue;
                }

                try {
                    $newRoot = $this->replicateOne($service, $root, $tgtCc, $dryRun);
                    $created++;
                    $msg = $dryRun ? '(dry-run)' : "created #{$newRoot->id}";
                    $this->info("  ✓ root {$msg}");

                    // Replicate each sibling (language variant)
                    $siblings = LandingPage::where('parent_id', $root->id)
                        ->whereNotIn('language', [$root->language])
                        ->get();
                    foreach ($siblings as $sib) {
                        if (! $dryRun) {
                            $this->replicateOne($service, $sib, $tgtCc, false, $newRoot->id);
                        }
                        $created++;
                        $this->line("    ↳ [{$sib->language}] " . ($dryRun ? '(dry-run)' : "sibling replicated"));
                    }

                    if (! $dryRun) {
                        $service->syncHreflangMap($newRoot);
                    }
                } catch (\Throwable $e) {
                    $this->error("  ✗ FAIL: " . $e->getMessage());
                    $skipped++;
                }
            }
        }

        $this->newLine();
        $this->info("Done. Processed={$processed}, rows created={$created}, skipped={$skipped}" . ($dryRun ? ' (dry run)' : ''));
        return self::SUCCESS;
    }

    /**
     * Clone one landing (root or sibling) to a different country by:
     *   - swapping country_code + country name in text fields
     *   - recomputing slug / canonical_url via LandingGenerationService::buildSlug
     *   - keeping audience_type, template_id, problem_id, category_slug, etc.
     */
    private function replicateOne(
        LandingGenerationService $service,
        LandingPage $src,
        string $newCountry,
        bool $dryRun,
        ?int $parentId = null,
    ): LandingPage {
        $newCountry    = strtoupper($newCountry);
        $srcCountry    = strtoupper((string) $src->country_code);
        $srcCountryName = $service->getCountryName($srcCountry, $src->language);
        $newCountryName = $service->getCountryName($newCountry, $src->language);
        $srcCountrySlug = $service->getCountrySlug($srcCountry, $src->language);
        $newCountrySlug = $service->getCountrySlug($newCountry, $src->language);

        // Build target slug via the same builder as the AI pipeline
        $problemSlug = $src->problem_id
            ?? $src->category_slug
            ?? $src->user_profile
            ?? ($src->origin_nationality ? strtolower($src->origin_nationality) : null);

        $newSlug = $service->buildSlug(
            $src->audience_type ?? 'clients',
            $src->language,
            $newCountrySlug,
            $problemSlug ? strtolower(str_replace('_', '-', $problemSlug)) : null,
            $src->template_id,
            null,
        );

        // Prepend locale region to the slug (fr → fr-vn, etc.) to match the
        // canonical URL format served by the Blog.
        if (str_contains($newSlug, '/') && ! str_contains(explode('/', $newSlug)[0], '-')) {
            $parts = explode('/', $newSlug, 2);
            $parts[0] .= '-' . strtolower($newCountry);
            $newSlug   = implode('/', $parts);
        }

        $newCanonical = rtrim(config('services.site_url', 'https://sos-expat.com'), '/') . '/' . $newSlug;

        // Substitution helper: replace the source country name by the target
        // in every language-neutral string we touch.
        $swap = function (?string $s) use ($srcCountryName, $newCountryName, $srcCountry, $newCountry, $srcCountrySlug, $newCountrySlug): ?string {
            if ($s === null) return null;
            $out = $s;
            // Longer string first to avoid mid-word collisions.
            foreach ([$srcCountryName, strtolower($srcCountryName), $srcCountrySlug, $srcCountry] as $from) {
                if (! $from) continue;
                $to = match ($from) {
                    $srcCountryName         => $newCountryName,
                    strtolower($srcCountryName) => strtolower($newCountryName),
                    $srcCountrySlug         => $newCountrySlug,
                    $srcCountry             => $newCountry,
                    default                 => $from,
                };
                $out = str_replace($from, $to, $out);
            }
            return $out;
        };

        $swapArr = function ($node) use (&$swapArr, $swap) {
            if (is_string($node)) return $swap($node);
            if (is_array($node)) {
                $out = [];
                foreach ($node as $k => $v) $out[$k] = $swapArr($v);
                return $out;
            }
            return $node;
        };

        $payload = [
            'parent_id'          => $parentId,
            'audience_type'      => $src->audience_type,
            'template_id'        => $src->template_id,
            'problem_id'         => $src->problem_id,
            'category_slug'      => $src->category_slug,
            'user_profile'       => $src->user_profile,
            'origin_nationality' => $src->origin_nationality,
            'country'            => $newCountryName,
            'country_code'       => $newCountry,
            'language'           => $src->language,
            'content_language'   => $src->content_language ?? $src->language,
            'title'              => $swap($src->title),
            'slug'               => $newSlug,
            'meta_title'         => $swap($src->meta_title),
            'meta_description'   => $swap($src->meta_description),
            'canonical_url'      => $newCanonical,
            'sections'           => $swapArr(is_array($src->sections) ? $src->sections : []),
            'json_ld'            => is_array($src->json_ld) ? $src->json_ld : [],
            'hreflang_map'       => [],
            'status'             => 'published',
            'published_at'       => now(),
            'date_published_at'  => now(),
            'date_modified_at'   => now(),
            'generation_source'  => 'replicated',
            'generation_cost_cents' => 0,
            'seo_score'          => (int) ($src->seo_score ?? 70),
            'featured_image_url' => $src->featured_image_url,
            'featured_image_alt' => $swap($src->featured_image_alt),
            'og_type'            => $src->og_type ?? 'article',
            'og_title'           => $swap($src->og_title),
            'og_description'     => $swap($src->og_description),
            'og_image'           => $src->og_image ?? $src->featured_image_url,
            'og_site_name'       => 'SOS-Expat',
            'og_locale'          => $src->language . '_' . $newCountry,
            'twitter_card'       => 'summary_large_image',
            'twitter_title'      => $swap($src->twitter_title),
            'twitter_description'=> $swap($src->twitter_description),
            'twitter_image'      => $src->twitter_image ?? $src->featured_image_url,
            'robots'             => 'index,follow',
            'geo_placename'      => $newCountryName,
        ];

        if ($dryRun) {
            $fake = new LandingPage();
            $fake->id = 0;
            return $fake;
        }
        return LandingPage::create($payload);
    }

    /** @return string[] */
    private function allCountryCodes(): array
    {
        // Pull the set from existing landing_pages — any country with at
        // least one published landing anywhere. Keeps the scope to countries
        // the business already knows, instead of all 249 ISO-3166 codes.
        return DB::table('landing_pages')
            ->whereNotNull('country_code')
            ->whereNull('deleted_at')
            ->where('status', 'published')
            ->select('country_code')
            ->distinct()
            ->pluck('country_code')
            ->map(fn($c) => strtoupper($c))
            ->values()
            ->all();
    }
}
