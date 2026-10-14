<?php

namespace App\Models;

use App\Support\TicketDurations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delivery extends Model
{
    public const STATUS_PENDING = 'en_attente';

    public const STATUS_DONE = 'livree';

    public const STATUS_CANCELLED = 'annulee';

    protected $fillable = [
        'user_id',
        'customer_id',
        'recipient_name',
        'phone',
        'address',
        'reception_place',
        'reception_gps_url',
        'duration_key',
        'quantity',
        'status',
        'notes',
        'delivery_date',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'delivery_date' => 'date',
            'delivered_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function durationLabel(): string
    {
        return TicketDurations::label($this->duration_key);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_DONE => 'Livrée',
            self::STATUS_CANCELLED => 'Annulée',
            default => 'En attente',
        };
    }

    public function gpsUrl(): ?string
    {
        $url = trim((string) $this->reception_gps_url);
        if ($url === '') {
            return null;
        }

        if (! preg_match('#^(https?://|geo:)#i', $url)) {
            $url = 'https://'.$url;
        }

        return $url;
    }
}
