<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnnuaireImportJob extends Model
{
    protected $table = 'annuaire_import_jobs';

    protected $fillable = [
        'source', 'scope_type', 'scope_value', 'categories',
        'status', 'total_expected', 'total_processed',
        'total_inserted', 'total_updated', 'total_errors',
        'log', 'error_message', 'launched_by',
        'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'categories'   => 'array',
            'started_at'   => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    public function appendLog(string $message): void
    {
        $line = '[' . now()->format('H:i:s') . '] ' . $message;
        $this->update(['log' => ($this->log ? $this->log . "\n" : '') . $line]);
    }

    public function incrementProcessed(int $inserted = 0, int $updated = 0, int $errors = 0): void
    {
        $this->increment('total_processed', $inserted + $updated + $errors);
        if ($inserted) $this->increment('total_inserted', $inserted);
        if ($updated)  $this->increment('total_updated', $updated);
        if ($errors)   $this->increment('total_errors', $errors);
    }

    public function isCancelled(): bool
    {
        return $this->fresh()->status === 'cancelled';
    }

    public function progressPercent(): int
    {
        if ($this->total_expected <= 0) return 0;
        return (int) min(100, round($this->total_processed / $this->total_expected * 100));
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeRecent($query)
    {
        return $query->orderByDesc('created_at')->limit(50);
    }
}
