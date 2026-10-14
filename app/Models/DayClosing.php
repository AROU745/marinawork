<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DayClosing extends Model
{
    protected $fillable = [
        'user_id',
        'business_date',
        'tickets_count',
        'total_amount',
        'breakdown',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'tickets_count' => 'integer',
            'total_amount' => 'integer',
            'breakdown' => 'array',
            'closed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(TicketSale::class);
    }
}
