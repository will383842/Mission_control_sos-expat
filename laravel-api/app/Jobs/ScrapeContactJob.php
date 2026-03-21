<?php

namespace App\Jobs;

use App\Models\ActivityLog;
use App\Models\ContactTypeModel;
use App\Models\Influenceur;
use App\Models\Setting;
use App\Services\WebScraperService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ScrapeContactJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;
    public int $tries = 1; // Don't retry failed scrapes

    public function __construct(
        private int $influenceurId,
    ) {
        $this->onQueue('scraper');
    }

    public function handle(WebScraperService $scraper): void
    {
        $influenceur = Influenceur::find($this->influenceurId);
        if (!$influenceur) {
            Log::warning('ScrapeContactJob: influenceur not found', ['id' => $this->influenceurId]);
            return;
        }

        // Double-check global toggle (may have changed since dispatch)
        if (!Setting::getBool('scraper_enabled')) {
            $this->markStatus($influenceur, 'skipped');
            Log::debug('ScrapeContactJob: global scraper disabled, skipping', ['id' => $influenceur->id]);
            return;
        }

        // Double-check per-type toggle
        $contactType = $influenceur->contact_type instanceof \App\Enums\ContactType
            ? $influenceur->contact_type->value
            : $influenceur->contact_type;

        $typeModel = ContactTypeModel::where('value', $contactType)->first();
        if ($typeModel && !$typeModel->scraper_enabled) {
            $this->markStatus($influenceur, 'skipped');
            Log::debug('ScrapeContactJob: type scraper disabled', [
                'id'   => $influenceur->id,
                'type' => $contactType,
            ]);
            return;
        }

        // Determine which URL to scrape
        $url = $influenceur->website_url ?: $influenceur->profile_url;
        if (empty($url)) {
            $this->markStatus($influenceur, 'skipped');
            return;
        }

        // Ensure URL has a scheme
        if (!preg_match('/^https?:\/\//i', $url)) {
            $url = 'https://' . $url;
        }

        Log::info('ScrapeContactJob: starting', ['id' => $influenceur->id, 'url' => $url]);

        try {
            $result = $scraper->scrape($url);

            // Update the influenceur with scraped data
            $updateData = [
                'scraped_at'      => now(),
                'scraper_status'  => $result['success'] ? 'completed' : 'failed',
                'scraped_emails'  => $result['emails'] ?: null,
                'scraped_phones'  => $result['phones'] ?: null,
                'scraped_social'    => $result['social_links'] ?: null,
                'scraped_addresses' => $result['addresses'] ?: null,
            ];

            // Only fill NULL/empty fields — NEVER overwrite existing data
            if (empty($influenceur->email) && !empty($result['emails'])) {
                $updateData['email'] = $result['emails'][0]; // Use first found email
            }

            if (empty($influenceur->phone) && !empty($result['phones'])) {
                $updateData['phone'] = $result['phones'][0]; // Use first found phone
            }

            $influenceur->update($updateData);

            // Log the activity
            $details = [
                'url'           => $url,
                'pages_scraped' => count($result['scraped_pages']),
                'emails_found'  => count($result['emails']),
                'phones_found'  => count($result['phones']),
                'social_found'    => count($result['social_links']),
                'addresses_found' => count($result['addresses'] ?? []),
                'success'       => $result['success'],
            ];

            if ($result['error']) {
                $details['error'] = $result['error'];
            }

            // Track what was actually filled in
            if (empty($influenceur->getOriginal('email')) && !empty($result['emails'])) {
                $details['email_filled'] = $result['emails'][0];
            }
            if (empty($influenceur->getOriginal('phone')) && !empty($result['phones'])) {
                $details['phone_filled'] = $result['phones'][0];
            }

            ActivityLog::create([
                'user_id'        => $influenceur->created_by, // Use contact's creator as fallback
                'influenceur_id' => $influenceur->id,
                'action'         => 'scraper_completed',
                'details'        => $details,
                'contact_type'   => $contactType,
            ]);

            Log::info('ScrapeContactJob: completed', [
                'id'            => $influenceur->id,
                'emails_found'  => count($result['emails']),
                'phones_found'  => count($result['phones']),
                'success'       => $result['success'],
            ]);

        } catch (\Throwable $e) {
            $this->markStatus($influenceur, 'failed');

            ActivityLog::create([
                'user_id'        => $influenceur->created_by,
                'influenceur_id' => $influenceur->id,
                'action'         => 'scraper_failed',
                'details'        => [
                    'url'   => $url,
                    'error' => substr($e->getMessage(), 0, 500),
                ],
                'contact_type' => $contactType,
            ]);

            Log::error('ScrapeContactJob: exception', [
                'id'    => $influenceur->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Update scraper status and timestamp without overwriting other fields.
     */
    private function markStatus(Influenceur $influenceur, string $status): void
    {
        $influenceur->update([
            'scraped_at'     => now(),
            'scraper_status' => $status,
        ]);
    }
}
