<?php

namespace App\Http\Controllers;

use App\Models\ContentContact;
use App\Models\ContentSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentContactController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ContentContact::with('source:id,name,slug');

        if ($request->filled('source')) {
            $source = ContentSource::where('slug', $request->input('source'))->first();
            if ($source) $query->where('source_id', $source->id);
        }
        if ($request->filled('sector')) {
            $query->where('sector', $request->input('sector'));
        }
        if ($request->filled('has_email')) {
            $query->whereNotNull('email')->where('email', '!=', '');
        }
        if ($request->filled('search')) {
            $search = str_replace(['%', '_'], ['\\%', '\\_'], $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', '%' . $search . '%')
                  ->orWhere('email', 'ilike', '%' . $search . '%')
                  ->orWhere('company', 'ilike', '%' . $search . '%')
                  ->orWhere('role', 'ilike', '%' . $search . '%');
            });
        }

        $allowedSorts = ['name', 'company', 'sector', 'country', 'created_at'];
        $sort = in_array($request->input('sort'), $allowedSorts) ? $request->input('sort') : 'name';
        $direction = $request->input('direction') === 'desc' ? 'desc' : 'asc';

        $perPage = min((int) $request->input('per_page', 50), 100);

        return response()->json($query->orderBy($sort, $direction)->paginate($perPage));
    }

    public function stats(): JsonResponse
    {
        $total = ContentContact::count();
        $withEmail = ContentContact::whereNotNull('email')->where('email', '!=', '')->count();
        $withPhone = ContentContact::whereNotNull('phone')->where('phone', '!=', '')->count();

        $bySector = ContentContact::selectRaw('sector, COUNT(*) as count')
            ->whereNotNull('sector')
            ->groupBy('sector')
            ->orderByDesc('count')
            ->get();

        $bySource = ContentContact::selectRaw('source_id, COUNT(*) as count')
            ->groupBy('source_id')
            ->with('source:id,name')
            ->get();

        $byCountry = ContentContact::selectRaw('country, COUNT(*) as count')
            ->whereNotNull('country')
            ->where('country', '!=', '')
            ->groupBy('country')
            ->orderByDesc('count')
            ->limit(8)
            ->pluck('count', 'country');

        return response()->json([
            'total'      => $total,
            'with_email' => $withEmail,
            'with_phone' => $withPhone,
            'by_sector'  => $bySector,
            'by_source'  => $bySource,
            'by_country' => $byCountry,
        ]);
    }

    public function export(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $query = ContentContact::with('source:id,name');

        if ($request->filled('sector')) $query->where('sector', $request->input('sector'));
        if ($request->filled('has_email')) $query->whereNotNull('email')->where('email', '!=', '');

        return response()->stream(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Nom', 'Role', 'Email', 'Telephone', 'Entreprise', 'Site Web', 'Secteur', 'Pays', 'Ville', 'Source', 'Notes']);

            $query->orderBy('name')->chunk(500, function ($contacts) use ($out) {
                foreach ($contacts as $c) {
                    fputcsv($out, [
                        $c->name, $c->role, $c->email, $c->phone,
                        $c->company, $c->company_url, $c->sector,
                        $c->country, $c->city, $c->source?->name ?? '',
                        mb_substr($c->notes ?? '', 0, 200),
                    ]);
                }
            });
            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="contacts-' . date('Y-m-d') . '.csv"',
        ]);
    }
}
