<?php

namespace App\Models;

use App\Support\TicketDurations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketSale extends Model
{
    protected $fillable = [
        'user_id',
        'ticket_id',
        'ticket_batch_id',
        'day_closing_id',
        'duration_key',
        'price',
        'sold_on',
        'sold_at',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'sold_on' => 'date',
            'sold_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(TicketBatch::class, 'ticket_batch_id');
    }

    public function dayClosing(): BelongsTo
    {
        return $this->belongsTo(DayClosing::class);
    }

    public function durationLabel(): string
    {
        return TicketDurations::label($this->duration_key);
    }
}
