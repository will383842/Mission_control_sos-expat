<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Contact;
use App\Models\Influenceur;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function index(Request $request, Influenceur $influenceur)
    {
        // Researcher scoping
        if ($request->user()->isResearcher() && $influenceur->created_by !== $request->user()->id) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        $contacts = $influenceur->contacts()
            ->with('user:id,name')
            ->get()
            ->map(function ($contact, $index) {
                return array_merge($contact->toArray(), ['rank' => $index + 1]);
            });

        return response()->json($contacts);
    }

    public function store(Request $request, Influenceur $influenceur)
    {
        // Researcher scoping
        if ($request->user()->isResearcher() && $influenceur->created_by !== $request->user()->id) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        $data = $request->validate([
            'date'    => 'required|date|before_or_equal:today',
            'channel' => 'required|string|in:email,instagram,linkedin,whatsapp,phone,other',
            'result'  => 'required|string|in:sent,replied,refused,registered,no_answer',
            'sender'  => 'nullable|string|max:255',
            'message' => 'nullable|string',
            'reply'   => 'nullable|string',
            'notes'   => 'nullable|string',
        ]);

        $data['influenceur_id'] = $influenceur->id;
        $data['user_id']        = $request->user()->id;

        $contact = Contact::create($data);

        ActivityLog::create([
            'user_id'        => $request->user()->id,
            'influenceur_id' => $influenceur->id,
            'action'         => 'contact_added',
            'details'        => [
                'channel' => $data['channel'],
                'result'  => $data['result'],
            ],
        ]);

        return response()->json($contact->load('user:id,name'), 201);
    }

    public function update(Request $request, Influenceur $influenceur, Contact $contact)
    {
        // Researcher scoping
        if ($request->user()->isResearcher() && $influenceur->created_by !== $request->user()->id) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        abort_if($contact->influenceur_id !== $influenceur->id, 404);

        $data = $request->validate([
            'date'    => 'sometimes|date',
            'channel' => 'sometimes|in:email,instagram,linkedin,whatsapp,phone,other',
            'result'  => 'sometimes|in:sent,replied,refused,registered,no_answer',
            'sender'  => 'nullable|string|max:255',
            'message' => 'nullable|string',
            'reply'   => 'nullable|string',
            'notes'   => 'nullable|string',
        ]);

        $contact->update($data);

        ActivityLog::create([
            'user_id'        => $request->user()->id,
            'influenceur_id' => $influenceur->id,
            'action'         => 'contact_updated',
            'details'        => $data,
            'created_at'     => now(),
        ]);

        return response()->json($contact->load('user:id,name'));
    }

    public function destroy(Request $request, Influenceur $influenceur, Contact $contact)
    {
        // Researcher scoping
        if ($request->user()->isResearcher() && $influenceur->created_by !== $request->user()->id) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        abort_if($contact->influenceur_id !== $influenceur->id, 404);

        ActivityLog::create([
            'user_id'        => $request->user()->id,
            'influenceur_id' => $influenceur->id,
            'action'         => 'contact_deleted',
            'details'        => ['contact_id' => $contact->id],
            'created_at'     => now(),
        ]);

        $contact->delete();

        return response()->json(null, 204);
    }
}
