<?php

namespace App\Models;

use App\Support\TicketDurations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketBatch extends Model
{
    protected $fillable = [
        'original_filename',
        'duration_key',
        'stored_path',
        'total_tickets',
        'current_index',
        'needs_visual_scan',
    ];

    protected function casts(): array
    {
        return [
            'total_tickets' => 'integer',
            'current_index' => 'integer',
            'needs_visual_scan' => 'boolean',
        ];
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class)->orderBy('position');
    }

    public function remainingCount(): int
    {
        return max(0, $this->total_tickets - $this->current_index);
    }

    public function revealedCount(): int
    {
        return $this->current_index;
    }

    public function isExhausted(): bool
    {
        if ($this->total_tickets === 0) {
            return true;
        }

        return $this->current_index >= $this->total_tickets;
    }

    public function isPendingVisualScan(): bool
    {
        return $this->needs_visual_scan && $this->total_tickets === 0;
    }

    public function durationLabel(): string
    {
        return TicketDurations::label($this->duration_key);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query
            ->where('needs_visual_scan', false)
            ->where('total_tickets', '>', 0)
            ->whereColumn('current_index', '<', 'total_tickets');
    }

    public function scopeForDuration(Builder $query, string $durationKey): Builder
    {
        return $query->where('duration_key', $durationKey);
    }
}
