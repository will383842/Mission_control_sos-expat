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

        $apiKey = config('services.anthropic.api_key') ?: config('services.claude.api_key');
        $blogUrl = rtrim(config('services.blog.url', ''), '/');
        $blogKey = config('services.blog.api_key', '');

        if (!$apiKey || !$blogUrl || !$blogKey) {
            Log::warning('QrSatellites: missing API key or Blog config');
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

        foreach (array_slice($questions, 0, 5) as $question) {
            // Guard check — skip if duplicate Q/R exists
            $guardResult = $guard->checkQa($question, $article->language ?? 'fr');
            if ($guardResult['status'] === 'block') {
                Log::info("QrSatellites: duplicate Q/R skipped", ['question' => $question]);
                continue;
            }

            // Generate Q/R content
            $content = $this->generateQr($question, $article, $apiKey, $kb);
            if (!$content) continue;

            // Send to Blog
            $sent = $this->sendToBlog($content, $article, $question, $blogUrl, $blogKey);
            if ($sent) {
                $generated++;
                Log::info("QrSatellites: published Q/R", ['question' => mb_substr($question, 0, 60), 'parent' => $article->id]);
            }

            sleep(rand(3, 8)); // Natural spacing
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

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(30)->post('https://api.anthropic.com/v1/messages', [
            'model' => 'claude-haiku-4-5-20251001',
            'max_tokens' => 300,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        if (!$response->successful()) return [];

        $text = $response->json('content.0.text', '');
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
            . "- meta_title: max 60 chars | meta_description: 140-155 chars | ai_summary: max 100 chars\n\n"
            . "Retourne en JSON UNIQUEMENT:\n"
            . "{\"meta_title\":\"...\",\"meta_description\":\"...\",\"excerpt\":\"...\",\"keywords_primary\":\"...\",\"ai_summary\":\"...\",\"content_html\":\"...\",\"faqs\":[{\"question\":\"?\",\"answer\":\"<p>...</p>\"}]}";

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(120)->post('https://api.anthropic.com/v1/messages', [
            'model' => 'claude-sonnet-4-6',
            'max_tokens' => 4000,
            'system' => $kbContext,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        if (!$response->successful()) return null;

        $text = $response->json('content.0.text', '');
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
}
