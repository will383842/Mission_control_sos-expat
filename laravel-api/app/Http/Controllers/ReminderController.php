<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Reminder;
use Illuminate\Http\Request;

class ReminderController extends Controller
{
    public function index(Request $request)
    {
        $query = Reminder::with([
            'influenceur:id,name,status,last_contact_at,primary_platform,created_by',
            'influenceur.assignedToUser:id,name',
        ])
            ->where('status', 'pending')
            ->orderBy('due_date');

        // Researcher scoping: only reminders for own influenceurs
        if ($request->user()->isResearcher()) {
            $query->whereHas('influenceur', function ($q) use ($request) {
                $q->where('created_by', $request->user()->id);
            });
        }

        $reminders = $query->get()
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
        ]);

        return response()->json($reminder);
    }

    public function done(Request $request, Reminder $reminder)
    {
        // Reuses dismissed_by/dismissed_at fields to record who completed the reminder and when,
        // avoiding a separate migration for completed_by/completed_at columns.
        $reminder->update([
            'status'       => 'done',
            'dismissed_by' => $request->user()->id,
            'dismissed_at' => now(),
        ]);

        ActivityLog::create([
            'user_id'        => $request->user()->id,
            'influenceur_id' => $reminder->influenceur_id,
            'action'         => 'reminder_done',
        ]);

        return response()->json($reminder);
    }
}
