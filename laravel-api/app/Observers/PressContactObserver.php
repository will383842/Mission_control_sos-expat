<?php

namespace App\Observers;

use App\Models\PressContact;
use App\Services\BacklinkEngineWebhookService;

class PressContactObserver
{
    public function created(PressContact $contact): void
    {
        if (! $contact->email) {
            return;
        }

        // PressContacts are always type "presse" which is syncable
        BacklinkEngineWebhookService::sendContactCreated([
            'email' => $contact->email,
            'firstName' => $contact->first_name,
            'lastName' => $contact->last_name,
            'name' => $contact->full_name,
            'type' => 'presse',
            'publication' => $contact->publication,
            'country' => $contact->country,
            'language' => $contact->language,
            'source_url' => $contact->source_url ?? $contact->profile_url,
            'source_table' => 'press_contacts',
            'source_id' => $contact->id,
        ]);
    }
}
