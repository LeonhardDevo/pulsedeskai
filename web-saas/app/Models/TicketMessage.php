<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'support_ticket_id',
        'sender_type',
        'sender_name',
        'content',
        'confidence_score',
        'provider_used',
        'sources',
    ];

    protected function casts(): array
    {
        return [
            'confidence_score' => 'float',
            'sources' => 'array',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }
}
