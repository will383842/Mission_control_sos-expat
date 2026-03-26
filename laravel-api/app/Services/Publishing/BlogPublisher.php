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
        $blogUrl  = $config['blog_api_url'] ?? '';
        $apiToken = $config['blog_api_token'] ?? '';

        if (empty($blogUrl) || empty($apiToken)) {
            throw new \RuntimeException('Blog API URL and token are required in endpoint config');
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

        // Map content_type → category_slug
        $categorySlug = match ($parentArticle->content_type) {
            'landing'                  => 'landing-page',
            'press', 'press_release'   => 'press-release',
            default                    => $parentArticle->content_type ?? 'article',
        };

        // ── Build payload ────────────────────────────────────────
        $payload = [
            'external_id'        => $parentArticle->uuid,
            'content_type'       => $parentArticle->content_type ?? 'article',
            'category_slug'      => $categorySlug,
            'status'             => 'draft',
            'featured_image_url' => $parentArticle->featured_image_url,
            'featured_image_alt' => $parentArticle->featured_image_alt,
            'source_url'         => $parentArticle->canonical_url,
            'seo_score'          => $parentArticle->seo_score,
            'quality_score'      => $parentArticle->quality_score,
            'keywords_primary'   => $parentArticle->keywords_primary,
            'keywords_secondary' => $parentArticle->keywords_secondary ?? [],
            'readability_score'  => $parentArticle->readability_score,
            'translations'       => $translations,
            'faqs'               => $faqs,
            'images'             => $allImages->unique('url')->values()->toArray(),
            'tags'               => $allTags->unique()->values()->toArray(),
            'countries'          => $allCountries->unique()->values()->toArray(),
        ];

        // ── Send to blog API ─────────────────────────────────────
        Log::info('BlogPublisher: sending article to blog', [
            'uuid'         => $parentArticle->uuid,
            'languages'    => array_keys($translations),
            'faq_langs'    => array_keys($faqs),
            'images_count' => $allImages->count(),
        ]);

        $response = Http::withToken($apiToken)
            ->timeout(30)
            ->post(rtrim($blogUrl, '/') . '/api/v1/articles', $payload);

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

        return [
            'external_id'  => (string) ($data['data']['id'] ?? $data['id'] ?? $parentArticle->uuid),
            'external_url' => $this->buildArticleUrl($parentArticle, $data),
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
            'og_title'         => $article->meta_title,
            'og_description'   => $article->meta_description,
            'og_image_url'     => $article->featured_image_url,
            'ai_summary'       => $article->excerpt,
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

        return "https://sos-expat.com/{$lang}-{$country}/articles/{$slug}";
    }
}
