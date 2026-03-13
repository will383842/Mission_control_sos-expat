<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Influenceur;
use Illuminate\Http\Request;

class InfluenceurController extends Controller
{
    public function index(Request $request)
    {
        $query = Influenceur::with(['assignedToUser:id,name', 'pendingReminder']);

        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->platform) {
            $query->whereJsonContains('platforms', $request->platform);
        }
        if ($request->assigned_to) {
            $query->where('assigned_to', $request->assigned_to);
        }
        if ($request->has_reminder) {
            $query->whereHas('pendingReminder');
        }
        if ($request->search) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('handle', 'like', "%{$s}%");
            });
        }

        $perPage = min((int) ($request->per_page ?? 30), 100);
        $results = $query->orderBy('id')
            ->cursorPaginate($perPage, ['*'], 'cursor', $request->cursor);

        return response()->json([
            'data'        => $results->items(),
            'next_cursor' => $results->nextCursor()?->encode(),
            'has_more'    => $results->hasMorePages(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'                 => 'required|string|max:255',
            'handle'               => 'nullable|string|max:255',
            'avatar_url'           => 'nullable|string|max:500',
            'platforms'            => 'required|array',
            'primary_platform'     => 'required|string|max:50',
            'followers'            => 'nullable|integer|min:0',
            'followers_secondary'  => 'nullable|array',
            'niche'                => 'nullable|string|max:255',
            'country'              => 'nullable|string|max:100',
            'language'             => 'nullable|string|max:10',
            'email'                => 'nullable|email',
            'phone'                => 'nullable|string|max:50',
            'profile_url'          => 'nullable|string|max:500',
            'status'               => 'sometimes|in:prospect,contacted,negotiating,active,refused,inactive',
            'assigned_to'          => 'nullable|exists:users,id',
            'reminder_days'        => 'sometimes|integer|min:1|max:365',
            'notes'                => 'nullable|string',
            'tags'                 => 'nullable|array',
        ]);

        $data['created_by'] = $request->user()->id;

        if (($data['status'] ?? 'prospect') === 'active') {
            $data['partnership_date'] = $data['partnership_date'] ?? now()->toDateString();
        }

        $influenceur = Influenceur::create($data);

        ActivityLog::create([
            'user_id'         => $request->user()->id,
            'influenceur_id'  => $influenceur->id,
            'action'          => 'created',
            'details'         => ['name' => $influenceur->name],
            'created_at'      => now(),
        ]);

        return response()->json($influenceur->load('assignedToUser:id,name'), 201);
    }

    public function show(Influenceur $influenceur)
    {
        return response()->json($influenceur->load([
            'assignedToUser:id,name',
            'createdBy:id,name',
            'contacts.user:id,name',
            'pendingReminder',
        ]));
    }

    public function update(Request $request, Influenceur $influenceur)
    {
        $data = $request->validate([
            'name'                => 'sometimes|string|max:255',
            'handle'              => 'nullable|string|max:255',
            'avatar_url'          => 'nullable|string|max:500',
            'platforms'           => 'sometimes|array',
            'primary_platform'    => 'sometimes|string|max:50',
            'followers'           => 'nullable|integer|min:0',
            'followers_secondary' => 'nullable|array',
            'niche'               => 'nullable|string|max:255',
            'country'             => 'nullable|string|max:100',
            'language'            => 'nullable|string|max:10',
            'email'               => 'nullable|email',
            'phone'               => 'nullable|string|max:50',
            'profile_url'         => 'nullable|string|max:500',
            'status'              => 'sometimes|in:prospect,contacted,negotiating,active,refused,inactive',
            'assigned_to'         => 'nullable|exists:users,id',
            'reminder_days'       => 'sometimes|integer|min:1|max:365',
            'reminder_active'     => 'sometimes|boolean',
            'notes'               => 'nullable|string',
            'tags'                => 'nullable|array',
        ]);

        $oldStatus = $influenceur->status;

        if (isset($data['status']) && $data['status'] === 'active'
            && $oldStatus !== 'active'
            && !$influenceur->partnership_date) {
            $data['partnership_date'] = now()->toDateString();
        }

        $influenceur->update($data);

        $action = (isset($data['status']) && $data['status'] !== $oldStatus)
            ? 'status_changed'
            : 'updated';

        ActivityLog::create([
            'user_id'        => $request->user()->id,
            'influenceur_id' => $influenceur->id,
            'action'         => $action,
            'details'        => $data,
            'created_at'     => now(),
        ]);

        return response()->json($influenceur->load('assignedToUser:id,name'));
    }

    public function destroy(Request $request, Influenceur $influenceur)
    {
        ActivityLog::create([
            'user_id'        => $request->user()->id,
            'influenceur_id' => null,
            'action'         => 'deleted',
            'details'        => ['name' => $influenceur->name, 'id' => $influenceur->id],
            'created_at'     => now(),
        ]);

        $influenceur->delete();

        return response()->json(null, 204);
    }

    public function remindersPending()
    {
        $influenceurs = Influenceur::with(['pendingReminder', 'assignedToUser:id,name'])
            ->whereHas('pendingReminder')
            ->get()
            ->map(function ($inf) {
                $daysElapsed = $inf->last_contact_at
                    ? (int) now()->diffInDays($inf->last_contact_at)
                    : null;

                return array_merge($inf->toArray(), ['days_elapsed' => $daysElapsed]);
            })
            ->sortByDesc('days_elapsed')
            ->values();

        return response()->json($influenceurs);
    }
}
