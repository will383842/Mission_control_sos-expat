<?php

namespace App\Services\Publishing;

use App\Models\GeneratedArticle;
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
        // Support GeneratedArticle, Comparative, and QaEntry
        if (!$content instanceof GeneratedArticle
            && !$content instanceof \App\Models\Comparative
            && !$content instanceof \App\Models\QaEntry
        ) {
            throw new \RuntimeException('BlogPublisher supports GeneratedArticle, Comparative, and QaEntry models');
        }

        $config   = $endpoint->config ?? [];
        $blogUrl  = $config['blog_api_url'] ?? $config['url'] ?? config('services.blog.url', '');
        $apiToken = $config['blog_api_token'] ?? $config['api_key'] ?? config('services.blog.api_key', '');

        if (empty($blogUrl)) {
            throw new \RuntimeException('Blog API URL is required — set BLOG_API_URL in .env or blog_api_url in endpoint config');
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
                => 'fiches-pratiques',
            $parentArticle->content_type === 'outreach'
                => 'programme',
            in_array($parentArticle->content_type, ['affiliation', 'landing'])
                => 'affiliation',
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
            'og_site_name'     => $parentArticle->og_site_name ?? 'SOS Expat & Travelers',
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

        if ($response->failed()) {
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
