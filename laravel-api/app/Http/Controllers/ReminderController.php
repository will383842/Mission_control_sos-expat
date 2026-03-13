<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Reminder;
use Illuminate\Http\Request;

class ReminderController extends Controller
{
    public function index()
    {
        $reminders = Reminder::with([
            'influenceur:id,name,status,last_contact_at,primary_platform',
            'influenceur.assignedToUser:id,name',
        ])
            ->where('status', 'pending')
            ->orderBy('due_date')
            ->get()
            ->map(function ($reminder) {
                $daysElapsed = $reminder->influenceur?->last_contact_at
                    ? (int) now()->diffInDays($reminder->influenceur->last_contact_at)
                    : null;
                return array_merge($reminder->toArray(), ['days_elapsed' => $daysElapsed]);
            });

        return response()->json($reminders);
    }

    public function dismiss(Request $request, Reminder $reminder)
    {
        $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        $reminder->update([
            'status'       => 'dismissed',
            'dismissed_by' => $request->user()->id,
            'dismissed_at' => now(),
            'notes'        => $request->notes,
        ]);

        ActivityLog::create([
            'user_id'        => $request->user()->id,
            'influenceur_id' => $reminder->influenceur_id,
            'action'         => 'reminder_dismissed',
            'details'        => ['notes' => $request->notes],
            'created_at'     => now(),
        ]);

        return response()->json($reminder);
    }

    public function done(Request $request, Reminder $reminder)
    {
        $reminder->update([
            'status'       => 'done',
            'dismissed_by' => $request->user()->id,
            'dismissed_at' => now(),
        ]);

        ActivityLog::create([
            'user_id'        => $request->user()->id,
            'influenceur_id' => $reminder->influenceur_id,
            'action'         => 'reminder_done',
            'created_at'     => now(),
        ]);

        return response()->json($reminder);
    }
}
