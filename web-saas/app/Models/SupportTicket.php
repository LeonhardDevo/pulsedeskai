<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'telegram_chat_id',
        'customer_name',
        'customer_username',
        'status',
        'was_deflected',
        'intent',
        'escalation_reason',
        'avg_confidence',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'was_deflected' => 'boolean',
            'avg_confidence' => 'float',
            'last_message_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class);
    }
}
