<?php

namespace App\Jobs;

use App\Http\Controllers\InfluenceurController;
use App\Models\AiResearchSession;
use App\Models\Influenceur;
use App\Services\AiPromptService;
use App\Services\ClaudeSearchService;
use App\Services\PerplexitySearchService;
use App\Services\ResultParserService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * AI Research Pipeline:
 *
 * STEP 1: Perplexity (the EYES) — searches the REAL web
 *   → 2 parallel searches: discovery + deep dive
 *   → Returns real URLs, real emails from actual web pages
 *   → With citations (source URLs)
 *
 * STEP 2: Claude (the BRAIN) — analyzes and structures
 *   → Receives raw Perplexity results
 *   → Cleans, structures into NOM/EMAIL/TEL/URL format
 *   → Scores reliability 1-5 for each contact
 *   → Filters irrelevant results
 *
 * STEP 3: Parser — deduplicates against existing database
 *
 * FALLBACK: If Perplexity not configured → Claude alone (with low reliability warning)
 */
class RunAiResearchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180; // 3 min (Perplexity + Claude)
    public int $tries = 2;

    public function __construct(
        private int $sessionId,
        private ?string $customPrompt = null,
    ) {}

    public function handle(
        AiPromptService $promptService,
        PerplexitySearchService $perplexityService,
        ClaudeSearchService $claudeService,
        ResultParserService $parserService,
    ): void {
        $session = AiResearchSession::find($this->sessionId);
        if (!$session || $session->status !== 'pending') return;

        $session->markRunning();

        try {
            $contactType = $session->contact_type instanceof \App\Enums\ContactType
                ? $session->contact_type->value
                : $session->contact_type;

            // 1. Collect existing URLs to exclude
            $existingDomains = Influenceur::where('contact_type', $contactType)
                ->where('country', $session->country)
                ->whereNotNull('profile_url_domain')
                ->pluck('profile_url_domain')
                ->toArray();

            $session->update(['excluded_domains' => $existingDomains]);

            // 2. Build the search prompt (use custom if provided)
            if ($this->customPrompt) {
                $prompt = $this->customPrompt;
            } else {
                $prompt = $promptService->buildPrompt(
                    $contactType,
                    $session->country,
                    $session->language,
                    $existingDomains
                );
            }

            $totalTokens = 0;
            $rawTexts = [];

            // ============================================================
            // STEP 1: Perplexity — Real web search
            // ============================================================
            if ($perplexityService->isConfigured()) {
                Log::info('AI Research: Using Perplexity + Claude pipeline', ['session' => $session->id]);

                // Two Perplexity searches in parallel
                $deepPrompt = $prompt . "\n\nCette deuxième recherche doit trouver des résultats COMPLÉMENTAIRES que la première aurait manqués. Cherche dans des sources différentes : annuaires d'expatriés, forums, groupes Facebook, blogs d'expats, pages jaunes locales, Google Maps. Visite les pages Contact de chaque site trouvé pour extraire emails et téléphones.";

                $perplexityResults = $perplexityService->searchParallel($prompt, $deepPrompt, $session->language);
                $totalTokens += $perplexityResults['tokens'];

                // Store raw Perplexity responses
                $session->update([
                    'perplexity_response' => $perplexityResults['responses']['discovery'] ?? '',
                    'tavily_response'     => $perplexityResults['responses']['deep'] ?? '',
                ]);

                // Merge both Perplexity responses
                $combinedPerplexity = trim(
                    ($perplexityResults['responses']['discovery'] ?? '') . "\n\n---\n\n" .
                    ($perplexityResults['responses']['deep'] ?? '')
                );

                // ============================================================
                // STEP 2: Claude — Analyze and structure Perplexity results
                // ============================================================
                if (!empty($combinedPerplexity)) {
                    $claudeResult = $claudeService->analyzeAndStructure(
                        $combinedPerplexity,
                        $contactType,
                        $session->country,
                        $perplexityResults['citations'] ?? []
                    );

                    if ($claudeResult['success']) {
                        $rawTexts[] = $claudeResult['text'];
                        $totalTokens += $claudeResult['tokens'];
                    }

                    $session->update([
                        'claude_response' => $claudeResult['text'] ?? '',
                    ]);
                }
            } else {
                // ============================================================
                // FALLBACK: Claude alone (no Perplexity)
                // ============================================================
                Log::info('AI Research: Perplexity not configured, using Claude fallback', ['session' => $session->id]);

                $claudeResult = $claudeService->searchAlone($prompt);
                if ($claudeResult['success']) {
                    $rawTexts[] = $claudeResult['text'];
                    $totalTokens += $claudeResult['tokens'];
                }

                $session->update([
                    'claude_response'     => $claudeResult['text'] ?? '',
                    'perplexity_response' => '[NOT CONFIGURED] Perplexity API key missing. Using Claude fallback with low reliability.',
                ]);
            }

            // ============================================================
            // STEP 3: Parse + Deduplicate
            // ============================================================
            $parsedContacts = $parserService->parseAndMerge($rawTexts, $contactType, $session->country);
            $deduped = $parserService->checkDuplicates($parsedContacts);

            // Add normalized profile_url_domain
            foreach ($deduped['new'] as &$contact) {
                if (!empty($contact['profile_url'])) {
                    $contact['profile_url_domain'] = InfluenceurController::normalizeProfileUrl($contact['profile_url']);
                }
            }

            // ============================================================
            // STEP 4: AUTO-IMPORT all new contacts into influenceurs table
            // Checks: name+country duplicate, email duplicate, URL duplicate
            // ============================================================
            $imported = 0;
            $skippedDuplicates = 0;
            foreach ($deduped['new'] as $contact) {
                try {
                    // Check duplicate by name + country (case-insensitive)
                    $nameExists = Influenceur::whereRaw('LOWER(name) = ?', [strtolower($contact['name'])])
                        ->where('country', $session->country)
                        ->exists();
                    if ($nameExists) {
                        $skippedDuplicates++;
                        Log::debug('Auto-import: skipped name duplicate', ['name' => $contact['name']]);
                        continue;
                    }

                    // Check duplicate by profile_url_domain (if we have one)
                    if (!empty($contact['profile_url_domain'])) {
                        $urlExists = Influenceur::where('profile_url_domain', $contact['profile_url_domain'])->exists();
                        if ($urlExists) {
                            $skippedDuplicates++;
                            continue;
                        }
                    }

                    // Check duplicate by email
                    if (!empty($contact['email'])) {
                        $emailExists = Influenceur::where('email', strtolower($contact['email']))->exists();
                        if ($emailExists) {
                            $skippedDuplicates++;
                            continue;
                        }
                    }

                    // For non-social contact types, the URL is a website, not a social profile
                    // Store it in BOTH profile_url (for duplicate detection) and website_url (for scraper)
                    $websiteUrl = null;
                    $nonSocialTypes = Influenceur::NON_SOCIAL_TYPES;
                    $effectiveType = $contact['contact_type'] ?? $contactType;
                    if (in_array($effectiveType, $nonSocialTypes) && !empty($contact['profile_url'])) {
                        $websiteUrl = $contact['profile_url'];
                    }

                    Influenceur::create([
                        'contact_type'       => $effectiveType,
                        'name'               => $contact['name'],
                        'email'              => $contact['email'] ?? null,
                        'phone'              => $contact['phone'] ?? null,
                        'profile_url'        => $contact['profile_url'] ?? null,
                        'profile_url_domain' => $contact['profile_url_domain'] ?? null,
                        'website_url'        => $websiteUrl,
                        'country'            => $contact['country'] ?? $session->country,
                        'language'           => $session->language,
                        'platforms'          => $contact['platforms'] ?? [],
                        'primary_platform'   => $contact['platforms'][0] ?? 'website',
                        'followers'          => $contact['followers'] ?? null,
                        'notes'              => $contact['notes'] ?? null,
                        'source'             => 'ai_research',
                        'status'             => 'new',
                        'score'              => ($contact['reliability_score'] ?? 1) * 20,
                        'created_by'         => $session->user_id,
                    ]);
                    $imported++;
                } catch (\Throwable $e) {
                    Log::warning('Auto-import contact failed', [
                        'name'  => $contact['name'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // ============================================================
            // DONE — Save results
            // ============================================================
            $session->update([
                'status'              => 'completed',
                'completed_at'        => now(),
                'parsed_contacts'     => $deduped['new'],
                'contacts_found'      => count($parsedContacts),
                'contacts_imported'   => $imported,
                'contacts_duplicates' => count($deduped['duplicates']),
                'tokens_used'         => $totalTokens,
                'cost_cents'          => $this->estimateCost($totalTokens, $perplexityService->isConfigured()),
            ]);

            Log::info('AI Research completed + auto-imported', [
                'session_id'   => $session->id,
                'pipeline'     => $perplexityService->isConfigured() ? 'perplexity+claude' : 'claude-only',
                'found'        => count($parsedContacts),
                'new'          => count($deduped['new']),
                'imported'     => $imported,
                'duplicates'   => count($deduped['duplicates']),
                'tokens'       => $totalTokens,
            ]);

        } catch (\Throwable $e) {
            $session->markFailed($e->getMessage());
            Log::error('AI Research failed', [
                'session_id' => $session->id,
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Estimate API cost in cents.
     * Perplexity sonar: ~$1/1000 requests ($0.001 each)
     * Claude Sonnet: ~$3/M input + $15/M output
     */
    private function estimateCost(int $tokens, bool $usedPerplexity): int
    {
        $claudeCost = (int) round($tokens * 0.009); // ~$9/M tokens
        $perplexityCost = $usedPerplexity ? 2 : 0;   // ~$0.02 for 2 requests
        return $claudeCost + $perplexityCost;
    }
}
