<?php

namespace App\Services\Seo;

use App\Models\Comparative;
use App\Models\GeneratedArticle;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * JSON-LD structured data generation for SEO.
 */
class JsonLdService
{
    private const ORGANIZATION_NAME = 'SOS-Expat';
    private const ORGANIZATION_URL = 'https://www.sos-expat.com';
    private const LOGO_URL = 'https://www.sos-expat.com/logo.png';

    /**
     * Generate Article JSON-LD schema.
     */
    public function generateArticleSchema(GeneratedArticle $article): array
    {
        $imageUrl = $article->featured_image_url ?? null;
        if (!$imageUrl) {
            $featuredImage = $article->images()->orderBy('sort_order')->first();
            $imageUrl = $featuredImage?->url ?? null;
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $article->meta_title ?? $article->title,
            'description' => $article->meta_description ?? $article->excerpt ?? '',
            'datePublished' => $article->published_at
                ? $article->published_at->toIso8601String()
                : $article->created_at->toIso8601String(),
            'dateModified' => $article->updated_at->toIso8601String(),
            'author' => [
                '@type' => 'Organization',
                'name' => self::ORGANIZATION_NAME,
                'url' => self::ORGANIZATION_URL,
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => self::ORGANIZATION_NAME,
                'url' => self::ORGANIZATION_URL,
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => self::LOGO_URL,
                ],
            ],
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => $article->canonical_url ?? $article->published_url ?? $article->url ?? self::ORGANIZATION_URL,
            ],
            'wordCount' => $article->word_count ?? 0,
            'inLanguage' => $article->language ?? 'fr',
            'speakable' => [
                '@type' => 'SpeakableSpecification',
                'cssSelector' => $this->getSpeakableSelectors($article),
            ],
        ];

        // Only add image if it exists — an empty string violates Google's structured data rules
        if (!empty($imageUrl)) {
            $schema['image'] = $imageUrl;
        }

        // Add Table of Contents as hasPart for enhanced Article schema
        if ($article->content_html) {
            $toc = $this->extractTableOfContents($article->content_html);
            if (!empty($toc)) {
                $schema['hasPart'] = array_map(fn ($item) => [
                    '@type' => 'WebPageElement',
                    'isAccessibleForFree' => true,
                    'cssSelector' => '#' . $item['slug'],
                    'name' => $item['text'],
                    'position' => $item['position'],
                ], $toc);
            }
        }

        return $schema;
    }

    /**
     * Extract a Table of Contents structure from article HTML (H2/H3 tags).
     * Used for JSON-LD hasPart and sent to the blog frontend.
     */
    public function extractTableOfContents(string $html): array
    {
        $toc = [];
        preg_match_all('/<h([23])[^>]*>(.*?)<\/h[23]>/i', $html, $matches, PREG_SET_ORDER);

        $position = 0;
        foreach ($matches as $match) {
            $level = (int) $match[1];
            $text = strip_tags($match[2]);
            $slug = Str::slug($text);
            $position++;

            $toc[] = [
                'level' => $level,
                'text' => $text,
                'slug' => $slug,
                'position' => $position,
            ];
        }

        return $toc;
    }

    /**
     * Generate FAQPage JSON-LD schema.
     */
    public function generateFaqSchema(Collection $faqs): array
    {
        $mainEntity = [];

        foreach ($faqs as $faq) {
            $mainEntity[] = [
                '@type' => 'Question',
                'name' => $faq->question ?? ($faq['question'] ?? ''),
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $faq->answer ?? ($faq['answer'] ?? ''),
                ],
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $mainEntity,
        ];
    }

    /**
     * Generate BreadcrumbList JSON-LD schema.
     */
    public function generateBreadcrumbSchema(string $language, string $category, string $title): array
    {
        $homeNames = [
            'fr' => 'Accueil',
            'en' => 'Home',
            'de' => 'Startseite',
            'es' => 'Inicio',
            'it' => 'Home',
            'pt' => 'Início',
            'nl' => 'Home',
            'ar' => 'الرئيسية',
        ];

        $homeName = $homeNames[$language] ?? 'Home';

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => $homeName,
                    'item' => self::ORGANIZATION_URL . '/' . $language,
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => $category,
                    'item' => self::ORGANIZATION_URL . '/' . $language . '/blog/' . mb_strtolower($category),
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 3,
                    'name' => $title,
                ],
            ],
        ];
    }

    /**
     * Generate comparison/ItemList schema for comparatives.
     */
    public function generateComparativeSchema(Comparative $comparative): array
    {
        $entities = $comparative->entities ?? [];
        $comparisonData = $comparative->comparison_data ?? [];

        $itemListElement = [];
        $position = 1;

        foreach ($entities as $entity) {
            $entityData = $comparisonData[$entity] ?? [];

            $item = [
                '@type' => 'ListItem',
                'position' => $position,
                'name' => $entity,
            ];

            // Add description from pros if available
            if (!empty($entityData['pros'])) {
                $item['description'] = implode('. ', array_slice($entityData['pros'], 0, 3));
            }

            // Add rating with Review + AggregateRating for richer schema
            if (isset($entityData['rating']) && $entityData['rating'] > 0) {
                $item['item'] = [
                    '@type' => 'Product',
                    'name' => $entity,
                    'review' => [
                        '@type' => 'Review',
                        'reviewRating' => [
                            '@type' => 'Rating',
                            'ratingValue' => round($entityData['rating'], 1),
                            'bestRating' => 5,
                            'worstRating' => 1,
                        ],
                        'author' => [
                            '@type' => 'Organization',
                            'name' => config('services.site.name', 'SOS-Expat'),
                        ],
                        'reviewBody' => $entityData['description'] ?? "Analyse de {$entity} pour les expatriés.",
                        'datePublished' => now()->toIso8601String(),
                    ],
                    'aggregateRating' => [
                        '@type' => 'AggregateRating',
                        'ratingValue' => round($entityData['rating'], 1),
                        'bestRating' => 5,
                        'worstRating' => 1,
                        'ratingCount' => 10,
                        'reviewCount' => 5,
                    ],
                ];
            }

            $itemListElement[] = $item;
            $position++;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => $comparative->title,
            'description' => $comparative->meta_description ?? $comparative->excerpt ?? '',
            'numberOfItems' => count($entities),
            'itemListElement' => $itemListElement,
        ];
    }

    /**
     * Generate SpeakableSpecification schema for voice search.
     */
    public function generateSpeakableSchema(string $cssSelector = '.featured-snippet, h1, .qa-answer-short'): array
    {
        return [
            '@type' => 'SpeakableSpecification',
            'cssSelector' => explode(', ', $cssSelector),
        ];
    }

    /**
     * Generate HowTo schema from step data.
     */
    public function generateHowToSchema(string $name, array $steps, ?string $description = null, ?int $totalTime = null): array
    {
        $howTo = [
            '@context' => 'https://schema.org',
            '@type' => 'HowTo',
            'name' => $name,
            'step' => array_map(fn($step, $i) => array_filter([
                '@type' => 'HowToStep',
                'position' => $i + 1,
                'name' => $step['name'] ?? "Étape " . ($i + 1),
                'text' => $step['text'],
                'url' => $step['url'] ?? null,
            ]), $steps, array_keys($steps)),
        ];

        if ($description) {
            $howTo['description'] = $description;
        }
        if ($totalTime) {
            $howTo['totalTime'] = 'PT' . $totalTime . 'M';
        }

        return $howTo;
    }

    /**
     * Combine Article + FAQ + Breadcrumb + HowTo into @graph array.
     */
    public function generateFullSchema(GeneratedArticle $article): array
    {
        $graph = [];

        // Article schema (includes speakable)
        $articleSchema = $this->generateArticleSchema($article);
        unset($articleSchema['@context']); // Remove individual context for @graph
        $graph[] = $articleSchema;

        // FAQ schema (if article has FAQs)
        $faqs = $article->faqs()->get();
        if ($faqs->isNotEmpty()) {
            $faqSchema = $this->generateFaqSchema($faqs);
            unset($faqSchema['@context']);
            $graph[] = $faqSchema;
        }

        // Breadcrumb schema
        $category = $article->content_type ?? 'blog';
        $breadcrumbSchema = $this->generateBreadcrumbSchema(
            $article->language ?? 'fr',
            ucfirst($category),
            $article->title
        );
        unset($breadcrumbSchema['@context']);
        $graph[] = $breadcrumbSchema;

        // HowTo schema — auto-detect ordered lists in content
        if ($article->content_html) {
            preg_match_all('/<ol[^>]*>(.*?)<\/ol>/si', $article->content_html, $olMatches);
            if (!empty($olMatches[1])) {
                // Extract steps from the first <ol>
                preg_match_all('/<li[^>]*>(.*?)<\/li>/si', $olMatches[1][0], $liMatches);
                if (count($liMatches[1]) >= 3) {
                    $steps = array_map(fn($li) => ['text' => strip_tags($li)], $liMatches[1]);
                    $howToSchema = $this->generateHowToSchema($article->title, $steps);
                    unset($howToSchema['@context']);
                    $graph[] = $howToSchema;
                }
            }
        }

        // Place+Geo schema — for country/city guide articles
        if (!empty($article->country) && in_array($article->content_type ?? '', ['guide', 'pillar', 'guide_city'], true)) {
            try {
                $geo = app(\App\Services\Seo\GeoMetaService::class)->getByCode($article->country);
                if ($geo) {
                    $lang = $article->language ?? 'fr';
                    $placeName = $lang === 'en' ? $geo->country_name_en : $geo->country_name_fr;
                    $placeSchema = [
                        '@type'  => 'Place',
                        'name'   => $placeName,
                        'geo'    => [
                            '@type'     => 'GeoCoordinates',
                            'latitude'  => $geo->latitude,
                            'longitude' => $geo->longitude,
                        ],
                        'address' => [
                            '@type'           => 'PostalAddress',
                            'addressCountry'  => strtoupper($article->country),
                            'addressLocality' => $lang === 'en' ? ($geo->capital_en ?? '') : ($geo->capital_fr ?? ''),
                        ],
                    ];
                    unset($placeSchema['@context']);
                    $graph[] = $placeSchema;
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::debug('JsonLdService: Place schema failed', ['error' => $e->getMessage()]);
            }
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ];
    }

    /**
     * Validate JSON-LD structure.
     */
    public function validate(array $jsonLd): array
    {
        $errors = [];

        if (empty($jsonLd)) {
            return ['valid' => false, 'errors' => ['JSON-LD is empty']];
        }

        // Check for @context
        if (empty($jsonLd['@context'])) {
            $errors[] = 'Missing @context property';
        }

        // If @graph, validate each item
        $items = isset($jsonLd['@graph']) ? $jsonLd['@graph'] : [$jsonLd];

        foreach ($items as $index => $item) {
            $type = $item['@type'] ?? '';

            if (empty($type)) {
                $errors[] = "Item #{$index}: missing @type";
                continue;
            }

            switch ($type) {
                case 'Article':
                case 'BlogPosting':
                case 'NewsArticle':
                    $required = ['headline', 'datePublished', 'author'];
                    foreach ($required as $field) {
                        if (empty($item[$field])) {
                            $errors[] = "{$type}: missing required field '{$field}'";
                        }
                    }
                    break;

                case 'FAQPage':
                    if (empty($item['mainEntity']) || !is_array($item['mainEntity'])) {
                        $errors[] = 'FAQPage: missing or invalid mainEntity array';
                    } else {
                        foreach ($item['mainEntity'] as $i => $faqItem) {
                            if (($faqItem['@type'] ?? '') !== 'Question') {
                                $errors[] = "FAQPage: mainEntity[{$i}] should be @type Question";
                            }
                            if (empty($faqItem['acceptedAnswer'])) {
                                $errors[] = "FAQPage: mainEntity[{$i}] missing acceptedAnswer";
                            }
                        }
                    }
                    break;

                case 'BreadcrumbList':
                    if (empty($item['itemListElement']) || !is_array($item['itemListElement'])) {
                        $errors[] = 'BreadcrumbList: missing or invalid itemListElement';
                    }
                    break;

                case 'ItemList':
                    if (empty($item['itemListElement']) || !is_array($item['itemListElement'])) {
                        $errors[] = 'ItemList: missing or invalid itemListElement';
                    }
                    break;
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Speakable selectors refined by search intent (SGE / voice search optimization).
     */
    private function getSpeakableSelectors($article): array
    {
        $selectors = ['.featured-snippet', 'h1'];

        $intent = $article->search_intent ?? null;
        $contentType = $article->content_type ?? 'article';

        // Refine by intent
        if ($intent === 'urgency') {
            $selectors[] = '.emergency-box';
        }
        if ($intent === 'commercial_investigation' || $contentType === 'comparative') {
            $selectors[] = '.cta-box';
        }
        if ($intent === 'transactional') {
            $selectors[] = '.pricing-box';
        }

        // Refine by content type
        if ($contentType === 'statistics') {
            $selectors[] = '.key-figures';
        }
        if ($contentType === 'qa' || $contentType === 'qa_needs') {
            $selectors[] = '.qa-answer';
        }

        return array_unique($selectors);
    }
}
