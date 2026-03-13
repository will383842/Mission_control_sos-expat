<?php

namespace App\Exports;

use App\Models\Influenceur;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class InfluenceursSheet implements FromQuery, WithHeadings, WithTitle, WithMapping
{
    public function title(): string
    {
        return 'Influenceurs';
    }

    public function query()
    {
        return Influenceur::with(['assignedToUser:id,name']);
    }

    public function headings(): array
    {
        return [
            'ID', 'Nom', 'Handle', 'Plateforme', 'Followers',
            'Statut', 'Pays', 'Email', 'Niche',
            'Assigné à', 'Dernier contact', 'Date partenariat', 'Notes',
        ];
    }

    public function map($inf): array
    {
        return [
            $inf->id,
            $inf->name,
            $inf->handle,
            $inf->primary_platform,
            $inf->followers,
            $inf->status,
            $inf->country,
            $inf->email,
            $inf->niche,
            $inf->assignedToUser?->name,
            $inf->last_contact_at?->toDateString(),
            $inf->partnership_date?->toDateString(),
            $inf->notes,
        ];
    }
}
