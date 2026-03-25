<?php

namespace App\Http\Controllers;

use App\Jobs\RunQualityVerificationJob;
use App\Models\DuplicateFlag;
use App\Models\Influenceur;
use App\Models\TypeVerificationFlag;
use App\Services\DeduplicationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QualityController extends Controller
{
    /**
     * Dashboard: global quality stats.
     */
    public function dashboard()
    {
        $total = Influenceur::count();

        // Email verification stats
        $emailStats = Influenceur::selectRaw("
            SUM(CASE WHEN email IS NULL THEN 1 ELSE 0 END) as no_email,
            SUM(CASE WHEN email_verified_status = 'unverified' AND email IS NOT NULL THEN 1 ELSE 0 END) as unverified,
            SUM(CASE WHEN email_verified_status = 'verified' THEN 1 ELSE 0 END) as verified,
            SUM(CASE WHEN email_verified_status = 'invalid' THEN 1 ELSE 0 END) as invalid,
            SUM(CASE WHEN email_verified_status IN ('risky', 'catch_all') THEN 1 ELSE 0 END) as risky
        ")->first();

        // Quality score distribution
        $scoreDistribution = Influenceur::selectRaw("
            SUM(CASE WHEN quality_score = 0 THEN 1 ELSE 0 END) as not_scored,
            SUM(CASE WHEN quality_score BETWEEN 1 AND 25 THEN 1 ELSE 0 END) as low,
            SUM(CASE WHEN quality_score BETWEEN 26 AND 50 THEN 1 ELSE 0 END) as medium,
            SUM(CASE WHEN quality_score BETWEEN 51 AND 75 THEN 1 ELSE 0 END) as good,
            SUM(CASE WHEN quality_score > 75 THEN 1 ELSE 0 END) as excellent
        ")->first();

        // Pending flags
        $pendingDupes = DuplicateFlag::pending()->count();
        $pendingTypes = TypeVerificationFlag::pending()->count();

        return response()->json([
            'total'         => $total,
            'email'         => $emailStats,
            'scores'        => $scoreDistribution,
            'pending_dupes' => $pendingDupes,
            'pending_types' => $pendingTypes,
        ]);
    }

    /**
     * List duplicate flags.
     */
    public function duplicates(Request $request)
    {
        $query = DuplicateFlag::with(['influenceurA:id,name,email,contact_type,country,profile_url', 'influenceurB:id,name,email,contact_type,country,profile_url']);

        if ($request->query('status', 'pending') !== 'all') {
            $query->where('status', $request->query('status', 'pending'));
        }

        $flags = $query->orderByDesc('confidence')
            ->orderByDesc('id')
            ->paginate(50);

        return response()->json($flags);
    }

    /**
     * Resolve a duplicate flag.
     */
    public function resolveDuplicate(Request $request, DuplicateFlag $flag)
    {
        $data = $request->validate([
            'action' => 'required|in:merge_a,merge_b,dismiss,keep_both',
        ]);

        $dedupService = app(DeduplicationService::class);

        switch ($data['action']) {
            case 'merge_a':
                // Keep A, merge B into A
                $dedupService->autoMerge($flag->influenceur_a_id, $flag->influenceur_b_id);
                $flag->update(['status' => 'merged', 'resolved_by' => $request->user()->id, 'resolved_at' => now()]);
                break;
            case 'merge_b':
                // Keep B, merge A into B
                $dedupService->autoMerge($flag->influenceur_b_id, $flag->influenceur_a_id);
                $flag->update(['status' => 'merged', 'resolved_by' => $request->user()->id, 'resolved_at' => now()]);
                break;
            case 'dismiss':
                $flag->update(['status' => 'dismissed', 'resolved_by' => $request->user()->id, 'resolved_at' => now()]);
                break;
            case 'keep_both':
                $flag->update(['status' => 'kept_both', 'resolved_by' => $request->user()->id, 'resolved_at' => now()]);
                break;
        }

        return response()->json(['message' => 'Doublon résolu.', 'flag' => $flag->fresh()]);
    }

    /**
     * List type verification flags.
     */
    public function typeFlags(Request $request)
    {
        $query = TypeVerificationFlag::with(['influenceur:id,name,email,contact_type,country,profile_url']);

        if ($request->query('status', 'pending') !== 'all') {
            $query->where('status', $request->query('status', 'pending'));
        }

        $flags = $query->orderByDesc('id')->paginate(50);

        return response()->json($flags);
    }

    /**
     * Resolve a type flag: fix type or dismiss.
     */
    public function resolveTypeFlag(Request $request, TypeVerificationFlag $flag)
    {
        $data = $request->validate([
            'action'   => 'required|in:fix,dismiss',
            'new_type' => 'nullable|string|max:50',
        ]);

        if ($data['action'] === 'fix') {
            $newType = $data['new_type'] ?? $flag->suggested_type;
            if ($newType) {
                Influenceur::where('id', $flag->influenceur_id)->update(['contact_type' => $newType]);
            }
            $flag->update(['status' => 'fixed', 'resolved_by' => $request->user()->id]);
        } else {
            $flag->update(['status' => 'dismissed', 'resolved_by' => $request->user()->id]);
        }

        return response()->json(['message' => 'Flag résolu.', 'flag' => $flag->fresh()]);
    }

    /**
     * Manually trigger the quality pipeline.
     */
    public function runAll()
    {
        RunQualityVerificationJob::dispatch();
        return response()->json(['message' => 'Pipeline de qualité lancée.']);
    }
}
