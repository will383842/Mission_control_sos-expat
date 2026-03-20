<?php

namespace App\Observers;

use App\Models\Contact;
use App\Models\Influenceur;
use App\Models\Reminder;

class ContactObserver
{
    public function created(Contact $contact): void
    {
        $this->recalcLastContact($contact->influenceur_id);

        // Fermer tous les rappels pending quand un contact est ajouté
        Reminder::where('influenceur_id', $contact->influenceur_id)
            ->where('status', 'pending')
            ->update([
                'status'       => 'done',
                'dismissed_at' => now(),
            ]);
    }

    public function updated(Contact $contact): void
    {
        if ($contact->isDirty('date')) {
            $this->recalcLastContact($contact->influenceur_id);
        }
    }

    public function deleted(Contact $contact): void
    {
        $this->recalcLastContact($contact->influenceur_id);
    }

    private function recalcLastContact(int $influenceurId): void
    {
        $maxDate = Contact::where('influenceur_id', $influenceurId)
            ->whereNull('deleted_at')
            ->max('date');

        Influenceur::where('id', $influenceurId)
            ->update(['last_contact_at' => $maxDate]);
    }
}
