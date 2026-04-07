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
        if (!$content instanceof GeneratedArticle) {
            throw new \RuntimeException('BlogPublisher only supports GeneratedArticle models');
        }

        $config   = $endpoint->config ?? [];
        $blogUrl  = $config['blog_api_url'] ?? $config['url'] ?? config('services.blog.url', '');
        $apiToken = $config['blog_api_token'] ?? $config['api_key'] ?? config('services.blog.api_key', '');

        if (empty($blogUrl)) {
            throw new \RuntimeException('Blog API URL is required — set BLOG_API_URL in .env or blog_api_url in endpoint config');
        }

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
        $categorySlug = match ($parentArticle->content_type) {
            'guide', 'pillar'                                         => 'fiches-pays',
            'guide_city'                                              => 'fiches-villes',
            'article', 'tutorial'                                     => 'fiches-pratiques',
            'qa', 'comparative', 'news', 'testimonial', 'qa_needs',
                'press', 'press_release'                              => 'fiches-thematiques',
            'outreach'                                                => 'programme',
            'affiliation', 'landing'                                  => 'affiliation',
            default                                                   => 'fiches-pratiques',
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
            'content_type'       => $parentArticle->content_type ?? 'article',
            'source_slug'        => $parentArticle->source_slug ?? null,
            'category_slug'      => $categorySlug,
            'status'             => 'draft',
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
            'countries'          => $allCountries->unique()->values()->toArray(),
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
        $externalUrl = $this->buildArticleUrl($parentArticle, $data);

        // Update Mission Control article with published status + blog URL
        $parentArticle->update([
            'status'       => 'published',
            'published_at' => now(),
            'external_url' => $externalUrl,
            'external_id'  => $externalId,
        ]);

        // Also update translations with their respective blog URLs
        foreach ($parentArticle->translations ?? [] as $translation) {
            $translationUrl = str_replace(
                "/{$parentArticle->language}/",
                "/{$translation->language}/",
                $externalUrl
            );
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
            'title'            => $article->title,
            'slug'             => $article->slug,
            'content_html'     => $article->content_html,
            'excerpt'          => $article->excerpt,
            'meta_title'       => $article->meta_title,
            'meta_description' => $article->meta_description,
            'og_title'         => $article->og_title ?? $article->meta_title,
            'og_description'   => $article->og_description ?? $article->meta_description,
            'og_image_url'     => $article->featured_image_url,
            'ai_summary'       => $article->ai_summary ?? $article->excerpt,
        ];

        // FAQs for this language
        if ($article->relationLoaded('faqs') && $article->faqs->isNotEmpty()) {
            $faqs[$lang] = $article->faqs->map(fn ($faq) => [
                'question' => $faq->question,
                'answer'   => $faq->answer,
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

    /**
     * Build the public article URL from the blog response or fallback to convention.
     */
    private function buildArticleUrl(GeneratedArticle $article, array $responseData): ?string
    {
        // Prefer the URL returned by the blog API
        $apiUrl = $responseData['data']['url'] ?? $responseData['url'] ?? null;
        if ($apiUrl) {
            return $apiUrl;
        }

        // Fallback: build from convention
        $lang = $this->normalizeLanguageCode($article->language) ?? 'fr';
        $countryDefaults = [
            'fr' => 'fr', 'en' => 'us', 'es' => 'es', 'de' => 'de',
            'ru' => 'ru', 'pt' => 'pt', 'zh' => 'cn', 'hi' => 'in',
            'ar' => 'sa',
        ];
        $country = $countryDefaults[$lang] ?? 'fr';
        $slug    = $article->slug;

        $siteUrl = config('services.blog.site_url', 'https://blog.sos-expat.com');
        return rtrim($siteUrl, '/') . "/{$lang}-{$country}/articles/{$slug}";
    }
}
