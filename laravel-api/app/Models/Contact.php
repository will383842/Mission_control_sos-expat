<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    protected $fillable = [
        'influenceur_id', 'user_id', 'date', 'channel',
        'result', 'sender', 'message', 'reply', 'notes',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function influenceur()
    {
        return $this->belongsTo(Influenceur::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
