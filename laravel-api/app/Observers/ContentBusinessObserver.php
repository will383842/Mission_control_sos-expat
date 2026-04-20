<?php

namespace App\Observers;

use App\Models\ContentBusiness;
use App\Services\BacklinkEngineWebhookService;

/**
 * Envoie les entreprises scrapées (expat.com, etc.) au Backlink Engine.
 *
 * Typé comme 'partenaire' côté engine (type B2B généraliste).
 * Utilise `saved()` (ScrapeBusinessDirectoryJob fait `updateOrCreate()`).
 */
class ContentBusinessObserver
{
    public function saved(ContentBusiness $business): void
    {
        // ContentBusiness utilise `contact_email` (pas `email` comme les autres modèles)
        $email = $business->contact_email ?: null;
        if (!$email) {
            return;
        }

        $significantFields = ['contact_email', 'website', 'name', 'country'];
        $isNew = $business->wasRecentlyCreated;
        $hasSignificantChange = $business->wasChanged($significantFields);

        if (!$isNew && !$hasSignificantChange) {
            return;
        }

        $synced = BacklinkEngineWebhookService::sendContactCreated([
            'email'        => $email,
            'name'         => $business->contact_name ?: $business->name,
            'type'         => 'partenaire',
            'publication'  => $business->name,
            'country'      => $business->country,
            'language'     => $business->language,
            'source_url'   => $business->website ?? $business->url,
            'source_table' => 'content_businesses',
            'source_id'    => $business->id,
        ]);

        if ($synced) {
            $business->updateQuietly(['backlink_synced_at' => now()]);
        }
    }
}
