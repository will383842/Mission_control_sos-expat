<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class InfluenceursExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new InfluenceursSheet(),
            new ContactsSheet(),
        ];
    }
}
