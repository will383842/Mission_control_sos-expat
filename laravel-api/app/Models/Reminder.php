<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reminder extends Model
{
    protected $fillable = [
        'influenceur_id', 'due_date', 'status',
        'dismissed_by', 'dismissed_at', 'notes',
    ];

    protected $casts = [
        'due_date'     => 'date',
        'dismissed_at' => 'datetime',
    ];

    public function influenceur()
    {
        return $this->belongsTo(Influenceur::class);
    }

    public function dismissedByUser()
    {
        return $this->belongsTo(User::class, 'dismissed_by');
    }
}
