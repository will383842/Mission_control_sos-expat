<?php

namespace App\Exports;

use App\Models\Contact;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class ContactsSheet implements FromQuery, WithHeadings, WithTitle, WithMapping
{
    private array $rankMap = [];

    public function title(): string
    {
        return 'Timeline Contacts';
    }

    public function query()
    {
        return Contact::with(['influenceur:id,name', 'user:id,name'])
            ->orderBy('influenceur_id')
            ->orderBy('date')
            ->orderBy('created_at');
    }

    public function headings(): array
    {
        return [
            'Influenceur', 'Rang', 'Date', 'Canal',
            'Résultat', 'Expéditeur', 'Message', 'Réponse',
            'Membre équipe', 'Notes',
        ];
    }

    public function map($contact): array
    {
        $id = $contact->influenceur_id;
        $this->rankMap[$id] = ($this->rankMap[$id] ?? 0) + 1;

        return [
            $contact->influenceur?->name,
            $this->rankMap[$id],
            $contact->date?->toDateString(),
            $contact->channel,
            $contact->result,
            $contact->sender,
            $contact->message,
            $contact->reply,
            $contact->user?->name,
            $contact->notes,
        ];
    }
}
