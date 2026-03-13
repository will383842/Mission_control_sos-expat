<?php

namespace App\Services;

use App\Models\Influenceur;
use App\Models\Reminder;
use Carbon\Carbon;

class ReminderService
{
    public function checkAll(): int
    {
        $created = 0;

        Influenceur::where('reminder_active', true)
            ->whereIn('status', ['contacted', 'negotiating'])
            ->whereNotNull('last_contact_at')
            ->whereDoesntHave('reminders', fn($q) => $q->where('status', 'pending'))
            ->get()
            ->each(function ($influenceur) use (&$created) {
                $daysSinceContact = Carbon::parse($influenceur->last_contact_at)
                    ->diffInDays(now());

                if ($daysSinceContact >= $influenceur->reminder_days) {
                    Reminder::create([
                        'influenceur_id' => $influenceur->id,
                        'due_date'       => now()->toDateString(),
                        'status'         => 'pending',
                    ]);
                    $created++;
                }
            });

        return $created;
    }
}
