<?php

namespace App\Services\News;

use App\Models\RssFeedItem;
use App\Services\Content\GenerationGuardService;
use App\Services\Content\GenerationSchedulerService;
use App\Services\Content\KnowledgeBaseService;
use App\Services\News\SimilarityCheckerService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NewsGenerationService
{
    private const MODEL_GENERATE = 'claude-sonnet-4-6';
    private const MODEL_LIGHT    = 'claude-haiku-4-5-20251001';

    public function __construct(
        private KnowledgeBaseService $knowledgeBase,
        private GenerationGuardService $guard,
        private GenerationSchedulerService $scheduler,
    ) {}

    /**
     * Generate a blog article from an RSS feed item.
     * Returns true on success, false on failure.
     */
    public function generate(RssFeedItem $item): bool
    {
        $anthropicKey = config('services.anthropic.api_key') ?: config('services.claude.api_key');

        if (! $anthropicKey) {
            $item->update(['status' => 'failed', 'error_message' => 'ANTHROPIC_API_KEY manquant']);
            return false;
        }

        // ── Rate limiting ──
        $scheduleCheck = $this->scheduler->canGenerate('news');
        if (!$scheduleCheck['allowed']) {
            $item->update(['status' => 'skipped', 'error_message' => 'Rate limit: ' . $scheduleCheck['reason']]);
            return false;
        }

        // ── Generation Guard: dedup check ──
        $guardResult = $this->guard->check(
            $item->title,
            'news',
            $item->language ?? 'fr',
            $item->country,
        );
        if ($guardResult['status'] === 'block') {
            $item->update(['status' => 'skipped', 'error_message' => 'Duplicate: ' . $guardResult['reason']]);
            Log::info("NewsGenerationService: blocked by guard", ['item_id' => $item->id, 'reason' => $guardResult['reason']]);
            return false;
        }

        // ── PASSE 1 : extraction des faits (Haiku) ──
        $facts = $this->extractFacts($item, $anthropicKey);

        if (! $facts) {
            $item->update(['status' => 'failed', 'error_message' => 'Extraction des faits échouée']);
            return false;
        }

        // ── PASSE 2 : réécriture (Sonnet) ──
        $content = $this->rewriteContent($item, $facts, $anthropicKey);

        if (! $content) {
            $item->update(['status' => 'failed', 'error_message' => 'Génération du contenu échouée']);
            return false;
        }

        // ── Anti-plagiat ──
        $similarityChecker = new SimilarityCheckerService();
        $originalText      = $item->original_content ?: $item->original_excerpt ?: $item->title;
        $generatedText     = ($content['content_html'] ?? '') . ' ' . ($content['excerpt'] ?? '');

        $similarityScore = $similarityChecker->compute($originalText, $generatedText);

        if ($similarityScore > 30) {
            // Retry avec instruction anti-plagiat
            Log::info("NewsGenerationService: similarité {$similarityScore}% → retry item #{$item->id}");
            $content = $this->rewriteContent($item, $facts, $anthropicKey, retry: true);

            if (! $content) {
                $item->update(['status' => 'failed', 'error_message' => 'Génération retry échouée']);
                return false;
            }

            $generatedText   = ($content['content_html'] ?? '') . ' ' . ($content['excerpt'] ?? '');
            $similarityScore = $similarityChecker->compute($originalText, $generatedText);

            if ($similarityScore > 30) {
                $item->update([
                    'status'           => 'failed',
                    'similarity_score' => $similarityScore,
                    'error_message'    => 'similarity_score trop élevé après retry',
                ]);
                return false;
            }
        }

        // ── Post-process: quality check, SEO score ──
        $postProcessor = app(\App\Services\Content\ContentPostProcessor::class);
        $content = $postProcessor->process($content, 'news', $item->language ?? 'fr', $item->country, "news_rss_{$item->id}");

        // ── Envoi au Blog ──
        $sent = $this->sendToBlog($item, $content);

        if (! $sent) {
            $item->update(['status' => 'failed', 'error_message' => 'Envoi au Blog échoué']);
            return false;
        }

        // ── Marquer publié ──
        $uuid = "mc_rss_{$item->id}";
        $item->update([
            'status'            => 'published',
            'similarity_score'  => $similarityScore,
            'blog_article_uuid' => $uuid,
            'generated_at'      => now(),
            'error_message'     => null,
        ]);

        // Record generation for rate limiting
        $this->scheduler->recordGeneration('news');

        return true;
    }

    // ─────────────────────────────────────────
    // PASSE 1 — EXTRACTION FAITS
    // ─────────────────────────────────────────

    private function extractFacts(RssFeedItem $item, string $key): ?array
    {
        $source  = mb_substr(strip_tags($item->original_content ?: $item->original_excerpt ?: ''), 0, 1500);
        $title   = $item->title;

        $prompt = <<<PROMPT
Extrais UNIQUEMENT les faits factuels de cet article: dates, chiffres, noms de pays/villes, noms d'institutions, données statistiques, changements réglementaires.
NE RECOPIE AUCUNE phrase de l'article source.
Titre: {$title}
Contenu: {$source}
Réponds en JSON: {"facts": ["fait1", "fait2", ...], "country": "XX ou null", "angle": "angle editorial suggéré"}
PROMPT;

        $kbLight = $this->knowledgeBase->getLightPrompt('news', $item->country, $item->language ?? 'fr');
        $result = $this->callClaude(self::MODEL_LIGHT, $prompt, 500, $key, $kbLight);
        if (! $result) return null;

        return $this->extractJson($result);
    }

    // ─────────────────────────────────────────
    // PASSE 2 — RÉÉCRITURE
    // ─────────────────────────────────────────

    private function rewriteContent(RssFeedItem $item, array $facts, string $key, bool $retry = false): ?array
    {
        $factsJson   = json_encode($facts['facts'] ?? [], JSON_UNESCAPED_UNICODE);
        $country     = $facts['country'] ?? $item->country ?? 'null';
        $category    = $item->relevance_category ?? $item->feed?->category ?? 'autre';
        $lang        = $item->language ?? 'fr';
        $faqSegments = ['fr'=>'vie-a-letranger','en'=>'living-abroad','es'=>'vivir-en-el-extranjero','de'=>'leben-im-ausland','pt'=>'viver-no-estrangeiro','ru'=>'zhizn-za-rubezhom','zh'=>'haiwai-shenghuo','hi'=>'videsh-mein-jeevan','ar'=>'alhayat-fi-alkhaarij'];
        $artSegments = ['fr'=>'articles','en'=>'articles','es'=>'articulos','de'=>'artikel','pt'=>'artigos','ru'=>'stati','zh'=>'wenzhang','hi'=>'lekh','ar'=>'maqalat'];
        $defCountry  = ['fr'=>'fr','en'=>'us','es'=>'es','de'=>'de','pt'=>'pt','ru'=>'ru','zh'=>'cn','hi'=>'in','ar'=>'sa'];
        $faqUrl  = "https://sos-expat.com/{$lang}-" . ($defCountry[$lang]??'fr') . "/" . ($faqSegments[$lang]??'living-abroad');
        $artUrl  = "https://sos-expat.com/{$lang}-" . ($defCountry[$lang]??'fr') . "/" . ($artSegments[$lang]??'articles');

        $retryNote = $retry
            ? "\nATTENTION: le précédent résultat était trop similaire à la source. Reformule entièrement avec d'autres formulations."
            : '';

        $prompt = <<<PROMPT
Tu es rédacteur expert pour SOS-Expat.com, service d'assistance aux expatriés dans 197 pays.
Public cible: expatriés, voyageurs longue durée, vacanciers, touristes, nomades numériques — de TOUTES nationalités (pas uniquement francophones).
Adapte l'angle au sujet: visa/admin → expatrié, destination/culture → vacancier/voyageur, crise/sécurité → voyageurs et résidents.
IMPORTANT: Ne JAMAIS écrire uniquement pour les Français. S'adresser à TOUTE personne étrangère dans le pays concerné. Dire "votre ambassade" et non "l'ambassade de France".

MISSION: Rédiger un article ENTIÈREMENT ORIGINAL à partir de ces faits.
INTERDICTIONS ABSOLUES:
- Ne recopier AUCUNE phrase de la source originale
- Ne paraphraser directement aucun passage
- Pas de contenu générique qui pourrait être copié-collé d'ailleurs
OBLIGATIONS:
- Minimum 600 mots, HTML avec <h2>, <h3>, <ul>, <strong>, <p> (pas de <h1>)
- Structure: inverted pyramid — réponse directe d'abord, contexte ensuite, détails en dernier
- Premier paragraphe: réponse directe et utile pour le lecteur cible (expatrié/voyageur/vacancier)
- Angle pratique: "Que signifie cette actualité concrètement pour vous ?"
- 4 à 6 sous-sections thématiques pertinentes (H2), avec sous-titres H3 si nécessaire
- 1 à 2 liens contextuels internes quand c'est naturellement pertinent: <a href="{$faqUrl}">...</a> ou <a href="{$artUrl}">...</a>
- 5 FAQ en fin d'article (sous-questions pratiques + réponses 80-150 mots chacune)
- Ton: informatif, pratique, accessible (pas juridique/jargon)

Faits à utiliser comme base: {$factsJson}
Pays concerné: {$country}
Catégorie: {$category}{$retryNote}

Réponds UNIQUEMENT en JSON valide (sans markdown):
{
  "title": "titre accrocheur 65-90 chars, angle expatrié / voyageur / vacancier selon le sujet",
  "meta_title": "max 60 chars, mot-clé principal au début",
  "meta_description": "140-155 chars, incitative",
  "excerpt": "2-3 phrases d'accroche max 180 chars",
  "keywords_primary": "mot-clé principal",
  "keywords_secondary": ["longue traîne 1", "longue traîne 2", "longue traîne 3"],
  "ai_summary": "1 phrase max 100 chars pour IA/AEO",
  "content_html": "<p>...</p><h2>...</h2>...",
  "faqs": [{"question": "...", "answer": "<p>...</p>"}]
}
PROMPT;

        $kbFull = $this->knowledgeBase->getSystemPrompt('news', $item->country, $lang);
        $result = $this->callClaude(self::MODEL_GENERATE, $prompt, 5000, $key, $kbFull);
        if (! $result) return null;

        $json = $this->extractJson($result);

        // Vérifier minimum ~400 mots (≈ 2400 chars) — le prompt demande 600 mots
        $textContent = strip_tags($json['content_html'] ?? '');
        if (! $json || str_word_count($textContent) < 150) {
            Log::warning('NewsGenerationService: contenu trop court', [
                'words' => str_word_count($textContent),
            ]);
            return null;
        }

        return $json;
    }

    // ─────────────────────────────────────────
    // ENVOI BLOG
    // ─────────────────────────────────────────

    private function sendToBlog(RssFeedItem $item, array $content): bool
    {
        $blogUrl = rtrim(config('services.blog.url', ''), '/');
        $blogKey = config('services.blog.api_key', '');

        if (! $blogUrl || ! $blogKey) {
            Log::warning('NewsGenerationService: Blog URL ou API key manquant');
            return false;
        }

        $uuid = "mc_rss_{$item->id}";

        $qm = $content['quality_metrics'] ?? [];

        $payload = [
            'uuid'               => $uuid,
            'event'              => 'create',
            'content_type'       => 'news',
            'language'           => $item->language,
            'title'              => $content['title'] ?? $content['meta_title'] ?? $item->title,
            'content_html'       => $content['content_html'] ?? '',
            'excerpt'            => $content['excerpt'] ?? null,
            'meta_title'         => $content['meta_title'] ?? null,
            'meta_description'   => $content['meta_description'] ?? null,
            'ai_summary'         => $content['ai_summary'] ?? null,
            'keywords_primary'   => $content['keywords_primary'] ?? null,
            'keywords_secondary' => $content['keywords_secondary'] ?? [],
            'source_url'         => $item->url,
            'source_name'        => $item->source_name,
            'published_at'       => now()->toIso8601String(),
            'seo_score'          => $qm['seo_score'] ?? null,
            'quality_score'      => $qm['quality_score'] ?? null,
            'readability_score'  => $qm['readability_score'] ?? null,
            'faqs'               => array_map(fn($f) => [
                'question' => $f['question'],
                'answer'   => $f['answer'],
            ], $content['faqs'] ?? []),
        ];

        if ($item->country) {
            $payload['country'] = strtoupper($item->country);
        }

        $response = Http::withToken($blogKey)->timeout(60)
            ->post("{$blogUrl}/api/v1/webhook/article", $payload);

        if (! $response->successful()) {
            Log::error('NewsGenerationService: Blog webhook erreur', [
                'status'  => $response->status(),
                'item_id' => $item->id,
            ]);
        }

        return $response->successful();
    }

    // ─────────────────────────────────────────
    // CLAUDE API
    // ─────────────────────────────────────────

    private function callClaude(string $model, string $prompt, int $maxTokens, string $key, ?string $systemPrompt = null): ?string
    {
        $body = [
            'model'      => $model,
            'max_tokens' => $maxTokens,
            'messages'   => [['role' => 'user', 'content' => $prompt]],
        ];

        if ($systemPrompt) {
            $body['system'] = $systemPrompt;
        }

        $response = Http::withHeaders([
            'x-api-key'         => $key,
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        ])->timeout(120)->post('https://api.anthropic.com/v1/messages', $body);

        if (! $response->successful()) {
            Log::error('NewsGenerationService: Claude API error', [
                'model'  => $model,
                'status' => $response->status(),
            ]);
            return null;
        }

        return $response->json('content.0.text');
    }

    private function extractJson(string $text): ?array
    {
        $text  = trim(preg_replace(['/^```(?:json)?\s*/i', '/\s*```$/i'], '', trim($text)));
        $start = strpos($text, '{');
        $end   = strrpos($text, '}');
        if ($start === false || $end === false || $end <= $start) return null;

        try {
            $decoded = json_decode(substr($text, $start, $end - $start + 1), true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : null;
        } catch (\JsonException) {
            return null;
        }
    }
}
