<?php

namespace App\Jobs;

use App\Models\ContentQuestion;
use App\Services\Content\GenerationGuardService;
use App\Services\Content\KnowledgeBaseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GenerateQrBlogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 7200;  // 2h max
    public int $tries   = 1;

    private const PROGRESS_KEY   = 'qr_blog_generation_progress';
    private const MODEL_GENERATE = 'claude-sonnet-4-6';
    private const MODEL_TRANSLATE = 'claude-haiku-4-5-20251001';
    private const LANGUAGES = ['fr', 'en', 'es', 'de', 'pt', 'ru', 'zh', 'hi', 'ar'];
    private const LANGUAGE_NAMES = [
        'fr' => 'French',    'en' => 'English',   'es' => 'Spanish',
        'de' => 'German',    'pt' => 'Portuguese', 'ru' => 'Russian',
        'zh' => 'Simplified Chinese', 'hi' => 'Hindi', 'ar' => 'Arabic',
    ];

    public function __construct(private readonly array $questionIds) {}

    public function handle(): void
    {
        $anthropicKey = config('services.anthropic.api_key') ?: config('services.claude.api_key', '');
        $blogApiUrl   = rtrim(config('services.blog.url', ''), '/');
        $blogApiKey   = config('services.blog.api_key', '');

        // GPT is now primary, Claude is fallback only — only require at least
        // one of them to be configured. OpenAI alone is enough.
        $openai = app(\App\Services\AI\OpenAiService::class);
        if (! $openai->isConfigured() && ! $anthropicKey) {
            $this->updateProgress(['status' => 'failed', 'finished_at' => now()->toIso8601String(),
                'log' => [['type' => 'error', 'msg' => 'Aucune clé AI configurée (OPENAI_API_KEY ou ANTHROPIC_API_KEY)']]]);
            return;
        }

        $total     = count($this->questionIds);
        $completed = 0;
        $skipped   = 0;
        $errors    = 0;
        $log       = [];

        foreach ($this->questionIds as $qId) {
            $question = ContentQuestion::find($qId);
            if (! $question) {
                $errors++;
                continue;
            }

            // Marquer en cours
            $question->update(['article_status' => 'writing']);
            $this->updateProgress([
                'current_title' => $question->title,
                'completed'     => $completed,
                'skipped'       => $skipped,
                'errors'        => $errors,
            ]);

            try {
                // 0. Generation Guard: dedup check
                $guard = app(GenerationGuardService::class);
                $guardResult = $guard->checkQa($question->title, 'fr');
                if ($guardResult['status'] === 'block') {
                    $log[] = ['type' => 'skip', 'id' => $qId, 'title' => $question->title, 'reason' => 'Duplicate: ' . $guardResult['reason']];
                    $question->update(['article_status' => 'skipped']);
                    $skipped++;
                    continue;
                }

                // 1. Optimiser le titre + évaluer pertinence
                $optimization = $this->optimizeTitle($question->toArray(), $anthropicKey);

                if ($optimization['skip'] ?? false) {
                    $log[] = ['type' => 'skip', 'id' => $qId, 'title' => $question->title, 'reason' => $optimization['reason'] ?? ''];
                    $question->update(['article_status' => 'skipped']);
                    $skipped++;
                    continue;
                }

                $title    = $optimization['title']    ?? $question->title;
                $country  = $optimization['country']  ?? $question->country;
                $category = $optimization['category'] ?? 'quotidien';

                // 2. Générer le contenu FR complet
                $frContent = $this->generateFrContent($title, $country, $category, $anthropicKey);

                if (! $frContent) {
                    $log[] = ['type' => 'error', 'id' => $qId, 'title' => $title, 'reason' => 'Génération FR échouée'];
                    $question->update(['article_status' => 'opportunity']);
                    $errors++;
                    continue;
                }

                // 2b. Post-process: quality check, SEO score, plagiarism
                $postProcessor = app(\App\Services\Content\ContentPostProcessor::class);
                $frContent = $postProcessor->process($frContent, 'qa', 'fr', $country, "qr_blog_{$qId}");

                $qm = $frContent['quality_metrics'] ?? [];
                if (! ($qm['passed'] ?? true)) {
                    $log[] = ['type' => 'warning', 'id' => $qId, 'title' => $title, 'reason' => 'Qualite insuffisante: ' . implode(', ', $qm['issues'] ?? [])];
                    // Continue anyway — just log the warning, don't block
                }

                // 3. Envoyer FR au Blog (le Blog traduit automatiquement en 8 langues via TranslateArticleJob)
                $uuid = "mc_question_{$qId}";
                $sentFr = $this->sendToBlog($uuid, 'fr', $country, $frContent, $blogApiUrl, $blogApiKey);

                if (! $sentFr) {
                    $log[] = ['type' => 'error', 'id' => $qId, 'title' => $title, 'reason' => 'Envoi FR Blog échoué'];
                    $question->update(['article_status' => 'opportunity']);
                    $errors++;
                    continue;
                }

                // 5. Marquer publiée
                $question->update(['article_status' => 'published']);
                $log[] = ['type' => 'success', 'id' => $qId, 'title' => $title, 'optimized_title' => $title];
                $completed++;

            } catch (\Throwable $e) {
                Log::error("GenerateQrBlogJob question #{$qId}", ['error' => $e->getMessage()]);
                $log[] = ['type' => 'error', 'id' => $qId, 'title' => $question->title, 'reason' => $e->getMessage()];
                $question->update(['article_status' => 'opportunity']);
                $errors++;
            }

            // Mise à jour progression
            $this->updateProgress([
                'completed'     => $completed,
                'skipped'       => $skipped,
                'errors'        => $errors,
                'current_title' => null,
                'log'           => array_slice($log, -20), // 20 derniers logs
            ]);

            sleep(2); // Pause entre Q/R
        }

        // Terminer
        $this->updateProgress([
            'status'        => 'completed',
            'completed'     => $completed,
            'skipped'       => $skipped,
            'errors'        => $errors,
            'current_title' => null,
            'finished_at'   => now()->toIso8601String(),
            'log'           => array_slice($log, -50),
        ]);

        // Mettre à jour total_generated dans le schedule
        if ($completed > 0) {
            try {
                $raw = DB::table('settings')->where('key', 'qr_schedule')->value('value');
                $sched = $raw ? json_decode($raw, true) : [];
                $sched['total_generated'] = ($sched['total_generated'] ?? 0) + $completed;
                // Auto-désactiver si objectif total atteint
                if (($sched['duration_type'] ?? '') === 'total' && isset($sched['total_goal'])) {
                    if ($sched['total_generated'] >= (int) $sched['total_goal']) {
                        $sched['active'] = false;
                        Log::info('QrBlogJob: total_goal atteint → schedule désactivé.', ['total' => $sched['total_generated']]);
                    }
                }
                DB::table('settings')->updateOrInsert(
                    ['key' => 'qr_schedule'],
                    ['value' => json_encode($sched), 'updated_at' => now()]
                );
            } catch (\Throwable $e) {
                Log::warning('QrBlogJob: impossible de mettre à jour total_generated', ['error' => $e->getMessage()]);
            }
        }
    }

    // ─────────────────────────────────────────
    // OPTIMISATION TITRE + PERTINENCE
    // ─────────────────────────────────────────

    private function optimizeTitle(array $q, string $key): array
    {
        $prompt = <<<PROMPT
Tu analyses une question de forum d'expatriés pour décider si elle mérite une page Q/R SEO.

Question source : "{$q['title']}"
Pays associé    : {$q['country']}
Vues forum      : {$q['views']} | Réponses : {$q['replies']}

1. Est-elle pertinente pour un public large d'expatriés/voyageurs ? Si trop personnelle ou hors sujet → skip.
2. Reformuler le titre pour Google : mot-clé principal en début, intention claire, naturel, max 65 chars.
3. Identifier le pays (code ISO 2 lettres majuscules) ou null si générique.
4. Catégorie parmi : visa, logement, sante, fiscalite, administratif, urgence, quotidien, travail, etudes, retraite.

Réponds UNIQUEMENT en JSON :
{"skip":false,"reason":null,"title":"Titre optimisé","country":"FR","category":"visa"}
PROMPT;

        $kb = app(KnowledgeBaseService::class);
        $kbLight = $kb->getLightPrompt('qr', $q['country'] ?? null, 'fr');
        $result = $this->callClaude(self::MODEL_GENERATE, $prompt, 200, $key, $kbLight);
        if (! $result) return ['skip' => false, 'title' => $q['title'], 'country' => $q['country'], 'category' => 'quotidien'];

        return $this->extractJson($result) ?: ['skip' => false, 'title' => $q['title'], 'country' => $q['country'], 'category' => 'quotidien'];
    }

    // ─────────────────────────────────────────
    // GÉNÉRATION CONTENU FR
    // ─────────────────────────────────────────

    private function generateFrContent(string $title, ?string $country, string $category, string $key): ?array
    {
        $countryCtx = $country
            ? "Pays ciblé : {$country}. TOUTES les informations doivent être spécifiques à ce pays. Jamais de réponses génériques."
            : "Applicable à plusieurs pays : infos générales valides pour l'expatriation en général.";

        $prompt = <<<PROMPT
Tu es expert en expatriation et droit international. Tu rédiges une page Q/R SEO pour sos-expat.com.

Titre Q/R : "{$title}"
Catégorie  : {$category}
{$countryCtx}

Public : expatriés, voyageurs longue durée, nomades numériques, travailleurs internationaux.

RÈGLES :
- content_html : min 600 mots, HTML avec <h2>, <h3>, <ul>, <strong>, <p>. PAS de <h1>.
- Premier paragraphe = réponse directe et utile immédiatement
- 5 à 7 sous-questions FAQ, chacune 80-200 mots, concrètes et actionnables
- meta_title : max 60 chars, mot-clé principal au début
- meta_description : 140-155 chars, incitative
- Cohérence pays absolue si country est spécifié

Réponds UNIQUEMENT en JSON valide (sans markdown) :
{
  "meta_title": "string max 60 chars",
  "meta_description": "string 140-155 chars",
  "excerpt": "string 1-2 phrases max 120 chars",
  "keywords_primary": "3-5 mots-clés séparés virgule",
  "keywords_secondary": ["longue traîne 1", "longue traîne 2", "longue traîne 3"],
  "ai_summary": "1 phrase max 160 chars pour IA",
  "content_html": "<p>Intro...</p><h2>...</h2>...",
  "faqs": [
    {"question": "Question ?", "answer": "<p>Réponse HTML...</p>"},
    {"question": "Question ?", "answer": "<p>Réponse HTML...</p>"},
    {"question": "Question ?", "answer": "<p>Réponse HTML...</p>"},
    {"question": "Question ?", "answer": "<p>Réponse HTML...</p>"},
    {"question": "Question ?", "answer": "<p>Réponse HTML...</p>"}
  ]
}
PROMPT;

        $kb = app(KnowledgeBaseService::class);
        $kbFull = $kb->getSystemPrompt('qr', $country, 'fr');
        $result = $this->callClaude(self::MODEL_GENERATE, $prompt, 4000, $key, $kbFull);
        if (! $result) return null;

        $json = $this->extractJson($result);
        if (! $json || count($json['faqs'] ?? []) < 3 || mb_strlen(strip_tags($json['content_html'] ?? '')) < 300) return null;

        return $json;
    }

    // ─────────────────────────────────────────
    // TRADUCTION
    // ─────────────────────────────────────────

    private function translateContent(array $fr, string $lang, ?string $country, string $key): ?array
    {
        $langName    = self::LANGUAGE_NAMES[$lang] ?? $lang;
        $countryNote = $country ? "Keep all country-specific info ({$country}) accurate in the translation." : '';

        $src = json_encode([
            'meta_title'         => $fr['meta_title'],
            'meta_description'   => $fr['meta_description'],
            'excerpt'            => $fr['excerpt'],
            'keywords_primary'   => $fr['keywords_primary'],
            'keywords_secondary' => $fr['keywords_secondary'] ?? [],
            'ai_summary'         => $fr['ai_summary'] ?? '',
            'content_html'       => $fr['content_html'],
            'faqs'               => $fr['faqs'],
        ], JSON_UNESCAPED_UNICODE);

        $prompt = <<<PROMPT
Translate this Q/R page JSON from French to {$langName}. {$countryNote}
- Preserve all HTML tags
- meta_title max 60 chars in {$langName}
- meta_description 140-155 chars in {$langName}
- keywords: translate to natural {$langName} search terms
- Return ONLY valid JSON

{$src}
PROMPT;

        $result = $this->callClaude(self::MODEL_TRANSLATE, $prompt, 3000, $key);
        if (! $result) return null;

        return $this->extractJson($result);
    }

    // ─────────────────────────────────────────
    // ENVOI BLOG
    // ─────────────────────────────────────────

    private function sendToBlog(string $uuid, string $lang, ?string $country, array $content, string $blogUrl, string $blogKey): bool
    {
        if (! $blogUrl || ! $blogKey) return false;

        // Ensure CTA present
        $html = $content['content_html'] ?? '';
        if (!str_contains($html, 'cta-box') && !str_contains($html, 'sos-expat.com')) {
            $content['content_html'] = $html . "\n" . \App\Helpers\CtaHelper::html($content['language'] ?? 'fr', $content['country'] ?? null);
        }

        $qm = $content['quality_metrics'] ?? [];

        $payload = [
            'uuid'               => $uuid,
            'event'              => 'create',
            'content_type'       => 'qa',
            'language'           => $lang,
            'title'              => $content['meta_title'] ?? '',
            'content_html'       => $content['content_html'] ?? '',
            'excerpt'            => $content['excerpt'] ?? null,
            'meta_title'         => $content['meta_title'] ?? null,
            'meta_description'   => $content['meta_description'] ?? null,
            'ai_summary'         => $content['ai_summary'] ?? null,
            'keywords_primary'   => $content['keywords_primary'] ?? null,
            'keywords_secondary' => $content['keywords_secondary'] ?? [],
            'faqs'               => array_map(fn($f) => [
                'question' => $f['question'],
                'answer'   => $f['answer'],
            ], $content['faqs'] ?? []),
            'published_at'       => now()->toIso8601String(),
            'seo_score'          => $qm['seo_score'] ?? null,
            'quality_score'      => $qm['quality_score'] ?? null,
            'readability_score'  => $qm['readability_score'] ?? null,
        ];

        if ($country) $payload['country'] = strtoupper($country);

        $response = Http::withToken($blogKey)->timeout(60)
            ->post("{$blogUrl}/api/v1/webhook/article", $payload);

        return $response->successful();
    }

    // ─────────────────────────────────────────
    // CLAUDE API
    // ─────────────────────────────────────────

    /**
     * GPT-4o primary, Claude fallback. Switched 2026-04-11 to isolate Q/R
     * generation from Anthropic credit / availability issues. Maps the
     * legacy Claude model arg to a GPT equivalent (haiku → mini, sonnet → 4o).
     */
    private function callClaude(string $model, string $prompt, int $maxTokens, string $key, ?string $systemPrompt = null): ?string
    {
        $gptModel = str_contains($model, 'haiku') ? 'gpt-4o-mini' : 'gpt-4o';

        /** @var \App\Services\AI\OpenAiService $openai */
        $openai = app(\App\Services\AI\OpenAiService::class);

        if ($openai->isConfigured()) {
            $result = $openai->complete($systemPrompt ?? '', $prompt, [
                'model'      => $gptModel,
                'max_tokens' => $maxTokens,
                'json_mode'  => true,
            ]);
            if (!empty($result['success']) && !empty($result['content'])) {
                return $result['content'];
            }
            Log::warning('GenerateQrBlogJob: GPT primary failed, falling back to Claude', [
                'gpt_model' => $gptModel,
                'error'     => $result['error'] ?? 'unknown',
            ]);
        }

        // Fallback: Claude
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
            Log::error('Claude API error in QrBlogJob (fallback also failed)', ['status' => $response->status()]);
            return null;
        }

        return $response->json('content.0.text');
    }

    private function extractJson(string $text): ?array
    {
        $text = trim(preg_replace(['/^```(?:json)?\s*/i', '/\s*```$/i'], '', trim($text)));
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

    // ─────────────────────────────────────────
    // PROGRESS
    // ─────────────────────────────────────────

    private function updateProgress(array $merge): void
    {
        $current = Cache::get(self::PROGRESS_KEY, []);
        Cache::put(self::PROGRESS_KEY, array_merge($current, $merge), now()->addHours(24));
    }
}
