<?php

namespace App\Jobs;

use App\Http\Controllers\InfluenceurController;
use App\Models\ActivityLog;
use App\Models\AiResearchSession;
use App\Models\AutoCampaign;
use App\Models\AutoCampaignTask;
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
 * Orchestrator job: runs every minute via scheduler.
 *
 * Picks one task from the active campaign, executes AI research,
 * records results, and manages retries + circuit breaker.
 *
 * Rate limiting strategy:
 * - 1 AI research per N seconds (configurable, default 5 min)
 * - Exponential backoff on retries (10min, 20min, 40min)
 * - Circuit breaker: auto-pause after N consecutive failures
 * - DuckDuckGo: only used during scraping phase (separate queue)
 */
class ProcessAutoCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 240; // 4 min (Perplexity + Claude + parsing)
    public int $tries = 1;     // Don't retry the orchestrator itself

    public function handle(
        AiPromptService $promptService,
        PerplexitySearchService $perplexityService,
        ClaudeSearchService $claudeService,
        ResultParserService $parserService,
    ): void {
        // Find the active running campaign
        $campaign = AutoCampaign::running()->first();
        if (!$campaign) {
            return; // Nothing to do
        }

        // Check rate limit
        if (!$campaign->isReadyForNextTask()) {
            Log::debug('AutoCampaign: not ready for next task (rate limit or circuit breaker)', [
                'campaign_id'          => $campaign->id,
                'consecutive_failures' => $campaign->consecutive_failures,
                'last_task_at'         => $campaign->last_task_at?->toIso8601String(),
            ]);
            return;
        }

        // Pick next task: pending first, then failed eligible for retry
        $task = $campaign->tasks()
            ->readyToProcess()
            ->orderBy('priority')
            ->orderBy('id')
            ->first();

        if (!$task) {
            // No more tasks to process — check if campaign is done
            $campaign->checkCompletion();

            // Log completion
            if ($campaign->status === 'completed') {
                $this->logCampaignComplete($campaign);
            }
            return;
        }

        // ============================================================
        // Execute the AI research for this task
        // ============================================================
        $task->markRunning();

        Log::info('AutoCampaign: processing task', [
            'campaign_id'  => $campaign->id,
            'task_id'      => $task->id,
            'type'         => $task->contact_type,
            'country'      => $task->country,
            'language'     => $task->language,
            'attempt'      => $task->attempt,
        ]);

        try {
            $result = $this->executeResearch(
                $task, $campaign,
                $promptService, $perplexityService, $claudeService, $parserService
            );

            // Record success
            $task->markCompleted(
                $result['contacts_found'],
                $result['contacts_imported'],
                $result['session_id']
            );

            $campaign->recordTaskSuccess(
                $result['contacts_found'],
                $result['contacts_imported'],
                $result['cost_cents']
            );

            // Alert if nothing found (task is now completed, won't be retried)
            if ($result['contacts_found'] === 0) {
                $this->logAlert($campaign, $task, 'no_results',
                    "Aucun contact trouvé pour {$task->contact_type} / {$task->country} (tentative {$task->attempt})"
                );
            }

            Log::info('AutoCampaign: task completed', [
                'task_id'  => $task->id,
                'found'    => $result['contacts_found'],
                'imported' => $result['contacts_imported'],
            ]);

        } catch (\Throwable $e) {
            $task->markFailed($e->getMessage(), $campaign->max_retries);
            $campaign->recordTaskFailure();

            Log::error('AutoCampaign: task failed', [
                'task_id' => $task->id,
                'attempt' => $task->attempt,
                'error'   => mb_substr($e->getMessage(), 0, 500),
            ]);

            // Alert if circuit breaker triggered
            if ($campaign->status === 'paused') {
                $this->logAlert($campaign, $task, 'circuit_breaker',
                    "Campagne en pause automatique après {$campaign->consecutive_failures} échecs consécutifs. Dernière erreur: " . mb_substr($e->getMessage(), 0, 200)
                );
            }

            // Alert if max retries exhausted
            if (!$task->canRetry($campaign->max_retries)) {
                $this->logAlert($campaign, $task, 'max_retries',
                    "Échec définitif pour {$task->contact_type} / {$task->country} après {$task->attempt} tentatives: " . mb_substr($e->getMessage(), 0, 200)
                );
            }
        }

        // Check if all tasks are done
        $campaign->checkCompletion();
        if ($campaign->status === 'completed') {
            $this->logCampaignComplete($campaign);
        }
    }

    /**
     * Execute the full AI research pipeline for one task.
     * Mirrors RunAiResearchJob logic but synchronously (within this job).
     */
    private function executeResearch(
        AutoCampaignTask $task,
        AutoCampaign $campaign,
        AiPromptService $promptService,
        PerplexitySearchService $perplexityService,
        ClaudeSearchService $claudeService,
        ResultParserService $parserService,
    ): array {
        $contactType = $task->contact_type; // Always a string from auto_campaign_tasks
        $country = $task->country;
        $language = $task->language;

        // Create an AI research session for tracking
        // Use enum for AiResearchSession which casts contact_type to ContactType enum
        $session = AiResearchSession::create([
            'user_id'      => $campaign->created_by,
            'contact_type' => \App\Enums\ContactType::tryFrom($contactType)?->value ?? $contactType,
            'country'      => $country,
            'language'     => $language,
            'status'       => 'pending',
        ]);
        $session->markRunning();

        // Collect existing URLs to exclude duplicates
        $existingDomains = Influenceur::where('contact_type', $contactType)
            ->where('country', $country)
            ->whereNotNull('profile_url_domain')
            ->pluck('profile_url_domain')
            ->toArray();

        $session->update(['excluded_domains' => $existingDomains]);

        // Build the search prompt
        $prompt = $promptService->buildPrompt($contactType, $country, $language, $existingDomains);

        $totalTokens = 0;
        $rawTexts = [];

        // ============================================================
        // STEP 1: Perplexity — Real web search
        // ============================================================
        if ($perplexityService->isConfigured()) {
            $deepPrompt = $prompt . "\n\nCette deuxième recherche doit trouver des résultats COMPLÉMENTAIRES que la première aurait manqués. Cherche dans des sources différentes : annuaires d'expatriés, forums, groupes Facebook, blogs d'expats, pages jaunes locales, Google Maps. Visite les pages Contact de chaque site trouvé pour extraire emails et téléphones.";

            $perplexityResults = $perplexityService->searchParallel($prompt, $deepPrompt, $language);
            $totalTokens += $perplexityResults['tokens'];

            $session->update([
                'perplexity_response' => $perplexityResults['responses']['discovery'] ?? '',
                'tavily_response'     => $perplexityResults['responses']['deep'] ?? '',
            ]);

            $combinedPerplexity = trim(
                ($perplexityResults['responses']['discovery'] ?? '') . "\n\n---\n\n" .
                ($perplexityResults['responses']['deep'] ?? '')
            );

            // ============================================================
            // STEP 2: Claude — Analyze and structure
            // ============================================================
            if (!empty($combinedPerplexity)) {
                $claudeResult = $claudeService->analyzeAndStructure(
                    $combinedPerplexity, $contactType, $country,
                    $perplexityResults['citations'] ?? []
                );

                if ($claudeResult['success']) {
                    $rawTexts[] = $claudeResult['text'];
                    $totalTokens += $claudeResult['tokens'];
                }

                $session->update(['claude_response' => $claudeResult['text'] ?? '']);
            }
        } else {
            // Fallback: Claude alone
            $claudeResult = $claudeService->searchAlone($prompt);
            if ($claudeResult['success']) {
                $rawTexts[] = $claudeResult['text'];
                $totalTokens += $claudeResult['tokens'];
            }

            $session->update([
                'claude_response'     => $claudeResult['text'] ?? '',
                'perplexity_response' => '[AUTO-CAMPAIGN] Perplexity not configured.',
            ]);
        }

        // ============================================================
        // STEP 3: Parse + Deduplicate
        // ============================================================
        $parsedContacts = $parserService->parseAndMerge($rawTexts, $contactType, $country);
        $deduped = $parserService->checkDuplicates($parsedContacts);

        // Add normalized profile_url_domain
        foreach ($deduped['new'] as &$contact) {
            if (!empty($contact['profile_url'])) {
                $contact['profile_url_domain'] = InfluenceurController::normalizeProfileUrl($contact['profile_url']);
            }
        }

        // ============================================================
        // STEP 4: Auto-import
        // ============================================================
        $imported = 0;
        $nonSocialTypes = Influenceur::NON_SOCIAL_TYPES;

        foreach ($deduped['new'] as $contact) {
            try {
                // Duplicate checks
                $nameExists = Influenceur::whereRaw('LOWER(name) = ?', [strtolower($contact['name'])])
                    ->where('country', $country)
                    ->exists();
                if ($nameExists) continue;

                if (!empty($contact['profile_url_domain'])) {
                    if (Influenceur::where('profile_url_domain', $contact['profile_url_domain'])->exists()) continue;
                }
                if (!empty($contact['email'])) {
                    if (Influenceur::where('email', strtolower($contact['email']))->exists()) continue;
                }

                // Website URL for non-social types
                $websiteUrl = null;
                if (in_array($contactType, $nonSocialTypes) && !empty($contact['profile_url'])) {
                    $websiteUrl = $contact['profile_url'];
                }

                Influenceur::create([
                    'contact_type'       => $contact['contact_type'] ?? $contactType,
                    'name'               => $contact['name'],
                    'email'              => $contact['email'] ?? null,
                    'phone'              => $contact['phone'] ?? null,
                    'profile_url'        => $contact['profile_url'] ?? null,
                    'profile_url_domain' => $contact['profile_url_domain'] ?? null,
                    'website_url'        => $websiteUrl,
                    'country'            => $contact['country'] ?? $country,
                    'language'           => $language,
                    'platforms'          => $contact['platforms'] ?? [],
                    'primary_platform'   => $contact['platforms'][0] ?? 'website',
                    'followers'          => $contact['followers'] ?? null,
                    'notes'              => $contact['notes'] ?? null,
                    'source'             => 'auto_campaign',
                    'status'             => 'new',
                    'score'              => ($contact['reliability_score'] ?? 1) * 20,
                    'created_by'         => $campaign->created_by,
                ]);
                $imported++;
            } catch (\Throwable $e) {
                Log::debug('AutoCampaign: import failed', ['name' => $contact['name'] ?? '?', 'error' => $e->getMessage()]);
            }
        }

        // Finalize session
        $costCents = $this->estimateCost($totalTokens, $perplexityService->isConfigured());
        $session->update([
            'status'              => 'completed',
            'completed_at'        => now(),
            'parsed_contacts'     => $deduped['new'],
            'contacts_found'      => count($parsedContacts),
            'contacts_imported'   => $imported,
            'contacts_duplicates' => count($deduped['duplicates']),
            'tokens_used'         => $totalTokens,
            'cost_cents'          => $costCents,
        ]);

        return [
            'contacts_found'    => count($parsedContacts),
            'contacts_imported' => $imported,
            'cost_cents'        => $costCents,
            'session_id'        => $session->id,
        ];
    }

    /**
     * Log an alert to the activity log.
     */
    private function logAlert(AutoCampaign $campaign, AutoCampaignTask $task, string $type, string $message): void
    {
        ActivityLog::create([
            'user_id'      => $campaign->created_by,
            'action'        => 'auto_campaign_alert',
            'contact_type'  => $task->contact_type,
            'details'       => [
                'campaign_id'   => $campaign->id,
                'campaign_name' => $campaign->name,
                'task_id'       => $task->id,
                'alert_type'    => $type,
                'country'       => $task->country,
                'language'      => $task->language,
                'message'       => $message,
            ],
        ]);
    }

    /**
     * Log campaign completion.
     */
    private function logCampaignComplete(AutoCampaign $campaign): void
    {
        ActivityLog::create([
            'user_id' => $campaign->created_by,
            'action'   => 'auto_campaign_completed',
            'details'  => [
                'campaign_id'      => $campaign->id,
                'campaign_name'    => $campaign->name,
                'tasks_completed'  => $campaign->tasks_completed,
                'tasks_failed'     => $campaign->tasks_failed,
                'tasks_skipped'    => $campaign->tasks_skipped,
                'contacts_found'   => $campaign->contacts_found_total,
                'contacts_imported' => $campaign->contacts_imported_total,
                'total_cost_cents' => $campaign->total_cost_cents,
                'duration_minutes' => $campaign->started_at
                    ? (int) $campaign->started_at->diffInMinutes(now())
                    : null,
            ],
        ]);

        Log::info('AutoCampaign: COMPLETED', [
            'campaign_id' => $campaign->id,
            'name'        => $campaign->name,
            'imported'    => $campaign->contacts_imported_total,
            'cost_cents'  => $campaign->total_cost_cents,
        ]);
    }

    private function estimateCost(int $tokens, bool $usedPerplexity): int
    {
        $claudeCost = (int) round($tokens * 0.009);
        $perplexityCost = $usedPerplexity ? 2 : 0;
        return $claudeCost + $perplexityCost;
    }
}
