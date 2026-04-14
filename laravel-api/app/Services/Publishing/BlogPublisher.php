<?php

namespace App\Services\Publishing;

use App\Models\GeneratedArticle;
use App\Models\LandingPage;
use App\Models\PublishingEndpoint;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BlogPublisher
{
    /**
     * Publish an article to the Blog SOS-Expat.
     *
     * Collects the parent article + all translations + FAQs + images
     * and sends them in the multi-language format expected by the blog API.
     *
     * Expected endpoint config keys:
     *   - blog_api_url   (string, required) e.g. "https://blog.sos-expat.com"
     *   - blog_api_token  (string, required) Bearer token for authentication
     */
    public function publish(Model $content, PublishingEndpoint $endpoint): array
    {
        // Support GeneratedArticle, Comparative, QaEntry, and LandingPage
        if (!$content instanceof GeneratedArticle
            && !$content instanceof \App\Models\Comparative
            && !$content instanceof \App\Models\QaEntry
            && !$content instanceof LandingPage
        ) {
            throw new \RuntimeException('BlogPublisher supports GeneratedArticle, Comparative, QaEntry, and LandingPage models');
        }

        $config   = $endpoint->config ?? [];
        $blogUrl  = $config['blog_api_url'] ?? $config['url'] ?? config('services.blog.url', '');
        $apiToken = $config['blog_api_token'] ?? $config['api_key'] ?? config('services.blog.api_key', '');

        if (empty($blogUrl)) {
            throw new \RuntimeException('Blog API URL is required — set BLOG_API_URL in .env or blog_api_url in endpoint config');
        }

        // For LandingPage, use dedicated publish flow
        if ($content instanceof LandingPage) {
            return $this->publishLandingPage($content, $blogUrl, $apiToken);
        }

        // For Comparative and QaEntry, use simplified publish flow
        if ($content instanceof \App\Models\Comparative) {
            return $this->publishComparative($content, $blogUrl, $apiToken);
        }
        if ($content instanceof \App\Models\QaEntry) {
            return $this->publishQaEntry($content, $blogUrl, $apiToken);
        }

        // ── GeneratedArticle flow (full, with translations/faqs/images) ──

        // Resolve the parent article (if this is a translation child, go up)
        $parentArticle = $content->parent_article_id
            ? GeneratedArticle::find($content->parent_article_id) ?? $content
            : $content;

        $parentArticle->load([
            'faqs',
            'images',
            'sources',
            'translations.faqs',
            'translations.images',
        ]);

        // ── Build translations / FAQs / images maps ──────────────
        $translations = [];
        $faqs         = [];
        $allImages    = collect();
        $allCountries = collect();
        $allTags      = collect();

        // Parent article is the first translation
        $this->addTranslation($parentArticle, $translations, $faqs, $allImages);

        // Child translations
        foreach ($parentArticle->translations as $child) {
            $this->addTranslation($child, $translations, $faqs, $allImages);
        }

        // Collect countries from every variant
        foreach (array_merge([$parentArticle], $parentArticle->translations->all()) as $variant) {
            if ($variant->country) {
                $allCountries->push(strtoupper($variant->country));
            }
        }

        // Extract tags from keywords
        if ($parentArticle->keywords_primary) {
            $allTags->push($parentArticle->keywords_primary);
        }
        if (is_array($parentArticle->keywords_secondary)) {
            $allTags = $allTags->merge($parentArticle->keywords_secondary);
        }

        // Map content_type → blog category_slug (7-category taxonomy)
        // Category mapping — considers both content_type AND country
        // Articles about a specific country → fiches-pays
        // Articles about a city → fiches-pratiques (practical guides)
        // Generic/thematic articles → fiches-pratiques or fiches-thematiques
        $hasSpecificCountry = !empty($parentArticle->country)
            && mb_strlen($parentArticle->country) === 2
            && !in_array($parentArticle->content_type, ['outreach', 'affiliation', 'landing', 'news']);

        $categorySlug = match (true) {
            $parentArticle->content_type === 'guide' || $parentArticle->content_type === 'pillar'
                => 'fiches-pays',
            $parentArticle->content_type === 'guide_city'
                => 'fiches-villes',
            $parentArticle->content_type === 'outreach'
                => 'programme',
            in_array($parentArticle->content_type, ['affiliation', 'landing'])
                => 'affiliation',
            in_array($parentArticle->content_type, ['tutorial', 'pain_point'])
                => 'fiches-pratiques',
            in_array($parentArticle->content_type, ['qa', 'comparative', 'qa_needs'])
                => 'fiches-thematiques',
            in_array($parentArticle->content_type, ['news', 'press', 'press_release', 'testimonial'])
                => 'fiches-thematiques',
            // Country-specific articles (expatriation Dubai, coût de vie Portugal) → fiches-pays
            $hasSpecificCountry && in_array($parentArticle->content_type, ['article', 'statistics'])
                => 'fiches-pays',
            // Generic articles/tutorials → fiches-pratiques
            default => 'fiches-pratiques',
        };

        // ── Build sources array ──────────────────────────────────
        $sources = [];
        if ($parentArticle->relationLoaded('sources') && $parentArticle->sources->isNotEmpty()) {
            $sources = $parentArticle->sources->map(fn ($s) => [
                'url'         => $s->url,
                'title'       => $s->title ?? null,
                'domain'      => $s->domain ?? parse_url($s->url, PHP_URL_HOST),
                'trust_score' => $s->trust_score ?? null,
            ])->toArray();
        }

        // ── Build payload ────────────────────────────────────────
        $payload = [
            'idempotency_key'    => $parentArticle->uuid ?? ($parentArticle->id . '_' . now()->timestamp),
            'external_id'        => $parentArticle->uuid,
            // Map content_type to Blog API accepted values (outreach/affiliation/landing → article)
            'content_type'       => match ($parentArticle->content_type) {
                'outreach', 'affiliation', 'landing', 'testimonial', 'press_release' => 'article',
                default => $parentArticle->content_type ?? 'article',
            },
            'source_slug'        => $parentArticle->source_slug ?? null,
            'category_slug'      => $categorySlug,
            'status'             => 'published',
            'featured_image_url' => $parentArticle->featured_image_url,
            'featured_image_alt' => $parentArticle->featured_image_alt,
            'featured_image_attribution' => $parentArticle->featured_image_attribution,
            'photographer_name' => $parentArticle->photographer_name,
            'photographer_url' => $parentArticle->photographer_url,
            'featured_image_srcset' => $parentArticle->featured_image_srcset,
            'source_url'         => $parentArticle->canonical_url,
            'seo_score'          => $parentArticle->seo_score,
            'quality_score'      => $parentArticle->quality_score,
            'keywords_primary'   => $parentArticle->keywords_primary,
            'keywords_secondary' => $parentArticle->keywords_secondary ?? [],
            'readability_score'  => $parentArticle->readability_score,
            'translations'       => $translations,
            'faqs'               => $faqs,
            'sources'            => $sources,
            'images'             => $allImages->unique('url')->values()->toArray(),
            'tags'               => $allTags->unique()->values()->toArray(),
            'countries'          => $allCountries->unique()->filter(fn ($c) => mb_strlen($c) === 2)->values()->toArray(),
            // Extended SEO / geo / OG / Twitter fields
            'og_type'          => $parentArticle->og_type ?? 'article',
            'og_locale'        => $parentArticle->og_locale ?? null,
            'og_url'           => $parentArticle->og_url ?? $parentArticle->canonical_url ?? null,
            'og_site_name'     => $parentArticle->og_site_name ?? 'SOS-Expat & Travelers',
            'twitter_card'     => $parentArticle->twitter_card ?? 'summary_large_image',
            'geo_region'       => $parentArticle->geo_region ?? null,
            'geo_placename'    => $parentArticle->geo_placename ?? null,
            'geo_position'     => $parentArticle->geo_position ?? null,
            'icbm'             => $parentArticle->icbm ?? null,
            'meta_keywords'    => $parentArticle->meta_keywords ?? null,
            'content_language' => $parentArticle->content_language ?? $parentArticle->language ?? null,
            'last_reviewed_at' => $parentArticle->last_reviewed_at?->toIso8601String(),
            'noindex'          => false,
        ];

        // ── Extract and send table of contents ────────────────────
        if ($parentArticle->content_html) {
            $toc = app(\App\Services\Seo\JsonLdService::class)->extractTableOfContents($parentArticle->content_html);
            if (!empty($toc)) {
                $payload['table_of_contents'] = $toc;
            }
        }

        // ── Sign request with HMAC-SHA256 (replay-safe) ─────────
        // IMPORTANT: use same JSON flags as withBody() so signed bytes = sent bytes.
        // Default json_encode() escapes Unicode (\u00e9) while Guzzle sends UTF-8 (é).
        $timestamp = (string) time();
        $body      = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $timestamp . '.' . $body, $apiToken);

        // ── Send to blog API ─────────────────────────────────────
        Log::info('BlogPublisher: sending article to blog', [
            'uuid'         => $parentArticle->uuid,
            'languages'    => array_keys($translations),
            'faq_langs'    => array_keys($faqs),
            'images_count' => $allImages->count(),
        ]);

        $response = Http::withHeaders([
                'X-Webhook-Timestamp' => $timestamp,
                'X-Webhook-Signature' => $signature,
                'Content-Type'        => 'application/json',
                'Accept'              => 'application/json',
            ])
            ->withBody($body, 'application/json')
            ->timeout(30)
            ->post(rtrim($blogUrl, '/') . '/api/v1/articles');

        // 409 = article already exists (deduplicated by external_id) — treat as success
        if ($response->status() === 409) {
            $existingData = $response->json();
            Log::info('BlogPublisher: article already exists (409), treating as published', [
                'uuid' => $parentArticle->uuid,
                'existing_id' => $existingData['data']['id'] ?? $existingData['id'] ?? null,
            ]);
            $data = $existingData;
        } elseif ($response->failed()) {
            $error = $response->json('message') ?? $response->body();
            Log::error('BlogPublisher: API error', [
                'status' => $response->status(),
                'body'   => mb_substr($response->body(), 0, 1000),
            ]);
            throw new \RuntimeException("Blog API error ({$response->status()}): {$error}");
        }

        $data = $response->json();

        Log::info('BlogPublisher: published successfully', [
            'uuid'        => $parentArticle->uuid,
            'blog_id'     => $data['data']['id'] ?? $data['id'] ?? null,
        ]);

        $externalId  = (string) ($data['data']['id'] ?? $data['id'] ?? $parentArticle->uuid);

        // Build URLs from Blog API response translations (they have the real slugs)
        $blogTranslations = $data['data']['translations'] ?? $data['translations'] ?? [];
        $siteUrl = rtrim(config('services.blog.site_url', 'https://sos-expat.com'), '/');

        // Find the parent article's URL from the Blog response
        $parentLang = $this->normalizeLanguageCode($parentArticle->language) ?? 'fr';
        $parentBlogSlug = null;
        foreach ($blogTranslations as $bt) {
            $btLang = $bt['language_code'] ?? $bt['lang'] ?? null;
            if ($btLang === $parentLang) {
                $parentBlogSlug = $bt['slug'] ?? null;
                break;
            }
        }
        $externalUrl = $this->buildUrlForLanguage($siteUrl, $parentLang, $parentBlogSlug ?? $parentArticle->slug);

        // Update Mission Control article with published status + blog URL
        $parentArticle->update([
            'status'       => 'published',
            'published_at' => now(),
            'external_url' => $externalUrl,
            'external_id'  => $externalId,
        ]);

        // Also update translations with their respective blog URLs
        foreach ($parentArticle->translations ?? [] as $translation) {
            $transLang = $this->normalizeLanguageCode($translation->language) ?? 'fr';
            // Find slug from Blog API response for this language
            $transBlogSlug = null;
            foreach ($blogTranslations as $bt) {
                $btLang = $bt['language_code'] ?? $bt['lang'] ?? null;
                if ($btLang === $transLang) {
                    $transBlogSlug = $bt['slug'] ?? null;
                    break;
                }
            }
            $translationUrl = $this->buildUrlForLanguage($siteUrl, $transLang, $transBlogSlug ?? $translation->slug);
            $translation->update([
                'status'       => 'published',
                'published_at' => now(),
                'external_url' => $translationUrl,
                'external_id'  => $externalId,
            ]);
        }

        return [
            'external_id'  => $externalId,
            'external_url' => $externalUrl,
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────

    /**
     * Add a single article variant (parent or child) to the translations & FAQs maps.
     */
    private function addTranslation(
        GeneratedArticle $article,
        array &$translations,
        array &$faqs,
        &$allImages,
    ): void {
        $lang = $this->normalizeLanguageCode($article->language);
        if (!$lang) {
            return;
        }

        $translations[$lang] = [
            'title'            => mb_substr(strip_tags($article->title), 0, 255),
            'slug'             => $article->slug,
            'content_html'     => $article->content_html,
            'excerpt'          => mb_substr($article->excerpt ?? '', 0, 500) ?: null,
            'meta_title'       => mb_substr($article->meta_title ?? '', 0, 255) ?: null,
            'meta_description' => mb_substr($article->meta_description ?? '', 0, 500) ?: null,
            'og_title'         => mb_substr($article->og_title ?? $article->meta_title ?? '', 0, 255) ?: null,
            'og_description'   => mb_substr($article->og_description ?? $article->meta_description ?? '', 0, 500) ?: null,
            'og_image_url'     => $article->featured_image_url,
            'ai_summary'       => $article->ai_summary ?? $article->excerpt,
        ];

        // FAQs for this language — strip HTML tags and truncate
        if ($article->relationLoaded('faqs') && $article->faqs->isNotEmpty()) {
            $faqs[$lang] = $article->faqs->map(fn ($faq) => [
                'question' => mb_substr(strip_tags($faq->question), 0, 255),
                'answer'   => strip_tags($faq->answer, '<p><br><a><strong><em><ul><ol><li>'),
            ])->toArray();
        }

        // Images
        if ($article->relationLoaded('images') && $article->images->isNotEmpty()) {
            foreach ($article->images as $img) {
                $allImages->push([
                    'url'         => $img->url,
                    'alt_text'    => $img->alt_text,
                    'source'      => $img->source ?? 'external',
                    'attribution' => $img->attribution,
                    'width'       => $img->width,
                    'height'      => $img->height,
                ]);
            }
        }
    }

    /**
     * Normalize language codes (the tool uses 'ch' for Chinese internally).
     */
    private function normalizeLanguageCode(?string $code): ?string
    {
        if (!$code) {
            return null;
        }

        return $code === 'ch' ? 'zh' : $code;
    }

    // ── Comparative publish (simplified payload) ────────────────
    private function publishComparative(\App\Models\Comparative $comparative, string $blogUrl, string $apiToken): array
    {
        $lang = $this->normalizeLanguageCode($comparative->language) ?? 'fr';

        $translations = [];
        $translations[$lang] = [
            'title'            => mb_substr(strip_tags($comparative->title), 0, 255),
            'slug'             => $comparative->slug,
            'content_html'     => $comparative->content_html,
            'excerpt'          => $comparative->excerpt,
            'meta_title'       => mb_substr($comparative->meta_title ?? '', 0, 255) ?: null,
            'meta_description' => mb_substr($comparative->meta_description ?? '', 0, 500) ?: null,
            'json_ld'          => $comparative->json_ld,
        ];

        // Include child translations if any
        if (method_exists($comparative, 'translations') && $comparative->translations) {
            foreach ($comparative->translations as $child) {
                $childLang = $this->normalizeLanguageCode($child->language) ?? $lang;
                $translations[$childLang] = [
                    'title'            => mb_substr(strip_tags($child->title), 0, 255),
                    'slug'             => $child->slug,
                    'content_html'     => $child->content_html,
                    'excerpt'          => $child->excerpt,
                    'meta_title'       => mb_substr($child->meta_title ?? '', 0, 255) ?: null,
                    'meta_description' => mb_substr($child->meta_description ?? '', 0, 500) ?: null,
                    'json_ld'          => $child->json_ld,
                ];
            }
        }

        $payload = [
            'idempotency_key' => $comparative->uuid ?? 'comp_' . $comparative->id,
            'external_id'     => $comparative->uuid,
            'content_type'    => 'comparative',
            'category_slug'   => 'fiches-thematiques',
            'status'          => 'published',
            'seo_score'       => $comparative->seo_score,
            'quality_score'   => $comparative->quality_score,
            'translations'    => $translations,
            'faqs'            => [],
            'sources'         => [],
            'images'          => [],
            'tags'            => [],
            'countries'       => $comparative->country ? [strtoupper($comparative->country)] : [],
        ];

        return $this->sendPayload($payload, $blogUrl, $apiToken, $comparative);
    }

    // ── QaEntry publish (simplified payload) ────────────────────
    private function publishQaEntry(\App\Models\QaEntry $entry, string $blogUrl, string $apiToken): array
    {
        $lang = $this->normalizeLanguageCode($entry->language) ?? 'fr';

        $translations = [];
        $translations[$lang] = [
            'title'            => mb_substr(strip_tags($entry->question), 0, 255),
            'slug'             => $entry->slug,
            'content_html'     => $entry->answer_detailed_html,
            'excerpt'          => $entry->answer_short,
            'meta_title'       => mb_substr($entry->meta_title ?? $entry->question ?? '', 0, 255) ?: null,
            'meta_description' => mb_substr($entry->meta_description ?? $entry->answer_short ?? '', 0, 500) ?: null,
            'json_ld'          => $entry->json_ld,
        ];

        // Include child translations if any
        if (method_exists($entry, 'translations') && $entry->translations) {
            foreach ($entry->translations as $child) {
                $childLang = $this->normalizeLanguageCode($child->language) ?? $lang;
                $translations[$childLang] = [
                    'title'            => mb_substr(strip_tags($child->question), 0, 255),
                    'slug'             => $child->slug,
                    'content_html'     => $child->answer_detailed_html,
                    'excerpt'          => $child->answer_short,
                    'meta_title'       => mb_substr($child->meta_title ?? $child->question ?? '', 0, 255) ?: null,
                    'meta_description' => mb_substr($child->meta_description ?? $child->answer_short ?? '', 0, 500) ?: null,
                    'json_ld'          => $child->json_ld,
                ];
            }
        }

        $payload = [
            'idempotency_key' => $entry->uuid ?? 'qa_' . $entry->id,
            'external_id'     => $entry->uuid,
            'content_type'    => 'qa',
            'category_slug'   => 'fiches-thematiques',
            'status'          => 'published',
            'seo_score'       => $entry->seo_score,
            'quality_score'   => null,
            'keywords_primary' => $entry->keywords_primary,
            'translations'    => $translations,
            'faqs'            => [],
            'sources'         => is_array($entry->sources) ? $entry->sources : [],
            'images'          => [],
            'tags'            => [],
            'countries'       => $entry->country ? [strtoupper($entry->country)] : [],
        ];

        return $this->sendPayload($payload, $blogUrl, $apiToken, $entry);
    }

    // ── Shared: send payload to blog API ────────────────────────
    private function sendPayload(array $payload, string $blogUrl, string $apiToken, Model $content): array
    {
        $timestamp = (string) time();
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $timestamp . '.' . $body, $apiToken);

        $response = Http::withHeaders([
            'X-Webhook-Timestamp' => $timestamp,
            'X-Webhook-Signature' => $signature,
            'Content-Type'        => 'application/json',
        ])->timeout(30)->withBody($body, 'application/json')
          ->post(rtrim($blogUrl, '/') . '/api/v1/articles');

        if (!$response->successful()) {
            throw new \RuntimeException('Blog API error: HTTP ' . $response->status() . ' — ' . mb_substr($response->body(), 0, 500));
        }

        $data = $response->json();
        $externalId  = (string) ($data['data']['id'] ?? $data['id'] ?? $content->uuid ?? $content->id);

        // Build URL from Blog API response translations
        $blogTranslations = $data['data']['translations'] ?? $data['translations'] ?? [];
        $siteUrl = rtrim(config('services.blog.site_url', 'https://sos-expat.com'), '/');
        $contentLang = $this->normalizeLanguageCode($content->language) ?? 'fr';
        $blogSlug = null;
        foreach ($blogTranslations as $bt) {
            if (($bt['language_code'] ?? $bt['lang'] ?? null) === $contentLang) {
                $blogSlug = $bt['slug'] ?? null;
                break;
            }
        }
        $externalUrl = $this->buildUrlForLanguage($siteUrl, $contentLang, $blogSlug ?? $content->slug);

        $content->update([
            'status'       => 'published',
            'published_at' => now(),
            'external_url' => $externalUrl,
            'external_id'  => $externalId,
        ]);

        Log::info('BlogPublisher: published ' . class_basename($content), [
            'id'          => $content->id,
            'external_id' => $externalId,
        ]);

        return ['external_id' => $externalId, 'external_url' => $externalUrl];
    }

    // ── LandingPage publish ─────────────────────────────────────
    //
    // On utilise le même endpoint /api/v1/articles que les articles classiques,
    // avec content_type='landing'. Le contenu HTML est généré depuis les sections JSON.
    // Cela évite d'avoir à créer un nouvel endpoint sur le blog Laravel.
    //
    private function publishLandingPage(LandingPage $landing, string $blogUrl, string $apiToken): array
    {
        if (! $landing->relationLoaded('ctaLinks')) {
            $landing->load('ctaLinks');
        }

        $lang        = $this->normalizeLanguageCode($landing->language) ?? 'fr';
        $contentHtml = $this->sectionsToHtml($landing->sections ?? [], $landing->ctaLinks->toArray());
        $faqs        = $this->extractFaqsFromSections($landing->sections ?? [], $lang);

        // ── Translations map ───────────────────────────────────────
        $translations = [];
        $translations[$lang] = [
            'title'            => mb_substr(strip_tags($landing->title), 0, 255),
            'slug'             => $landing->slug,
            'content_html'     => $contentHtml,
            'excerpt'          => mb_substr($landing->meta_description ?? '', 0, 500) ?: null,
            'meta_title'       => mb_substr($landing->meta_title ?? '', 0, 70) ?: null,
            'meta_description' => mb_substr($landing->meta_description ?? '', 0, 160) ?: null,
            'og_title'         => mb_substr($landing->meta_title ?? $landing->title ?? '', 0, 70) ?: null,
            'og_description'   => mb_substr($landing->meta_description ?? '', 0, 160) ?: null,
            'og_image_url'     => $landing->featured_image_url,
            'json_ld'          => $landing->json_ld,     // @graph complet
            'hreflang_map'     => $landing->hreflang_map ?? [],
            'canonical_url'    => $landing->canonical_url,
        ];

        // ── Category slug selon audience ───────────────────────────
        $categorySlug = match ($landing->audience_type) {
            'clients'         => 'fiches-pratiques',
            'lawyers'         => 'programme',
            'helpers'         => 'programme',
            'matching'        => 'fiches-pratiques',
            'category_pillar' => 'fiches-pratiques',
            'profile'         => 'fiches-pratiques',
            'emergency'       => 'urgences',
            'nationality'     => 'fiches-pratiques',
            default           => 'fiches-pratiques',
        };

        // ── Hreflang complet avec URLs absolues + x-default ───────
        $hreflangMap = $landing->hreflang_map ?? [];
        // S'assurer que x-default est présent
        if (!isset($hreflangMap['x-default'])) {
            $hreflangMap['x-default'] = $hreflangMap['en'] ?? reset($hreflangMap) ?: '';
        }

        // ── Payload complet ────────────────────────────────────────
        $payload = [
            'idempotency_key'    => $landing->uuid ?? 'lp_' . $landing->id,
            'external_id'        => $landing->uuid,
            'content_type'       => 'landing',
            'category_slug'      => $categorySlug,
            'status'             => 'published',

            // Image Unsplash
            'featured_image_url'         => $landing->featured_image_url,
            'featured_image_alt'         => $landing->featured_image_alt,
            'featured_image_attribution' => $landing->featured_image_attribution,
            'photographer_name'          => $landing->photographer_name,
            'photographer_url'           => $landing->photographer_url,

            // SEO scores
            'seo_score'          => $landing->seo_score,
            'quality_score'      => null,
            'keywords_primary'   => $landing->problem_id ?? $landing->template_id,

            // Translations & FAQs
            'translations'       => $translations,
            'faqs'               => $faqs,
            'sources'            => [],
            'images'             => [],

            // Tags & geo context
            'tags'               => array_values(array_filter([
                $landing->audience_type,
                $landing->template_id,
                $landing->country_code,
                $landing->problem_id,
            ])),
            'countries'          => $landing->country_code ? [strtoupper($landing->country_code)] : [],

            // Keywords SEO
            'keywords_primary'   => $landing->keywords_primary ?? $landing->problem_id ?? $landing->template_id,
            'keywords_secondary' => $landing->keywords_secondary ?? [],

            // OpenGraph complet (titre, description, image pour rich preview social)
            'og_type'            => $landing->og_type ?? 'WebPage',
            'og_locale'          => $landing->og_locale,
            'og_url'             => $landing->og_url ?? $landing->canonical_url,
            'og_site_name'       => $landing->og_site_name ?? 'SOS-Expat & Travelers',
            'og_title'           => $landing->og_title ?? $landing->meta_title ?? $landing->title,
            'og_description'     => $landing->og_description ?? $landing->meta_description,
            'og_image'           => $landing->og_image ?? $landing->featured_image_url,

            // Twitter card complet
            'twitter_card'            => $landing->twitter_card ?? 'summary_large_image',
            'twitter_title'           => $landing->twitter_title ?? $landing->meta_title ?? $landing->title,
            'twitter_description'     => $landing->twitter_description ?? $landing->meta_description,
            'twitter_image'           => $landing->twitter_image ?? $landing->featured_image_url,

            // Robots
            'robots'             => $landing->robots ?? 'index,follow',

            // Design template (pilier visuel pour le rendu côté blog)
            'design_template'    => $landing->design_template ?? 'informational',

            // Freshness signals (datePublished / dateModified pour JSON-LD et SERP)
            'date_published_at'       => $landing->date_published_at?->toIso8601String()
                                         ?? $landing->created_at?->toIso8601String(),
            'date_modified_at'        => $landing->date_modified_at?->toIso8601String()
                                         ?? now()->toIso8601String(),
            // Open Graph article times (interprétés même pour og:type=WebPage par crawlers)
            'article_published_time'  => $landing->date_published_at?->toIso8601String()
                                         ?? $landing->created_at?->toIso8601String(),
            'article_modified_time'   => $landing->date_modified_at?->toIso8601String()
                                         ?? now()->toIso8601String(),

            // Geo metadata (pour référencement local)
            'content_language'   => $landing->content_language ?? $landing->language,
            'geo_region'         => $landing->geo_region,
            'geo_placename'      => $landing->geo_placename,
            'geo_position'       => $landing->geo_position,
            'icbm'               => $landing->icbm,

            // Hreflang avec x-default
            'hreflang_map'       => $hreflangMap,

            // JSON-LD @graph complet (Organisation + WebPage + FAQPage + HowTo + Service + AggregateRating)
            'json_ld'            => $landing->json_ld ?? [],

            'noindex'            => false,
            'last_reviewed_at'   => now()->toIso8601String(),

            // Landing-specific extras
            'landing_audience_type' => $landing->audience_type,
            'landing_template_id'   => $landing->template_id,
            'landing_problem_id'    => $landing->problem_id,
            'landing_sections'      => $landing->sections ?? [],
            'landing_cta_links'     => $landing->ctaLinks->map(fn ($c) => [
                'text'     => $c->text,
                'url'      => $c->url,
                'position' => $c->position,
                'style'    => $c->style ?? 'primary',
            ])->toArray(),
        ];

        // ── Signature HMAC-SHA256 ──────────────────────────────────
        $timestamp = (string) time();
        $body      = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $timestamp . '.' . $body, $apiToken);

        Log::info('BlogPublisher: sending landing page to blog API', [
            'landing_id'     => $landing->id,
            'slug'           => $landing->slug,
            'audience_type'  => $landing->audience_type,
            'country_code'   => $landing->country_code,
            'language'       => $lang,
            'og_locale'      => $landing->og_locale,
            'has_json_ld'    => !empty($landing->json_ld),
            'hreflang_langs' => array_keys($hreflangMap),
        ]);

        $response = Http::withHeaders([
                'X-Webhook-Timestamp' => $timestamp,
                'X-Webhook-Signature' => $signature,
                'Content-Type'        => 'application/json',
                'Accept'              => 'application/json',
            ])
            ->withBody($body, 'application/json')
            ->timeout(30)
            ->post(rtrim($blogUrl, '/') . '/api/v1/articles');

        // 409 = déjà publiée (external_id dédupliqué) — traiter comme succès
        if ($response->status() === 409) {
            $bodyData = $response->json();
            Log::info('BlogPublisher: landing page déjà publiée (409)', [
                'landing_id' => $landing->id,
            ]);
        } elseif ($response->failed()) {
            Log::error('BlogPublisher: erreur API landing page', [
                'status'     => $response->status(),
                'landing_id' => $landing->id,
                'body'       => mb_substr($response->body(), 0, 1000),
            ]);
            throw new \RuntimeException(
                "Blog API error ({$response->status()}) landing #{$landing->id}: " . mb_substr($response->body(), 0, 400)
            );
        } else {
            $bodyData = $response->json();
        }

        // ── URL externe depuis réponse blog ───────────────────────
        $blogTranslations = $bodyData['data']['translations'] ?? $bodyData['translations'] ?? [];
        $siteUrl          = rtrim(config('services.blog.site_url', 'https://sos-expat.com'), '/');
        $blogSlug         = null;
        foreach ($blogTranslations as $bt) {
            if (($bt['language_code'] ?? $bt['lang'] ?? null) === $lang) {
                $blogSlug = $bt['slug'] ?? null;
                break;
            }
        }
        $externalUrl = $landing->canonical_url
            ?? $this->buildUrlForLanguage($siteUrl, $lang, $blogSlug ?? $landing->slug);
        $externalId  = (string) ($bodyData['data']['id'] ?? $bodyData['id'] ?? $landing->uuid ?? $landing->id);

        try {
            $landing->update([
                'status'           => 'published',
                'published_at'     => now(),
                'external_url'     => $externalUrl,
                'external_id'      => $externalId,
                'last_reviewed_at' => now(),
            ]);
        } catch (\Exception $e) {
            // last_reviewed_at peut ne pas encore être dans $fillable si la migration est en attente
            $landing->update([
                'status'       => 'published',
                'published_at' => now(),
                'external_url' => $externalUrl,
                'external_id'  => $externalId,
            ]);
        }

        Log::info('BlogPublisher: landing page publiée avec succès', [
            'landing_id'   => $landing->id,
            'external_id'  => $externalId,
            'external_url' => $externalUrl,
        ]);

        return [
            'external_id'  => $externalId,
            'external_url' => $externalUrl,
        ];
    }

    /**
     * Convertit les sections JSON d'une landing page en HTML sémantique.
     * Utilisé pour l'envoi au blog via /api/v1/articles.
     */
    private function sectionsToHtml(array $sections, array $ctaLinks = []): string
    {
        $html = '';

        foreach ($sections as $section) {
            $type    = $section['type'] ?? '';
            $content = $section['content'] ?? [];

            $html .= match ($type) {
                'hero' => sprintf(
                    '<section class="lp-hero"><h1>%s</h1><p>%s</p></section>',
                    e($content['h1'] ?? ''),
                    e($content['subtitle'] ?? ''),
                ),
                'trust_signals' => $this->renderListSection('lp-trust', 'Pourquoi nous choisir', $content['items'] ?? [], 'text'),
                'why_us'        => $this->renderListSection('lp-why-us', $content['headline'] ?? 'Pourquoi nous ?', $content['items'] ?? [], 'text'),
                'guide_steps'   => $this->renderStepsSection($content),
                'local_info'    => sprintf(
                    '<section class="lp-local"><h2>Infos pratiques</h2><ul><li>Ambassade : %s</li><li>Urgences : %s</li><li>%s</li></ul></section>',
                    e($content['embassy'] ?? ''),
                    e($content['emergency_number'] ?? ''),
                    e($content['tip'] ?? ''),
                ),
                'faq' => $this->renderFaqSection($content['items'] ?? []),
                'earnings' => sprintf(
                    '<section class="lp-earnings"><h2>%s</h2><p class="amount">%s</p><p>%s</p></section>',
                    e($content['headline'] ?? ''),
                    e($content['amount'] ?? ''),
                    e($content['detail'] ?? ''),
                ),
                'process'  => $this->renderStepsSection($content, 'steps'),
                'freedom'  => $this->renderListSection('lp-freedom', $content['headline'] ?? 'Liberté totale', $content['items'] ?? [], 'text'),
                'what_you_do' => $this->renderListSection('lp-what', $content['headline'] ?? 'Votre rôle', $content['items'] ?? [], 'text'),
                'community_proof', 'testimonial_proof', 'no_pressure',
                'client_quality', 'lawyer_advantages', 'helper_advantages' =>
                    $this->renderListSection('lp-' . $type, $content['headline'] ?? '', $content['items'] ?? [], 'text'),
                'cta' => sprintf(
                    '<section class="lp-cta"><h2>%s</h2><p>%s</p></section>',
                    e($content['headline'] ?? ''),
                    e($content['subtext'] ?? ''),
                ),
                default => '',
            };
        }

        return $html;
    }

    private function renderListSection(string $class, string $headline, array $items, string $textKey = 'text'): string
    {
        if (empty($items)) return '';
        $lis = implode('', array_map(fn ($i) => '<li>' . e($i[$textKey] ?? '') . '</li>', $items));
        return "<section class=\"{$class}\"><h2>" . e($headline) . "</h2><ul>{$lis}</ul></section>";
    }

    private function renderStepsSection(array $content, string $key = 'steps'): string
    {
        $steps = $content[$key] ?? [];
        if (empty($steps)) return '';
        $lis = implode('', array_map(fn ($s) => '<li><strong>' . e($s['num'] ?? '') . '.</strong> ' . e($s['title'] ?? $s['label'] ?? '') . '</li>', $steps));
        return "<section class=\"lp-process\"><h2>" . e($content['headline'] ?? 'Comment ça marche ?') . "</h2><ol>{$lis}</ol></section>";
    }

    private function renderFaqSection(array $items): string
    {
        if (empty($items)) return '';
        $html = '<section class="lp-faq"><h2>Questions fréquentes</h2>';
        foreach ($items as $item) {
            $html .= '<div class="faq-item"><h3>' . e($item['q'] ?? '') . '</h3><p>' . e($item['a'] ?? '') . '</p></div>';
        }
        $html .= '</section>';
        return $html;
    }

    /**
     * Extrait les FAQs des sections pour les envoyer au blog dans le format attendu.
     */
    private function extractFaqsFromSections(array $sections, string $lang): array
    {
        foreach ($sections as $section) {
            if ($section['type'] === 'faq' && ! empty($section['content']['items'])) {
                return [
                    $lang => array_map(fn ($item) => [
                        'question' => $item['q'] ?? '',
                        'answer'   => $item['a'] ?? '',
                    ], $section['content']['items']),
                ];
            }
        }
        return [];
    }

    /**
     * Build a public article URL for a given language.
     * Matches the Blog's route-segments.php convention: /{lang}-{country}/{segment}/{slug}
     */
    private function buildUrlForLanguage(string $siteUrl, string $lang, string $slug): string
    {
        $countryDefaults = [
            'fr' => 'fr', 'en' => 'us', 'es' => 'es', 'de' => 'de',
            'ru' => 'ru', 'pt' => 'pt', 'zh' => 'cn', 'hi' => 'in',
            'ar' => 'sa',
        ];

        // Localized URL segment for "articles" per language
        $articleSegments = [
            'fr' => 'articles',   'en' => 'articles',
            'es' => 'articulos',  'de' => 'artikel',
            'pt' => 'artigos',    'ru' => 'stati',
            'zh' => 'wenzhang',   'hi' => 'lekh',
            'ar' => 'maqalat',
        ];

        $country = $countryDefaults[$lang] ?? 'fr';
        $segment = $articleSegments[$lang] ?? 'articles';

        return "{$siteUrl}/{$lang}-{$country}/{$segment}/{$slug}";
    }
}
