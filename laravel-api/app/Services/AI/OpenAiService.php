<?php

namespace App\Services\AI;

use App\Models\ApiCost;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OpenAI API — GPT-4o, GPT-4o-mini, DALL-E 3.
 * Handles completions, translations, and image generation with cost tracking.
 */
class OpenAiService
{
    private string $apiKey;
    private string $defaultModel;
    private string $translationModel;
    private int $timeout;

    /** Model pricing in dollars per 1M tokens */
    private const MODEL_PRICING = [
        'gpt-4o' => ['input' => 2.50, 'output' => 10.00],
        'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60],
        'dall-e-3' => ['flat' => 0.08],
    ];

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key', '');
        $this->defaultModel = config('services.openai.model', 'gpt-4o');
        $this->translationModel = config('services.openai.translation_model', 'gpt-4o-mini');
        $this->timeout = (int) config('services.openai.timeout', 180);
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Send a chat completion request to OpenAI.
     */
    public function complete(string $systemPrompt, string $userPrompt, array $options = []): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'OpenAI API key not configured', 'content' => ''];
        }

        if (!$this->checkBudget()) {
            return ['success' => false, 'error' => 'AI budget exceeded', 'content' => ''];
        }

        $model = $options['model'] ?? $this->defaultModel;
        $temperature = $options['temperature'] ?? 0.7;
        $maxTokens = $options['max_tokens'] ?? 4000;
        $jsonMode = $options['json_mode'] ?? false;

        $body = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
        ];

        if ($jsonMode) {
            $body['response_format'] = ['type' => 'json_object'];
        }

        $startTime = microtime(true);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout($this->timeout)->post('https://api.openai.com/v1/chat/completions', $body);

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            if ($response->successful()) {
                $data = $response->json();
                $content = $data['choices'][0]['message']['content'] ?? '';
                $tokensInput = $data['usage']['prompt_tokens'] ?? 0;
                $tokensOutput = $data['usage']['completion_tokens'] ?? 0;

                $this->trackCost(
                    $model,
                    $tokensInput,
                    $tokensOutput,
                    $options['costable_type'] ?? null,
                    $options['costable_id'] ?? null
                );

                Log::info('OpenAI complete OK', [
                    'model' => $model,
                    'tokens_input' => $tokensInput,
                    'tokens_output' => $tokensOutput,
                    'duration_ms' => $durationMs,
                ]);

                return [
                    'success' => true,
                    'content' => $content,
                    'tokens_input' => $tokensInput,
                    'tokens_output' => $tokensOutput,
                    'model' => $model,
                    'duration_ms' => $durationMs,
                ];
            }

            Log::warning('OpenAI complete error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'model' => $model,
            ]);

            return [
                'success' => false,
                'error' => 'HTTP ' . $response->status() . ': ' . $response->body(),
                'content' => '',
            ];
        } catch (\Throwable $e) {
            Log::error('OpenAI complete exception', [
                'message' => $e->getMessage(),
                'model' => $model,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'content' => '',
            ];
        }
    }

    /**
     * Translate text preserving HTML structure.
     */
    public function translate(string $text, string $fromLang, string $toLang, array $options = []): array
    {
        $langNames = [
            'fr' => 'French', 'en' => 'English', 'es' => 'Spanish', 'de' => 'German',
            'pt' => 'Portuguese', 'ru' => 'Russian', 'zh' => 'Chinese (Simplified)',
            'ar' => 'Arabic', 'hi' => 'Hindi', 'it' => 'Italian', 'ja' => 'Japanese',
            'ko' => 'Korean', 'nl' => 'Dutch', 'pl' => 'Polish', 'tr' => 'Turkish',
        ];
        $fromName = $langNames[$fromLang] ?? $fromLang;
        $toName   = $langNames[$toLang] ?? $toLang;

        // CJK languages need more tokens (1 char ≈ 2-3 tokens vs ~1.3 for Latin languages)
        // Arabic/Hindi also need more tokens due to complex script tokenization
        $tokenMultiplier = match ($toLang) {
            'zh', 'ja', 'ko' => 3.0,       // CJK: 1 character ≈ 2-3 tokens
            'ar', 'hi', 'ru' => 2.0,        // Complex scripts
            default => 1.5,                  // Latin languages
        };
        // Estimate source tokens: ~1.3 tokens per word (French), then multiply for target
        $sourceWordCount = str_word_count(strip_tags($text));
        $estimatedMaxTokens = max(1000, (int) ($sourceWordCount * 1.3 * $tokenMultiplier));
        // Cap at 16384 (GPT-4o-mini limit)
        $estimatedMaxTokens = min(16384, $estimatedMaxTokens);

        $systemPrompt = "You are a professional translator. Translate the following content from {$fromName} to {$toName}. "
            . "CRITICAL: The entire output MUST be in {$toName}. Do NOT return the original {$fromName} text. "
            . "Maintain the same HTML formatting, structure, and tone. "
            . "Do not translate brand names (SOS-Expat), URLs, or code. Preserve all HTML tags exactly."
            . ($toLang === 'zh' ? " IMPORTANT: Output the FULL translation. Do not truncate or summarize. The Chinese output should be roughly the same length as the original in terms of content coverage." : '');

        return $this->complete($systemPrompt, $text, array_merge($options, [
            'model' => $options['model'] ?? $this->translationModel,
            'temperature' => $options['temperature'] ?? 0.3,
            'max_tokens' => $options['max_tokens'] ?? $estimatedMaxTokens,
        ]));
    }

    /**
     * Generate an image with DALL-E 3.
     */
    public function generateImage(string $prompt, array $options = []): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'OpenAI API key not configured'];
        }

        if (!$this->checkBudget()) {
            return ['success' => false, 'error' => 'AI budget exceeded'];
        }

        $size = $options['size'] ?? '1792x1024';
        $quality = $options['quality'] ?? 'standard';

        $startTime = microtime(true);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout($this->timeout)->post('https://api.openai.com/v1/images/generations', [
                'model' => 'dall-e-3',
                'prompt' => $prompt,
                'size' => $size,
                'quality' => $quality,
                'n' => 1,
            ]);

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            if ($response->successful()) {
                $data = $response->json();
                $imageData = $data['data'][0] ?? [];

                $this->trackCost(
                    'dall-e-3',
                    0,
                    0,
                    $options['costable_type'] ?? null,
                    $options['costable_id'] ?? null
                );

                Log::info('OpenAI image OK', [
                    'size' => $size,
                    'quality' => $quality,
                    'duration_ms' => $durationMs,
                ]);

                return [
                    'success' => true,
                    'url' => $imageData['url'] ?? '',
                    'revised_prompt' => $imageData['revised_prompt'] ?? '',
                ];
            }

            Log::warning('OpenAI image error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'error' => 'HTTP ' . $response->status() . ': ' . $response->body(),
            ];
        } catch (\Throwable $e) {
            Log::error('OpenAI image exception', ['message' => $e->getMessage()]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Track API cost and store in database.
     */
    private function trackCost(string $model, int $inputTokens, int $outputTokens, ?string $costableType = null, ?int $costableId = null): void
    {
        try {
            $costCents = 0;

            if ($model === 'dall-e-3') {
                // Flat rate per image: $0.08 = 8 cents
                $costCents = 8;
            } elseif (isset(self::MODEL_PRICING[$model])) {
                $pricing = self::MODEL_PRICING[$model];
                $inputCost = ($inputTokens / 1_000_000) * $pricing['input'];
                $outputCost = ($outputTokens / 1_000_000) * $pricing['output'];
                // Convert dollars to cents
                $costCents = (int) ceil(($inputCost + $outputCost) * 100);
            } else {
                // Fallback: use gpt-4o pricing
                $pricing = self::MODEL_PRICING['gpt-4o'];
                $inputCost = ($inputTokens / 1_000_000) * $pricing['input'];
                $outputCost = ($outputTokens / 1_000_000) * $pricing['output'];
                $costCents = (int) ceil(($inputCost + $outputCost) * 100);
            }

            ApiCost::create([
                'service' => 'openai',
                'model' => $model,
                'operation' => $model === 'dall-e-3' ? 'image_generation' : 'chat_completion',
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'cost_cents' => $costCents,
                'costable_type' => $costableType,
                'costable_id' => $costableId,
            ]);

            Log::debug('OpenAI cost tracked', [
                'model' => $model,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'cost_cents' => $costCents,
            ]);
        } catch (\Throwable $e) {
            Log::error('OpenAI cost tracking failed', ['message' => $e->getMessage()]);
        }
    }

    /**
     * Check if we are within daily and monthly budgets.
     * Uses a distributed lock to prevent race conditions between concurrent requests.
     */
    private function checkBudget(): bool
    {
        $dailyBudget = (int) config('services.openai.daily_budget', 5000);
        $monthlyBudget = (int) config('services.openai.monthly_budget', 100000);
        $blockOnExceeded = config('services.openai.block_on_exceeded', true);

        // Use distributed lock to prevent race conditions
        $lockKey = 'ai_budget_check:' . now()->toDateString();
        $lock = \Illuminate\Support\Facades\Cache::lock($lockKey, 10);

        try {
            $lock->block(5); // Wait up to 5 seconds for lock

            $todayCost = \App\Models\ApiCost::whereDate('created_at', now()->toDateString())
                ->sum('cost_cents');

            $monthCost = \App\Models\ApiCost::whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->sum('cost_cents');

            $overDaily = $dailyBudget > 0 && $todayCost >= $dailyBudget;
            $overMonthly = $monthlyBudget > 0 && $monthCost >= $monthlyBudget;

            if ($overDaily || $overMonthly) {
                $reason = $overDaily ? 'daily' : 'monthly';
                Log::warning("AI budget exceeded ({$reason})", [
                    'daily_spent' => $todayCost,
                    'daily_budget' => $dailyBudget,
                    'monthly_spent' => $monthCost,
                    'monthly_budget' => $monthlyBudget,
                ]);

                if ($blockOnExceeded) {
                    return false;
                }
            }

            return true;
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            // Lock timeout - be safe, deny the call
            Log::error('Budget check lock timeout - denying API call');
            return false;
        } catch (\Throwable $e) {
            // Any other error - be safe, deny the call
            Log::error('Budget check failed - denying API call', [
                'error' => $e->getMessage(),
            ]);
            return false;
        } finally {
            optional($lock)->forceRelease();
        }
    }
}
