<?php

namespace App\Http\Controllers;

use App\Models\AiPrompt;
use Illuminate\Http\Request;

class AiPromptController extends Controller
{
    public function index()
    {
        return response()->json(
            AiPrompt::orderBy('contact_type')->get()
        );
    }

    public function show(string $contactType)
    {
        $prompt = AiPrompt::where('contact_type', $contactType)->first();

        if (!$prompt) {
            // Return the hardcoded default so admin can see + edit it
            $service = new \App\Services\AiPromptService();
            $default = $service->buildPrompt($contactType, '{{PAYS}}', 'fr');
            return response()->json([
                'contact_type'    => $contactType,
                'prompt_template' => $default,
                'is_active'       => true,
                'is_default'      => true,
            ]);
        }

        return response()->json($prompt);
    }

    public function upsert(Request $request)
    {
        $data = $request->validate([
            'contact_type'    => 'required|string|max:50',
            'prompt_template' => 'required|string|min:20',
            'is_active'       => 'sometimes|boolean',
        ]);

        $prompt = AiPrompt::updateOrCreate(
            ['contact_type' => $data['contact_type']],
            $data
        );

        AiPrompt::flushCache($data['contact_type']);

        return response()->json($prompt);
    }

    public function destroy(string $contactType)
    {
        AiPrompt::where('contact_type', $contactType)->delete();
        AiPrompt::flushCache($contactType);

        return response()->json(['message' => 'Prompt supprimé — le défaut sera utilisé.']);
    }
}
