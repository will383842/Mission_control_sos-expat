<?php

namespace App\Jobs;

use App\Models\GeneratedArticle;
use App\Services\Content\GenerationGuardService;
use App\Services\Content\KnowledgeBaseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Auto-generate 3 Q/R satellite pages after an article is published.
 *
 * Creates a topical cluster:
 *   Article (pillar) → 3 Q/R (satellites) → each links back to parent
 *
 * Each Q/R:
 * - Has its own page on sos-expat.com (/vie-a-letranger/{slug})
 * - Is indexed by Google separately
 * - Has FAQPage JSON-LD schema
 * - Is translated to 9 languages
 * - Links back to parent article (internal maillage)
 *
 * Dispatched async after article generation (2 min delay for natural spacing).
 */
class GenerateQrSatellitesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 2;
    public int $backoff = 60;

    public function __construct(private readonly int $articleId) {}

    public function handle(): void
    {
        $article = GeneratedArticle::find($this->articleId);
        if (!$article || !$article->title) {
            Log::info("QrSatellites: article {$this->articleId} not found or no title");
            return;
        }

        // Don't generate satellites for Q/R or news (avoid infinite loop)
        if (in_array($article->content_type, ['qa', 'news'], true)) {
            return;
        }

        $apiKey = config('services.anthropic.api_key') ?: config('services.claude.api_key', '');
        $blogUrl = rtrim(config('services.blog.url', ''), '/');
        $blogKey = config('services.blog.api_key', '');

        // GPT primary, Claude fallback — only OpenAI is required.
        $openai = app(\App\Services\AI\OpenAiService::class);
        if ((! $openai->isConfigured() && ! $apiKey) || !$blogUrl || !$blogKey) {
            Log::warning('QrSatellites: missing AI key (need OpenAI or Anthropic) or Blog config');
            return;
        }

        $guard = app(GenerationGuardService::class);
        $kb = app(KnowledgeBaseService::class);

        // 1. Extract 3 questions from the article
        $questions = $this->extractQuestions($article, $apiKey);
        if (empty($questions)) {
            Log::info("QrSatellites: no questions extracted for article {$article->id}");
            return;
        }

        $generated = 0;
        $publishedQuestions = [];

        foreach (array_slice($questions, 0, 5) as $question) {
            // Guard check 1 — skip if duplicate Q/R exists
            $guardResult = $guard->checkQa($question, $article->language ?? 'fr');
            if ($guardResult['status'] === 'block') {
                Log::info("QrSatellites: duplicate Q/R skipped", ['question' => $question]);
                continue;
            }

            // Guard check 2 — skip if too similar to an existing ARTICLE (cross-type anti-cannibalization)
            $crossCheck = $guard->check($question, 'qa', $article->language ?? 'fr', $article->country);
            if ($crossCheck['status'] === 'block') {
                Log::info("QrSatellites: cross-type duplicate skipped (article exists)", ['question' => $question]);
                continue;
            }

            // Generate Q/R content
            $content = $this->generateQr($question, $article, $apiKey, $kb);
            if (!$content) continue;

            // Send to Blog
            $sent = $this->sendToBlog($content, $article, $question, $blogUrl, $blogKey);
            if ($sent) {
                $generated++;
                $publishedQuestions[] = $question;
                Log::info("QrSatellites: published Q/R", ['question' => mb_substr($question, 0, 60), 'parent' => $article->id]);
            }

            sleep(rand(3, 8)); // Natural spacing
        }

        // Add internal links FROM parent article TO Q/R satellites
        if ($generated > 0 && !empty($publishedQuestions)) {
            $this->addLinksToParent($article, $publishedQuestions, $blogUrl, $blogKey);
        }

        Log::info("QrSatellites: {$generated}/5 Q/R generated for article {$article->id}");
    }

    private function extractQuestions(GeneratedArticle $article, string $apiKey): array
    {
        $contentSnippet = mb_substr(strip_tags($article->content_html ?? ''), 0, 2000);

        $prompt = "Analyse cet article et genere exactement 5 questions 'People Also Ask' que des expatries/voyageurs de TOUTE nationalite (pas uniquement francophones) taperaient dans Google. "
            . "Questions COMPLEMENTAIRES au sujet (pas la meme question reformulee). 4+ mots minimum. "
            . "Inclure le nom du pays/ville si l'article est specifique. "
            . "S'adresser a des personnes de TOUTE nationalite vivant ou voyageant a l'etranger. "
            . "Retourne en JSON: [\"question 1 ?\", \"question 2 ?\", \"question 3 ?\", \"question 4 ?\", \"question 5 ?\"]"
            . "\n\nArticle: \"{$article->title}\"\nExtrait: {$contentSnippet}";

        // GPT primary
        $text = $this->callAi($prompt, null, 300, $apiKey, 'mini');

        if ($text === null) return [];

        $start = strpos($text, '[');
        $end = strrpos($text, ']');
        if ($start === false || $end === false) return [];

        try {
            $decoded = json_decode(substr($text, $start, $end - $start + 1), true);
            return is_array($decoded) ? array_filter($decoded, fn($q) => is_string($q) && mb_strlen($q) > 15) : [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function generateQr(string $question, GeneratedArticle $article, string $apiKey, KnowledgeBaseService $kb): ?array
    {
        $country = $article->country;
        $kbContext = $kb->getLightPrompt('qr', $country, $article->language ?? 'fr');

        $parentLink = $article->slug ? "<a href=\"/articles/{$article->slug}\">{$article->title}</a>" : '';

        $prompt = "Tu es expert en expatriation internationale. Redige une page Q/R SEO courte et directe.\n\n"
            . "Question: \"{$question}\"\n"
            . ($country ? "Pays: {$country}. TOUTES les infos doivent etre specifiques a ce pays.\n" : '')
            . "Article parent: \"{$article->title}\"\n\n"
            . "REGLES:\n"
            . "- content_html: 300-800 mots, HTML <h2>, <h3>, <p>, <ul>, <strong>. PAS de <h1>.\n"
            . "- Premier paragraphe = reponse DIRECTE en 40-60 mots (featured snippet)\n"
            . "- S'adresse a TOUTE nationalite (pas uniquement francophones — monde entier)\n"
            . "- Dire 'votre ambassade' PAS 'l'ambassade de France'\n"
            . "- 3-5 FAQ complementaires\n"
            . "- 1 lien interne vers l'article parent: {$parentLink}\n"
            . "- meta_title: max 60 chars | meta_description: 140-155 chars | ai_summary: max 160 chars\n\n"
            . "Retourne en JSON UNIQUEMENT:\n"
            . "{\"meta_title\":\"...\",\"meta_description\":\"...\",\"excerpt\":\"...\",\"keywords_primary\":\"...\",\"ai_summary\":\"...\",\"content_html\":\"...\",\"faqs\":[{\"question\":\"?\",\"answer\":\"<p>...</p>\"}]}";

        // GPT primary
        $text = $this->callAi($prompt, $kbContext, 4000, $apiKey, 'standard');

        if ($text === null) return null;

        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false) return null;

        try {
            $json = json_decode(substr($text, $start, $end - $start + 1), true, 512, JSON_THROW_ON_ERROR);
            if (!$json || empty($json['content_html'])) return null;
            return $json;
        } catch (\Throwable) {
            return null;
        }
    }

    private function sendToBlog(array $content, GeneratedArticle $parent, string $question, string $blogUrl, string $blogKey): bool
    {
        $uuid = 'mc_qr_sat_' . md5($question . $parent->id);

        $payload = [
            'uuid' => $uuid,
            'event' => 'create',
            'content_type' => 'qa',
            'language' => $parent->language ?? 'fr',
            'title' => $content['meta_title'] ?? mb_substr($question, 0, 60),
            'content_html' => $content['content_html'] ?? '',
            'excerpt' => $content['excerpt'] ?? null,
            'meta_title' => $content['meta_title'] ?? null,
            'meta_description' => $content['meta_description'] ?? null,
            'ai_summary' => $content['ai_summary'] ?? null,
            'keywords_primary' => $content['keywords_primary'] ?? null,
            'faqs' => array_map(fn($f) => ['question' => $f['question'], 'answer' => $f['answer']], $content['faqs'] ?? []),
            'published_at' => now()->toIso8601String(),
        ];

        if ($parent->country) {
            $payload['country'] = strtoupper($parent->country);
        }

        $response = Http::withToken($blogKey)->timeout(60)
            ->post("{$blogUrl}/api/v1/webhook/article", $payload);

        return $response->successful();
    }

    /**
     * Add a "Questions fréquentes liées" section at the end of the parent article
     * with links to the published Q/R satellites.
     * This creates the bidirectional maillage: parent ↔ satellites.
     */
    private function addLinksToParent(GeneratedArticle $article, array $questions, string $blogUrl, string $blogKey): void
    {
        if (empty($questions)) return;

        // Build HTML block with links to Q/R pages
        $linksHtml = "\n<div class=\"summary-box\">\n<p><strong>Questions frequemment posees</strong></p>\n<ul>\n";
        foreach ($questions as $q) {
            $slug = \Illuminate\Support\Str::slug(mb_substr($q, 0, 60));
            $linksHtml .= "<li><a href=\"/vie-a-letranger/{$slug}\">{$q}</a></li>\n";
        }
        $linksHtml .= "</ul>\n</div>\n";

        // Append to parent article content (before CTA if present)
        $html = $article->content_html ?? '';
        if (str_contains($html, 'cta-box')) {
            $html = str_replace('<div class="cta-box">', $linksHtml . '<div class="cta-box">', $html);
        } else {
            $html .= $linksHtml;
        }

        $article->update(['content_html' => $html]);

        // Also update on Blog via webhook (update event)
        try {
            Http::withToken($blogKey)->timeout(30)
                ->post("{$blogUrl}/api/v1/webhook/article", [
                    'uuid' => $article->uuid,
                    'event' => 'update',
                    'content_html' => $html,
                ]);
        } catch (\Throwable $e) {
            Log::warning("QrSatellites: failed to update parent on blog", ['error' => $e->getMessage()]);
        }
    }

    /**
     * GPT primary, Claude fallback. Switched 2026-04-11 to isolate Q/R
     * satellites from Anthropic credit / availability issues.
     *
     * @param  string  $tier  'mini' for cheap (gpt-4o-mini + haiku) or 'standard' (gpt-4o + sonnet)
     */
    private function callAi(string $prompt, ?string $systemPrompt, int $maxTokens, string $anthropicKey, string $tier = 'standard'): ?string
    {
        $gptModel    = $tier === 'mini' ? 'gpt-4o-mini' : 'gpt-4o';
        $claudeModel = $tier === 'mini' ? 'claude-haiku-4-5-20251001' : 'claude-sonnet-4-6';

        /** @var \App\Services\AI\OpenAiService $openai */
        $openai = app(\App\Services\AI\OpenAiService::class);

        if ($openai->isConfigured()) {
            $result = $openai->complete($systemPrompt ?? '', $prompt, [
                'model'      => $gptModel,
                'max_tokens' => $maxTokens,
            ]);
            if (!empty($result['success']) && !empty($result['content'])) {
                return $result['content'];
            }
            Log::warning('QrSatellites: GPT primary failed, falling back to Claude', [
                'gpt_model' => $gptModel,
                'error'     => $result['error'] ?? 'unknown',
            ]);
        }

        // Fallback Claude
        if (empty($anthropicKey)) return null;

        $body = [
            'model'      => $claudeModel,
            'max_tokens' => $maxTokens,
            'messages'   => [['role' => 'user', 'content' => $prompt]],
        ];
        if ($systemPrompt) $body['system'] = $systemPrompt;

        $response = Http::withHeaders([
            'x-api-key'         => $anthropicKey,
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        ])->timeout(120)->post('https://api.anthropic.com/v1/messages', $body);

        if (!$response->successful()) {
            Log::error('QrSatellites: Claude fallback also failed', ['status' => $response->status()]);
            return null;
        }

        return $response->json('content.0.text');
    }
}
