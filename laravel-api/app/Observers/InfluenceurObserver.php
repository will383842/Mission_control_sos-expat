<?php

namespace App\Observers;

use App\Models\Influenceur;
use App\Services\BacklinkEngineWebhookService;

class InfluenceurObserver
{
    public function created(Influenceur $influenceur): void
    {
        if (! $influenceur->email) {
            return;
        }

        $type = $influenceur->contact_type instanceof \App\Enums\ContactType
            ? $influenceur->contact_type->value
            : (string) $influenceur->contact_type;

        if (! BacklinkEngineWebhookService::isSyncable($type)) {
            return;
        }

        $synced = BacklinkEngineWebhookService::sendContactCreated([
            'email' => $influenceur->email,
            'name' => $influenceur->name,
            'firstName' => $influenceur->first_name,
            'lastName' => $influenceur->last_name,
            'type' => $type,
            'publication' => $influenceur->company,
            'country' => $influenceur->country,
            'language' => $influenceur->language,
            'source_url' => $influenceur->website_url ?? $influenceur->profile_url,
            'source_table' => 'influenceurs',
            'source_id' => $influenceur->id,
        ]);

        if ($synced) {
            $influenceur->updateQuietly(['backlink_synced_at' => now()]);
        }
    }
}
