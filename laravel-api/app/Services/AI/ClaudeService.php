<?php

namespace App\Services\AI;

use App\Models\ApiCost;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Anthropic Claude API — Claude Opus 4.6, Sonnet 4.6, Haiku 4.5.
 * Same interface as OpenAiService: complete(), translate(), isConfigured().
 * Use for content types that don't need GPT-4o quality (outreach, short QA, translations).
 */
class ClaudeService
{
    private string $apiKey;
    private string $defaultModel;
    private int    $timeout;

    /** Model pricing in dollars per 1M tokens */
    private const MODEL_PRICING = [
        'claude-opus-4-6'            => ['input' => 15.00, 'output' => 75.00],
        'claude-sonnet-4-6'          => ['input' =>  3.00, 'output' => 15.00],
        'claude-haiku-4-5-20251001'  => ['input' =>  0.80, 'output' =>  4.00],
    ];

    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';

    public function __construct()
    {
        $this->apiKey       = config('services.claude.api_key', '');
        $this->defaultModel = config('services.claude.model', 'claude-sonnet-4-6');
        $this->timeout      = (int) config('services.claude.timeout', 180);
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Send a completion request to Claude.
     * Same return shape as OpenAiService::complete().
     */
    public function complete(string $systemPrompt, string $userPrompt, array $options = []): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Claude API key not configured', 'content' => ''];
        }

        $model       = $options['model'] ?? $this->defaultModel;
        $temperature = $options['temperature'] ?? 0.7;
        $maxTokens   = $options['max_tokens'] ?? 4000;

        $body = [
            'model'      => $model,
            'max_tokens' => $maxTokens,
            'system'     => $systemPrompt,
            'messages'   => [
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'temperature' => $temperature,
        ];

        $startTime = microtime(true);

        try {
            $response = Http::withHeaders([
                'x-api-key'         => $this->apiKey,
                'anthropic-version' => self::API_VERSION,
                'Content-Type'      => 'application/json',
            ])->timeout($this->timeout)->post(self::API_URL, $body);

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            if ($response->successful()) {
                $data         = $response->json();
                $content      = $data['content'][0]['text'] ?? '';
                $tokensInput  = $data['usage']['input_tokens']  ?? 0;
                $tokensOutput = $data['usage']['output_tokens'] ?? 0;

                $this->trackCost(
                    $model,
                    $tokensInput,
                    $tokensOutput,
                    $options['costable_type'] ?? null,
                    $options['costable_id']   ?? null
                );

                Log::info('Claude complete OK', [
                    'model'         => $model,
                    'tokens_input'  => $tokensInput,
                    'tokens_output' => $tokensOutput,
                    'duration_ms'   => $durationMs,
                ]);

                return [
                    'success'       => true,
                    'content'       => $content,
                    'tokens_input'  => $tokensInput,
                    'tokens_output' => $tokensOutput,
                    'model'         => $model,
                    'duration_ms'   => $durationMs,
                ];
            }

            Log::warning('Claude complete error', [
                'status' => $response->status(),
                'body'   => mb_substr($response->body(), 0, 500),
                'model'  => $model,
            ]);

            return [
                'success' => false,
                'error'   => 'HTTP ' . $response->status() . ': ' . $response->body(),
                'content' => '',
            ];
        } catch (\Throwable $e) {
            Log::error('Claude complete exception', [
                'message' => $e->getMessage(),
                'model'   => $model,
            ]);

            return ['success' => false, 'error' => $e->getMessage(), 'content' => ''];
        }
    }

    /**
     * Simple text search — same interface as PerplexityService::search().
     * Uses Claude's training knowledge (no real-time web search).
     */
    public function search(string $query, string $systemPrompt = ''): array
    {
        $system = $systemPrompt ?: 'Tu es un expert en veille et recherche d\'informations. Réponds de façon précise et factuelle en te basant sur tes connaissances.';
        $result = $this->complete($system, $query, [
            'model'       => 'claude-haiku-4-5-20251001',
            'temperature' => 0.3,
            'max_tokens'  => 500,
        ]);
        return [
            'success' => $result['success'],
            'content' => $result['content'] ?? '',
            'error'   => $result['error'] ?? null,
        ];
    }

    /**
     * JSON search — same interface as PerplexityService::searchJson().
     * Parses JSON from Claude's response (handles markdown code blocks).
     */
    public function searchJson(string $query, string $systemPrompt = ''): array
    {
        $system = $systemPrompt ?: 'Tu réponds UNIQUEMENT en JSON valide, sans texte autour. Tableau d\'objets JSON.';
        $result = $this->complete($system, $query, [
            'model'       => 'claude-haiku-4-5-20251001',
            'temperature' => 0.2,
            'max_tokens'  => 4000,
        ]);

        if (!$result['success']) {
            return ['success' => false, 'error' => $result['error'] ?? 'Claude error', 'data' => null];
        }

        $content = trim($result['content']);

        // Extraire JSON d'un bloc markdown si présent
        if (preg_match('/```(?:json)?\s*([\s\S]+?)\s*```/i', $content, $m)) {
            $content = $m[1];
        }

        $decoded = json_decode($content, true);
        if ($decoded !== null) {
            return ['success' => true, 'data' => $decoded, 'raw' => $content];
        }

        // Tentative d'extraction d'un tableau JSON partiel
        if (preg_match('/\[[\s\S]*\]/s', $content, $m)) {
            $decoded = json_decode($m[0], true);
            if ($decoded !== null) {
                return ['success' => true, 'data' => $decoded, 'raw' => $content];
            }
        }

        return ['success' => false, 'error' => 'JSON parse error', 'data' => null, 'raw' => $content];
    }

    /**
     * Translate text preserving HTML structure.
     * Same signature as OpenAiService::translate().
     */
    public function translate(string $text, string $fromLang, string $toLang, array $options = []): array
    {
        $langNames = [
            'fr' => 'French', 'en' => 'English', 'es' => 'Spanish', 'de' => 'German',
            'pt' => 'Portuguese', 'ru' => 'Russian', 'zh' => 'Chinese (Simplified)',
            'ar' => 'Arabic', 'hi' => 'Hindi', 'it' => 'Italian', 'ja' => 'Japanese',
        ];
        $fromName = $langNames[$fromLang] ?? $fromLang;
        $toName   = $langNames[$toLang] ?? $toLang;

        $systemPrompt = "You are a professional translator. Translate the following content from {$fromName} to {$toName}. "
            . "CRITICAL: The entire output MUST be in {$toName}. Do NOT return the original {$fromName} text. "
            . "Maintain the same HTML formatting, structure, and tone. "
            . "Do not translate brand names (SOS-Expat), URLs, or code. Preserve all HTML tags exactly.";

        return $this->complete($systemPrompt, $text, array_merge($options, [
            'model'       => $options['model'] ?? 'claude-haiku-4-5-20251001',
            'temperature' => $options['temperature'] ?? 0.3,
        ]));
    }

    // ─────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────

    private function trackCost(
        string $model,
        int    $inputTokens,
        int    $outputTokens,
        ?string $costableType = null,
        ?int    $costableId   = null
    ): void {
        try {
            $pricing  = self::MODEL_PRICING[$model] ?? ['input' => 3.00, 'output' => 15.00];
            $costUsd  = ($inputTokens / 1_000_000) * $pricing['input']
                      + ($outputTokens / 1_000_000) * $pricing['output'];
            $costCents = (int) round($costUsd * 100);

            if ($costCents > 0) {
                ApiCost::create([
                    'service'       => 'claude',
                    'model'         => $model,
                    'input_tokens'  => $inputTokens,
                    'output_tokens' => $outputTokens,
                    'cost_cents'    => $costCents,
                    'costable_type' => $costableType,
                    'costable_id'   => $costableId,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('ClaudeService: cost tracking failed', ['error' => $e->getMessage()]);
        }
    }
}
