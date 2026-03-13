<?php

namespace App\Http\Controllers;

use App\Exports\InfluenceursExport;
use App\Models\Influenceur;
use Illuminate\Support\Facades\Response;
use Maatwebsite\Excel\Facades\Excel;

class ExportController extends Controller
{
    public function csv()
    {
        $influenceurs = Influenceur::with(['assignedToUser:id,name'])->get();

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="influenceurs-' . now()->format('Y-m-d') . '.csv"',
        ];

        $callback = function () use ($influenceurs) {
            $file = fopen('php://output', 'w');
            // BOM UTF-8 pour Excel
            fputs($file, "\xEF\xBB\xBF");

            fputcsv($file, [
                'ID', 'Nom', 'Handle', 'Plateforme principale', 'Followers',
                'Statut', 'Pays', 'Email', 'Téléphone', 'Niche',
                'Assigné à', 'Dernier contact', 'Date partenariat', 'Notes',
            ], ';');

            foreach ($influenceurs as $inf) {
                fputcsv($file, [
                    $inf->id,
                    $inf->name,
                    $inf->handle,
                    $inf->primary_platform,
                    $inf->followers,
                    $inf->status,
                    $inf->country,
                    $inf->email,
                    $inf->phone,
                    $inf->niche,
                    $inf->assignedToUser?->name,
                    $inf->last_contact_at?->toDateString(),
                    $inf->partnership_date?->toDateString(),
                    $inf->notes,
                ], ';');
            }

            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }

    public function excel()
    {
        $filename = 'influenceurs-' . now()->format('Y-m-d') . '.xlsx';
        return Excel::download(new InfluenceursExport, $filename);
    }
}
