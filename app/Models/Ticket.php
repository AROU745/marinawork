<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Ticket extends Model
{
    protected $fillable = [
        'ticket_batch_id',
        'position',
        'username',
        'password',
        'code',
        'ip_address',
        'ticket_no',
        'duration',
        'price',
        'image_path',
        'raw_text',
        'revealed',
        'revealed_at',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'revealed' => 'boolean',
            'revealed_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(TicketBatch::class, 'ticket_batch_id');
    }

    public function displayLabel(): string
    {
        if ($this->username && $this->password) {
            return $this->username;
        }

        return $this->code ?? $this->raw_text;
    }

    public function imageUrl(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        return Storage::disk('public')->url($this->image_path);
    }
}
