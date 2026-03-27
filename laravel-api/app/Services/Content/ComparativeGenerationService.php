<?php

namespace App\Services\Content;

use App\Models\Comparative;
use App\Models\ContentExternalLink;
use App\Models\ExternalLinkRegistry;
use App\Models\GeneratedArticle;
use App\Models\GenerationLog;
use App\Services\AI\OpenAiService;
use App\Services\AI\UnsplashService;
use App\Services\Content\ContentTypeConfig;
use App\Services\PerplexitySearchService;
use App\Services\Seo\InternalLinkingService;
use App\Services\Seo\JsonLdService;
use App\Services\Seo\SeoAnalysisService;
use App\Services\Seo\SlugService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Comparative content generation — structured comparisons between entities.
 */
class ComparativeGenerationService
{
    public function __construct(
        private OpenAiService $openAi,
        private PerplexitySearchService $perplexity,
        private SeoAnalysisService $seoAnalysis,
        private JsonLdService $jsonLd,
        private SlugService $slugService,
        private InternalLinkingService $internalLinking,
        private UnsplashService $unsplash,
    ) {}

    /**
     * Generate a comparative analysis article.
     */
    public function generate(array $params): Comparative
    {
        $startTime = microtime(true);

        $typeConfig = ContentTypeConfig::get('comparative');

        $entities = $params['entities'] ?? [];
        $language = $params['language'] ?? 'fr';
        $country = $params['country'] ?? null;
        $keywords = $params['keywords'] ?? [];

        $comparative = Comparative::create([
            'uuid' => (string) Str::uuid(),
            'title' => $params['title'] ?? implode(' vs ', $entities),
            'language' => $language,
            'country' => $country,
            'entities' => $entities,
            'status' => 'generating',
            'created_by' => $params['created_by'] ?? null,
        ]);

        Log::info('Comparative generation started', [
            'comparative_id' => $comparative->id,
            'entities' => $entities,
        ]);

        try {
            // Phase 1: Research each entity (use config model for research)
            $researchData = [];
            foreach ($entities as $entity) {
                $research = $this->researchEntity($entity, $language, $country);
                $researchData[$entity] = $research;
            }

            $this->logPhase($comparative, 'research', 'success', count($entities) . ' entities researched');

            // Extract LSI keywords from combined research
            $lsiKeywords = [];
            $combinedResearch = implode("\n", array_map(fn ($d) => $d['text'] ?? '', $researchData));
            if (!empty($combinedResearch)) {
                try {
                    $lsiResult = $this->openAi->complete(
                        "Extrais 10-15 mots-clés sémantiques (LSI) de ce texte de recherche comparative. Ce sont des termes que Google s'attend à trouver dans un comparatif complet sur ce sujet. Retourne en JSON: {\"lsi_keywords\": [\"mot1\", \"mot2\", ...]}",
                        mb_substr($combinedResearch, 0, 3000),
                        ['temperature' => 0.3, 'max_tokens' => 300, 'json_mode' => true]
                    );
                    if ($lsiResult['success']) {
                        $lsiData = json_decode($lsiResult['content'], true);
                        $lsiKeywords = $lsiData['lsi_keywords'] ?? [];
                    }
                } catch (\Throwable $e) {
                    Log::warning('ComparativeGeneration: LSI extraction failed (non-blocking)', ['error' => $e->getMessage()]);
                }
            }

            // Store LSI keywords on comparative
            if (!empty($lsiKeywords)) {
                $comparative->update(['keywords_secondary' => $lsiKeywords]);
            }

            // Phase 2: Generate comparison data (structured)
            $comparisonTable = $this->generateComparisonTable($entities, $researchData);
            $this->logPhase($comparative, 'comparison_table', 'success');

            // Phase 3: Generate pros/cons for each entity
            $comparisonData = [];
            foreach ($entities as $entity) {
                $context = $researchData[$entity]['text'] ?? '';
                $prosConsResult = $this->generateProsConsForEntity($entity, $context);
                $comparisonData[$entity] = $prosConsResult;
            }

            $comparative->update(['comparison_data' => $comparisonData]);
            $this->logPhase($comparative, 'pros_cons', 'success');

            // Phase 4: Generate full content HTML
            $contentHtml = $this->generateContentHtml($comparative, $comparisonTable, $comparisonData, $language);
            $comparative->update(['content_html' => $contentHtml]);
            $this->logPhase($comparative, 'content', 'success');

            // Phase 4b: Featured snippet paragraph
            try {
                $entityNames = is_array($entities) ? $entities : [];
                $snippetResult = $this->openAi->complete(
                    "Génère un paragraphe de définition de 40-60 mots comparant " . implode(' et ', $entityNames) . ". Format featured snippet Google. Commence par '" . ($entityNames[0] ?? '') . " et " . ($entityNames[1] ?? '') . " sont...' ou 'La comparaison entre...'",
                    "Entités: " . implode(', ', $entityNames) . "\nPays: " . ($country ?? ''),
                    ['temperature' => 0.5, 'max_tokens' => 200]
                );
                if ($snippetResult['success'] && !empty($snippetResult['content'])) {
                    $snippet = '<p class="featured-snippet"><strong>' . e(trim($snippetResult['content'])) . '</strong></p>';
                    $html = $comparative->content_html;
                    $pos = strpos($html, '</h2>');
                    if ($pos !== false) {
                        $html = substr($html, 0, $pos + 5) . "\n" . $snippet . "\n" . substr($html, $pos + 5);
                        $comparative->update(['content_html' => $html]);
                    }
                }
                $this->logPhase($comparative, 'featured_snippet', 'success');
            } catch (\Throwable $e) {
                Log::warning('ComparativeGeneration: featured snippet failed (non-blocking)', [
                    'comparative_id' => $comparative->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Phase 5: Generate meta tags
            $meta = $this->generateMeta($comparative->title, $entities, $language);
            $comparative->update([
                'meta_title' => $meta['meta_title'],
                'meta_description' => $meta['meta_description'],
                'excerpt' => $meta['excerpt'],
            ]);
            $this->logPhase($comparative, 'meta', 'success');

            // Phase 6: Generate JSON-LD with BreadcrumbList
            $compSchema = $this->jsonLd->generateComparativeSchema($comparative->fresh());
            $breadcrumb = $this->jsonLd->generateBreadcrumbSchema(
                $language,
                'comparatifs',
                $comparative->title
            );
            $jsonLdData = [
                '@context' => 'https://schema.org',
                '@graph' => [
                    $compSchema,
                    $breadcrumb,
                ],
            ];
            $comparative->update(['json_ld' => $jsonLdData]);
            $this->logPhase($comparative, 'json_ld', 'success');

            // Phase 7: Generate slug and calculate quality
            $slug = $this->slugService->generateSlug($comparative->title, $language);
            $slug = $this->slugService->ensureUnique($slug, $language, 'comparatives', $comparative->id);
            $canonical = rtrim(config('services.blog.site_url', 'https://sos-expat.com'), '/') . '/' . ($comparative->language ?? 'fr') . '/comparatifs/' . $slug;
            $comparative->update(['slug' => $slug, 'canonical_url' => $canonical]);

            $seoResult = $this->seoAnalysis->analyze($comparative->fresh());
            $comparative->update([
                'quality_score' => $seoResult->overall_score,
                'status' => 'review',
            ]);

            // Plagiarism check against existing comparatives
            try {
                $comparativeText = strip_tags($comparative->content_html ?? '');
                if (mb_strlen($comparativeText) > 200) {
                    $existingComparatives = Comparative::where('language', $comparative->language)
                        ->where('id', '!=', $comparative->id)
                        ->whereIn('status', ['draft', 'review', 'published'])
                        ->select('id', 'title', 'content_html')
                        ->limit(50)
                        ->get();

                    foreach ($existingComparatives as $existing) {
                        similar_text(
                            strip_tags($comparative->content_html ?? ''),
                            strip_tags($existing->content_html ?? ''),
                            $percent
                        );
                        if ($percent > 35) {
                            Log::warning('ComparativeGenerationService: similar comparative detected', [
                                'new' => $comparative->id,
                                'existing' => $existing->id,
                                'similarity' => $percent,
                            ]);
                            $comparative->update(['status' => 'review']);
                            break;
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('ComparativeGeneration: plagiarism check failed (non-blocking)', [
                    'comparative_id' => $comparative->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Phase 8: Enrichment — FAQ, images, internal links, external links, affiliate links
            try {
                $this->enrichComparative($comparative->fresh(), $typeConfig, $params);
            } catch (\Throwable $e) {
                Log::warning('ComparativeGeneration: enrichment failed (non-blocking)', [
                    'comparative_id' => $comparative->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $totalDuration = (int) ((microtime(true) - $startTime) * 1000);
            Log::info('Comparative generation complete', [
                'comparative_id' => $comparative->id,
                'duration_ms' => $totalDuration,
            ]);

            return $comparative->fresh();
        } catch (\Throwable $e) {
            Log::error('Comparative generation failed', [
                'comparative_id' => $comparative->id,
                'message' => $e->getMessage(),
            ]);

            $comparative->update(['status' => 'draft']);
            $this->logPhase($comparative, 'pipeline', 'error', $e->getMessage());

            return $comparative->fresh();
        }
    }

    /**
     * Research a single entity via Perplexity.
     */
    private function researchEntity(string $entity, string $language, ?string $country): array
    {
        if (!$this->perplexity->isConfigured()) {
            return ['text' => '', 'citations' => []];
        }

        $countryContext = $country ? " en {$country}" : '';
        $query = "Recherche des informations factuelles sur \"{$entity}\"{$countryContext}: "
            . "avantages, inconvénients, tarifs, fonctionnalités principales, avis utilisateurs.";

        $result = $this->perplexity->search($query, $language);

        return [
            'text' => $result['text'] ?? '',
            'citations' => $result['citations'] ?? [],
        ];
    }

    /**
     * Generate a structured comparison table.
     *
     * @return array<array{criteria: string, values: array<string, string>}>
     */
    private function generateComparisonTable(array $entities, array $researchData): array
    {
        $entitiesStr = implode(', ', $entities);
        $researchContext = '';
        foreach ($researchData as $entity => $data) {
            $text = $data['text'] ?? '';
            if (!empty($text)) {
                $researchContext .= "\n\n{$entity}:\n" . mb_substr($text, 0, 1000);
            }
        }

        $systemPrompt = "Tu es un analyste comparatif. Compare les entités suivantes selon des critères pertinents. "
            . "Retourne en JSON un tableau de comparaison:\n"
            . "[{\"criteria\": \"Nom du critère\", \"values\": {\"Entity1\": \"valeur\", \"Entity2\": \"valeur\"}}]\n\n"
            . "Inclus 8-12 critères pertinents (prix, fonctionnalités, facilité d'utilisation, support, etc.).";

        $typeConfig = ContentTypeConfig::get('comparative');
        $result = $this->openAi->complete($systemPrompt,
            "Entités à comparer: {$entitiesStr}\n\nDonnées de recherche:{$researchContext}", [
                'model' => $typeConfig['model'],
                'temperature' => $typeConfig['temperature'],
                'max_tokens' => 2000,
                'json_mode' => true,
            ]);

        if ($result['success']) {
            $parsed = json_decode($result['content'], true);
            // Handle various JSON structures
            return $parsed['comparison'] ?? $parsed['table'] ?? $parsed['criteria'] ?? (is_array($parsed) && isset($parsed[0]) ? $parsed : []);
        }

        return [];
    }

    /**
     * Generate pros, cons, and rating for a single entity.
     *
     * @return array{pros: array, cons: array, rating: float}
     */
    private function generateProsConsForEntity(string $entity, string $context): array
    {
        $contextExcerpt = mb_substr($context, 0, 1500);

        $systemPrompt = "Analyse l'entité suivante et retourne en JSON:\n"
            . "{\"pros\": [\"avantage 1\", ...], \"cons\": [\"inconvénient 1\", ...], \"rating\": 4.2}\n\n"
            . "3-6 avantages, 2-4 inconvénients, note sur 5. Sois objectif et factuel.";

        $typeConfig = ContentTypeConfig::get('comparative');
        $result = $this->openAi->complete($systemPrompt,
            "Entité: {$entity}\n\nContexte:\n{$contextExcerpt}", [
                'model' => $typeConfig['model'],
                'temperature' => $typeConfig['temperature'],
                'max_tokens' => 800,
                'json_mode' => true,
            ]);

        if ($result['success']) {
            $parsed = json_decode($result['content'], true);
            return [
                'pros' => $parsed['pros'] ?? [],
                'cons' => $parsed['cons'] ?? [],
                'rating' => (float) ($parsed['rating'] ?? 3.0),
            ];
        }

        return ['pros' => [], 'cons' => [], 'rating' => 3.0];
    }

    /**
     * Generate the full HTML content including comparison table.
     */
    private function generateContentHtml(Comparative $comparative, array $comparisonTable, array $comparisonData, string $language): string
    {
        $entities = $comparative->entities ?? [];
        $title = $comparative->title;

        // Build comparison table HTML
        $tableHtml = $this->buildComparisonTableHtml($comparisonTable, $entities);

        // Build pros/cons sections
        $prosConsHtml = '';
        foreach ($entities as $entity) {
            $data = $comparisonData[$entity] ?? [];
            $prosConsHtml .= $this->buildProsConsHtml($entity, $data);
        }

        $systemPrompt = "Tu es un rédacteur web expert en comparatifs. Rédige un article comparatif complet en HTML. "
            . "Langue: {$language}.\n\n"
            . "STRUCTURE ATTENDUE:\n"
            . "- Introduction (2-3 paragraphes)\n"
            . "- Le tableau de comparaison est déjà fourni (intègre-le tel quel)\n"
            . "- Les sections pros/cons sont fournies (intègre-les)\n"
            . "- Analyse détaillée de chaque entité (1-2 paragraphes chacune)\n"
            . "- Verdict final avec recommandation\n\n"
            . "Utilise des balises HTML: <h2>, <h3>, <p>, <strong>, <em>. Pas de <h1>.\n\n"
            . "Les entités comparées doivent apparaître dans les H2. Exemple : \"Avantages de {entity1}\" au lieu de \"Avantages de la première option\".\n"
            . "Intègre naturellement les noms des entités comparées avec une densité de 1-2% chacun dans le texte.";

        // Access LSI keywords from comparative (stored during research phase)
        $lsiKeywords = $comparative->keywords_secondary ?? [];
        $lsiBlock = '';
        if (!empty($lsiKeywords)) {
            $lsiList = implode(', ', array_slice($lsiKeywords, 0, 15));
            $lsiBlock = "\n\nMOTS-CLÉS SÉMANTIQUES (LSI) à intégrer naturellement dans le texte :\n{$lsiList}\nCes mots doivent apparaître au moins 1 fois chacun dans l'article pour signaler à Google que l'article couvre le sujet en profondeur.";
        }

        $userPrompt = "Titre: {$title}\nEntités: " . implode(', ', $entities)
            . "\n\nTableau de comparaison à intégrer:\n{$tableHtml}"
            . "\n\nSections pros/cons à intégrer:\n{$prosConsHtml}"
            . $lsiBlock
            . "\n\nRédige l'article complet.";

        $typeConfig = ContentTypeConfig::get('comparative');
        $result = $this->openAi->complete($systemPrompt, $userPrompt, [
            'model' => $typeConfig['model'],
            'temperature' => $typeConfig['temperature'],
            'max_tokens' => $typeConfig['max_tokens_content'] ?? 5000,
            'costable_type' => Comparative::class,
            'costable_id' => $comparative->id,
        ]);

        if ($result['success']) {
            return trim($result['content']);
        }

        // Fallback: return table + pros/cons
        return "<h2>Comparaison</h2>\n{$tableHtml}\n{$prosConsHtml}";
    }

    /**
     * Build HTML comparison table from structured data.
     */
    private function buildComparisonTableHtml(array $comparisonTable, array $entities): string
    {
        if (empty($comparisonTable)) {
            return '';
        }

        $html = "<table>\n<thead>\n<tr>\n<th>Critère</th>\n";
        foreach ($entities as $entity) {
            $html .= '<th>' . htmlspecialchars($entity) . "</th>\n";
        }
        $html .= "</tr>\n</thead>\n<tbody>\n";

        foreach ($comparisonTable as $row) {
            $criteria = $row['criteria'] ?? '';
            $values = $row['values'] ?? [];

            $html .= "<tr>\n<td><strong>" . htmlspecialchars($criteria) . "</strong></td>\n";
            foreach ($entities as $entity) {
                $value = $values[$entity] ?? '-';
                $html .= '<td>' . htmlspecialchars($value) . "</td>\n";
            }
            $html .= "</tr>\n";
        }

        $html .= "</tbody>\n</table>";

        return $html;
    }

    /**
     * Build HTML pros/cons section for an entity.
     */
    private function buildProsConsHtml(string $entity, array $data): string
    {
        $pros = $data['pros'] ?? [];
        $cons = $data['cons'] ?? [];
        $rating = $data['rating'] ?? 0;

        $html = '<h3>' . htmlspecialchars($entity) . " ({$rating}/5)</h3>\n";

        if (!empty($pros)) {
            $html .= "<p><strong>Avantages:</strong></p>\n<ul>\n";
            foreach ($pros as $pro) {
                $html .= '<li>' . htmlspecialchars($pro) . "</li>\n";
            }
            $html .= "</ul>\n";
        }

        if (!empty($cons)) {
            $html .= "<p><strong>Inconvénients:</strong></p>\n<ul>\n";
            foreach ($cons as $con) {
                $html .= '<li>' . htmlspecialchars($con) . "</li>\n";
            }
            $html .= "</ul>\n";
        }

        return $html;
    }

    /**
     * Generate meta tags for the comparative.
     */
    private function generateMeta(string $title, array $entities, string $language): array
    {
        $entitiesStr = implode(' vs ', $entities);
        $year = date('Y');

        $systemPrompt = "Génère des métadonnées SEO pour un comparatif. Langue: {$language}.\n"
            . "Retourne en JSON: {\"meta_title\": \"...\", \"meta_description\": \"...\", \"excerpt\": \"...\"}\n\n"
            . "RÈGLES META TITLE :\n"
            . "- EXACTEMENT 50-60 caractères\n"
            . "- Les noms des entités comparées DOIVENT apparaître\n"
            . "- Inclure l'année {$year}\n"
            . "- Format: \"{Entity1} vs {Entity2} : Comparatif {$year}\"\n"
            . "- Power word: Comparatif, Guide, Analyse\n\n"
            . "RÈGLES META DESCRIPTION :\n"
            . "- 140-155 caractères\n"
            . "- Commencer par un verbe d'action : \"Comparez\", \"Découvrez\", \"Analysez\"\n"
            . "- Mentionner les entités comparées\n"
            . "- Finir par un CTA : \"guide complet\", \"analyse détaillée\"\n\n"
            . "excerpt: 2-3 phrases.";

        $result = $this->openAi->complete($systemPrompt,
            "Titre: {$title}\nEntités: {$entitiesStr}", [
                'temperature' => 0.5,
                'max_tokens' => 400,
                'json_mode' => true,
            ]);

        if ($result['success']) {
            $parsed = json_decode($result['content'], true);
            return [
                'meta_title' => mb_substr($parsed['meta_title'] ?? $title, 0, 60),
                'meta_description' => mb_substr($parsed['meta_description'] ?? '', 0, 160),
                'excerpt' => $parsed['excerpt'] ?? '',
            ];
        }

        return [
            'meta_title' => mb_substr($title, 0, 60),
            'meta_description' => mb_substr("Comparaison détaillée: {$entitiesStr}", 0, 160),
            'excerpt' => "Découvrez notre comparatif détaillé: {$entitiesStr}.",
        ];
    }

    /**
     * Enrich comparative with FAQ, images, internal links, external links, affiliate links.
     */
    private function enrichComparative(Comparative $comparative, array $typeConfig, array $params): void
    {
        $entities = $comparative->entities ?? [];
        $entitiesStr = implode(', ', is_array($entities) ? $entities : []);

        // FAQ generation
        $faqCount = $typeConfig['faq_count'] ?? 6;
        if ($faqCount > 0) {
            $faqResult = $this->openAi->complete(
                "Genere {$faqCount} questions frequentes sur la comparaison entre ces entites pour les expatries. Retourne en JSON: {\"faqs\": [{\"question\":\"...\",\"answer\":\"...\"}]}",
                "Entites comparees: {$entitiesStr}\nPays: " . ($comparative->country ?? ''),
                ['temperature' => 0.6, 'max_tokens' => 2000, 'json_mode' => true]
            );
            if ($faqResult['success']) {
                $faqData = json_decode($faqResult['content'], true);
                $faqs = $faqData['faqs'] ?? $faqData ?? [];
                if (!empty($faqs) && isset($faqs[0]['question'])) {
                    $faqHtml = "\n<h2>Questions frequentes</h2>\n";
                    foreach ($faqs as $faq) {
                        $faqHtml .= '<h3>' . e($faq['question'] ?? '') . "</h3>\n<p>" . e($faq['answer'] ?? '') . "</p>\n";
                    }
                    $comparative->update(['content_html' => ($comparative->content_html ?? '') . $faqHtml]);
                }
            }
            $this->logPhase($comparative, 'faq', 'success', $faqCount . ' FAQs requested');
        }

        // Images (Unsplash)
        $imagesCount = $typeConfig['images_count'] ?? 1;
        if ($this->unsplash->isConfigured() && $imagesCount > 0) {
            $query = $comparative->title ?? $entitiesStr;
            $imgResult = $this->unsplash->search($query, $imagesCount);
            if ($imgResult['success'] && !empty($imgResult['images'])) {
                $img = $imgResult['images'][0];
                $altText = mb_substr($comparative->title . ' - ' . ($img['alt_text'] ?? ''), 0, 125);
                $imgTag = '<figure><img src="' . e($img['url']) . '" alt="' . e($altText) . '" loading="lazy" />';
                if (!empty($img['attribution'])) {
                    $imgTag .= '<figcaption>' . e($img['attribution']) . '</figcaption>';
                }
                $imgTag .= '</figure>';

                $html = $comparative->content_html ?? '';
                $pos = strpos($html, '</h2>');
                if ($pos !== false) {
                    $html = substr($html, 0, $pos + 5) . "\n" . $imgTag . "\n" . substr($html, $pos + 5);
                } else {
                    $html = $imgTag . "\n" . $html;
                }
                $comparative->update(['content_html' => $html]);
            }
            $this->logPhase($comparative, 'images', 'success');
        }

        // Internal links — find related published articles
        $internalLinksCount = $typeConfig['internal_links'] ?? 5;
        $relatedArticles = GeneratedArticle::where('language', $comparative->language ?? 'fr')
            ->where('status', 'published')
            ->whereNull('parent_article_id')
            ->when($comparative->country, fn ($q, $c) => $q->where('country', $c))
            ->limit($internalLinksCount)
            ->get();
        if ($relatedArticles->isNotEmpty()) {
            $linksHtml = "\n<h2>Articles connexes</h2>\n<ul>\n";
            foreach ($relatedArticles as $ra) {
                $linksHtml .= '<li><a href="/' . $ra->language . '/articles/' . $ra->slug . '">' . e($ra->title) . '</a></li>' . "\n";
            }
            $linksHtml .= "</ul>\n";
            $comparative->update(['content_html' => ($comparative->content_html ?? '') . $linksHtml]);
            $this->logPhase($comparative, 'internal_links', 'success', $relatedArticles->count() . ' links');
        }

        // External links from scraped sources
        $externalLinksCount = $typeConfig['external_links'] ?? 4;
        $extLinks = ContentExternalLink::where('country_id', function ($q) use ($comparative) {
                $q->select('id')->from('content_countries')->where('slug', Str::slug($comparative->country ?? ''))->limit(1);
            })
            ->whereIn('link_type', ['official', 'news', 'resource'])
            ->where('is_affiliate', false)
            ->orderByDesc('occurrences')
            ->limit($externalLinksCount)
            ->get();
        if ($extLinks->isNotEmpty()) {
            $html = $comparative->content_html ?? '';
            $extSection = "\n<h2>Sources et liens utiles</h2>\n<ul>\n";
            foreach ($extLinks as $link) {
                $anchor = $link->anchor_text ?: $link->domain;
                $extSection .= '<li><a href="' . e($link->url) . '" target="_blank" rel="noopener">' . e($anchor) . '</a></li>' . "\n";
            }
            $extSection .= "</ul>\n";
            $html .= $extSection;
            $comparative->update(['content_html' => $html]);
            $this->logPhase($comparative, 'external_links', 'success', $extLinks->count() . ' links');
        }

        // Affiliate links
        $siteUrl = config('services.site.url', 'https://sos-expat.com');
        $cta = '<p><strong>Besoin d\'aide pour votre expatriation ?</strong> <a href="' . $siteUrl . '?utm_source=blog&utm_medium=comparative&utm_campaign=' . ($comparative->slug ?? 'comparative') . '">Consultez nos experts SOS-Expat</a></p>';
        $comparative->update(['content_html' => ($comparative->content_html ?? '') . "\n" . $cta]);
    }

    private function logPhase(Comparative $comparative, string $phase, string $status, ?string $message = null): void
    {
        try {
            GenerationLog::create([
                'loggable_type' => Comparative::class,
                'loggable_id' => $comparative->id,
                'phase' => $phase,
                'status' => $status,
                'message' => $message,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to log comparative phase', ['phase' => $phase, 'message' => $e->getMessage()]);
        }
    }
}
