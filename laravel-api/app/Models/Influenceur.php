<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Influenceur extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'handle', 'avatar_url', 'platforms', 'primary_platform',
        'followers', 'followers_secondary', 'niche', 'country', 'language',
        'email', 'phone', 'profile_url', 'profile_url_domain', 'status', 'assigned_to',
        'reminder_days', 'reminder_active', 'last_contact_at',
        'partnership_date', 'notes', 'tags', 'created_by',
    ];

    protected $casts = [
        'platforms'          => 'array',
        'followers_secondary'=> 'array',
        'tags'               => 'array',
        'reminder_active'    => 'boolean',
        'last_contact_at'    => 'datetime',
        'partnership_date'   => 'date',
    ];

    public function assignedToUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function contacts()
    {
        return $this->hasMany(Contact::class)
            ->orderBy('date')
            ->orderBy('created_at');
    }

    public function reminders()
    {
        return $this->hasMany(Reminder::class);
    }

    public function pendingReminder()
    {
        return $this->hasOne(Reminder::class)->where('status', 'pending');
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }
}
