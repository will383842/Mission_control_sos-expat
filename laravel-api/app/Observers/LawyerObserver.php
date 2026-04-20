<?php

namespace App\Observers;

use App\Models\Lawyer;
use App\Services\BacklinkEngineWebhookService;

/**
 * Envoie les avocats scrapés au Backlink Engine.
 *
 * Utilise `saved()` (pas `created()`) parce que LawyerDirectoryScraperService
 * utilise `updateOrCreate()` qui ne déclenche `created` qu'en cas d'INSERT.
 * On veut aussi push les UPDATE quand l'email/website/country change.
 *
 * Dédup via `backlink_synced_at` : un contact déjà synchronisé n'est pas
 * renvoyé sauf si un champ "significatif" a changé.
 */
class LawyerObserver
{
    public function saved(Lawyer $lawyer): void
    {
        if (!$lawyer->email) {
            return;
        }

        // Skip les updates "insignifiants" (ex : `scraped_at` seulement)
        $significantFields = ['email', 'website', 'country', 'full_name', 'phone'];
        $isNew = $lawyer->wasRecentlyCreated;
        $hasSignificantChange = $lawyer->wasChanged($significantFields);

        if (!$isNew && !$hasSignificantChange) {
            return;
        }

        // Déjà synchronisé récemment et aucun changement → skip
        if (!$isNew && !$hasSignificantChange && $lawyer->backlink_synced_at) {
            return;
        }

        $synced = BacklinkEngineWebhookService::sendContactCreated([
            'email'        => $lawyer->email,
            'name'         => $lawyer->full_name,
            'firstName'    => $lawyer->first_name,
            'lastName'     => $lawyer->last_name,
            'type'         => 'avocat',
            'publication'  => $lawyer->firm_name,
            'country'      => $lawyer->country,
            'language'     => $lawyer->language,
            'source_url'   => $lawyer->website ?? $lawyer->source_url,
            'source_table' => 'lawyers',
            'source_id'    => $lawyer->id,
        ]);

        if ($synced) {
            $lawyer->updateQuietly(['backlink_synced_at' => now()]);
        }
    }
}
