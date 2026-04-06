<?php

namespace App\Console\Commands;

use App\Models\GeneratedArticle;
use App\Services\AI\ClaudeService;
use App\Services\Content\GenerationGuardService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Auto-generate Q/R satellite pages from published articles.
 *
 * For each article, extracts 3-5 "People Also Ask" questions and generates
 * short Q/R pages that link back to the parent article (pillar-satellite strategy).
 *
 * This creates topical clusters that massively boost ranking:
 * - Article "visa travail Thaïlande" (pillar, 2000+ words)
 *   → Q/R "combien coûte un visa travail Thaïlande ?" (satellite, 300-800 words)
 *   → Q/R "délai obtention visa travail Thaïlande" (satellite, 300-800 words)
 *   → Q/R "documents requis visa travail Thaïlande" (satellite, 300-800 words)
 *
 * Usage:
 *   php artisan qr:from-articles                    # Process latest 10 articles
 *   php artisan qr:from-articles --limit=30         # Process 30 articles
 *   php artisan qr:from-articles --country=TH       # Focus on Thailand
 *   php artisan qr:from-articles --questions=5      # 5 Q/R per article (default 3)
 */
class GenerateQrFromArticlesCommand extends Command
{
    protected $signature = 'qr:from-articles
        {--limit=10 : Number of articles to process}
        {--country= : Focus on a specific country code}
        {--questions=3 : Number of Q/R to generate per article}
        {--dry-run : Show discovered questions without generating}';

    protected $description = 'Auto-generate Q/R satellite pages from published articles for topical clustering';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $country = $this->option('country');
        $questionsPerArticle = (int) $this->option('questions');
        $dryRun = $this->option('dry-run');

        $apiKey = config('services.anthropic.api_key') ?: config('services.claude.api_key');
        $blogUrl = rtrim(config('services.blog.url', ''), '/');
        $blogKey = config('services.blog.api_key', '');

        if (!$apiKey) {
            $this->error('ANTHROPIC_API_KEY missing.');
            return 1;
        }

        $guard = app(GenerationGuardService::class);

        $this->info("=== Q/R FROM ARTICLES ===");

        // Fetch published articles that haven't been processed yet
        $query = GeneratedArticle::where('status', 'published')
            ->whereNull('parent_article_id')
            ->where('language', 'fr')
            ->whereNotIn('content_type', ['qa', 'news']) // Don't generate Q/R from Q/R or news
            ->orderByDesc('published_at');

        if ($country) {
            $query->where('country', strtoupper($country));
        }

        $articles = $query->limit($limit)->get();

        $this->info("Processing {$articles->count()} articles...");

        $totalQuestions = 0;
        $totalGenerated = 0;
        $totalSkipped = 0;

        foreach ($articles as $article) {
            $this->line("  Article: " . mb_substr($article->title, 0, 60) . "...");

            // Extract questions from article
            $questions = $this->extractQuestions($article, $questionsPerArticle, $apiKey);

            foreach ($questions as $q) {
                $totalQuestions++;

                // Guard check — avoid duplicates
                $guardResult = $guard->checkQa($q['question'], 'fr');
                if ($guardResult['status'] === 'block') {
                    $this->line("    SKIP (doublon): " . mb_substr($q['question'], 0, 60));
                    $totalSkipped++;
                    continue;
                }

                if ($dryRun) {
                    $this->line("    [{$q['intent']}] {$q['question']}");
                    continue;
                }

                // Generate Q/R content
                $content = $this->generateQr($q, $article, $apiKey);
                if (!$content) {
                    $this->warn("    FAIL: génération échouée");
                    continue;
                }

                // Send to Blog
                $sent = $this->sendToBlog($content, $article, $blogUrl, $blogKey);
                if ($sent) {
                    $totalGenerated++;
                    $this->line("    OK: {$q['question']}");
                } else {
                    $this->warn("    FAIL: envoi blog échoué");
                }

                sleep(2); // Rate limiting
            }
        }

        $this->newLine();
        $this->info("=== RESULTS ===");
        $this->info("Questions discovered: {$totalQuestions}");
        $this->info("Generated: {$totalGenerated}");
        $this->info("Skipped (duplicates): {$totalSkipped}");

        return 0;
    }

    private function extractQuestions(GeneratedArticle $article, int $count, string $apiKey): array
    {
        $contentSnippet = mb_substr(strip_tags($article->content_html ?? ''), 0, 2000);
        $countryCtx = $article->country ? "Pays: {$article->country}" : '';

        $prompt = <<<PROMPT
Analyse cet article et genere exactement {$count} questions "People Also Ask" que des expatries/voyageurs de TOUTE nationalite taperaient dans Google apres avoir lu cet article.

Article: "{$article->title}"
{$countryCtx}
Extrait: {$contentSnippet}

REGLES :
- Questions NATURELLES (comme un vrai utilisateur les taperait)
- Questions COMPLEMENTAIRES a l'article (pas la meme question reformulee)
- Chaque question = 1 requete longue traine specifique
- PAS de questions generiques — chaque question doit avoir une reponse PRECISE
- PAS de biais nationalite (pas "pour les francais" — pour TOUS les expatries)
- Classer chaque question par intention : informational, commercial_investigation, transactional, urgency
- Inclure le nom du pays si l'article est pays-specifique

Retourne en JSON UNIQUEMENT :
[
  {"question": "question naturelle 4+ mots ?", "intent": "informational", "category": "visa"},
  {"question": "autre question precise ?", "intent": "transactional", "category": "assurance"}
]
PROMPT;

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
            'model' => 'claude-haiku-4-5-20251001',
            'max_tokens' => 1000,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        if (!$response->successful()) return [];

        $text = $response->json('content.0.text', '');
        return $this->parseJson($text) ?? [];
    }

    private function generateQr(array $question, GeneratedArticle $parentArticle, string $apiKey): ?array
    {
        $countryCtx = $parentArticle->country
            ? "Pays: {$parentArticle->country}. TOUTES les infos doivent etre specifiques a ce pays."
            : '';

        $parentLink = $parentArticle->slug
            ? "Lien vers l'article parent: <a href=\"/articles/{$parentArticle->slug}\">{$parentArticle->title}</a>"
            : '';

        $kbService = app(\App\Services\Content\KnowledgeBaseService::class);
        $kbContext = $kbService->getLightPrompt('qr', $parentArticle->country, 'fr');

        $prompt = <<<PROMPT
Tu es expert en expatriation internationale. Redige une page Q/R SEO courte et directe.

Question: "{$question['question']}"
Intention: {$question['intent']}
{$countryCtx}
Article parent: "{$parentArticle->title}"

REGLES :
- content_html: 300-800 mots, HTML avec <h2>, <h3>, <p>, <ul>, <strong>. PAS de <h1>.
- Premier paragraphe = reponse DIRECTE en 40-60 mots (featured snippet Google)
- S'adresse a TOUTE nationalite (pas juste les francais)
- 3-5 FAQ complementaires (80-150 mots chacune)
- 1 lien interne vers l'article parent : {$parentLink}
- Ton pratique, concret, accessible
- meta_title: max 60 chars, question reformulee pour le SEO
- meta_description: 140-155 chars, incitative
- ai_summary: 1 phrase max 100 chars

Retourne en JSON UNIQUEMENT :
{{
  "meta_title": "string max 60 chars",
  "meta_description": "string 140-155 chars",
  "excerpt": "string max 120 chars",
  "keywords_primary": "mots-cles principaux",
  "ai_summary": "resume factuel max 100 chars",
  "content_html": "<p>Reponse directe...</p><h2>...</h2>...",
  "faqs": [{{"question": "?", "answer": "<p>...</p>"}}]
}}
PROMPT;

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
        $json = $this->parseJson($text);

        if (!$json || empty($json['content_html'])) return null;

        return $json;
    }

    private function sendToBlog(array $content, GeneratedArticle $parentArticle, string $blogUrl, string $blogKey): bool
    {
        if (!$blogUrl || !$blogKey) return false;

        $uuid = 'mc_qr_auto_' . md5($content['meta_title'] ?? '' . now()->timestamp);

        $payload = [
            'uuid' => $uuid,
            'event' => 'create',
            'content_type' => 'qa',
            'language' => 'fr',
            'title' => $content['meta_title'] ?? '',
            'content_html' => $content['content_html'] ?? '',
            'excerpt' => $content['excerpt'] ?? null,
            'meta_title' => $content['meta_title'] ?? null,
            'meta_description' => $content['meta_description'] ?? null,
            'ai_summary' => $content['ai_summary'] ?? null,
            'keywords_primary' => $content['keywords_primary'] ?? null,
            'faqs' => array_map(fn($f) => [
                'question' => $f['question'],
                'answer' => $f['answer'],
            ], $content['faqs'] ?? []),
            'published_at' => now()->toIso8601String(),
        ];

        if ($parentArticle->country) {
            $payload['country'] = strtoupper($parentArticle->country);
        }

        $response = Http::withToken($blogKey)->timeout(60)
            ->post("{$blogUrl}/api/v1/webhook/article", $payload);

        return $response->successful();
    }

    private function parseJson(string $text): ?array
    {
        $text = trim(preg_replace(['/^```(?:json)?\s*/i', '/\s*```$/i'], '', trim($text)));

        // Try array first
        $start = strpos($text, '[');
        $end = strrpos($text, ']');
        if ($start !== false && $end !== false && $end > $start) {
            try {
                $decoded = json_decode(substr($text, $start, $end - $start + 1), true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) return $decoded;
            } catch (\JsonException) {}
        }

        // Try object
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            try {
                $decoded = json_decode(substr($text, $start, $end - $start + 1), true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) return $decoded;
            } catch (\JsonException) {}
        }

        return null;
    }
}
