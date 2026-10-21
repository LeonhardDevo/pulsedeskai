<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory;

    public const SUPPORTED_PROVIDERS = [
        'openrouter' => [
            'label' => 'OpenRouter API (Recommended)',
            'default_model' => 'openai/gpt-4o-mini',
            'placeholder_key' => 'sk-or-v1-...',
        ],
        'opencode' => [
            'label' => 'OpenCode API',
            'default_model' => 'opencode-chat-v1',
            'placeholder_key' => 'oc-live-...',
        ],
        'claude' => [
            'label' => 'Anthropic Claude API',
            'default_model' => 'claude-3-5-sonnet-latest',
            'placeholder_key' => 'sk-ant-api03-...',
        ],
        'openai' => [
            'label' => 'OpenAI API',
            'default_model' => 'gpt-4o-mini',
            'placeholder_key' => 'sk-proj-...',
        ],
        'mock' => [
            'label' => 'Local Grounded Synthesizer (Zero-Cost Demo)',
            'default_model' => 'pulsedesk-rag-synthesizer-v1',
            'placeholder_key' => 'not-required',
        ],
    ];

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'telegram_bot_token',
        'ai_provider',
        'ai_api_key',
        'ai_model',
        'confidence_threshold',
        'system_prompt',
        'plan',
        'stripe_customer_id',
        'stripe_subscription_id',
        'subscription_status',
        'monthly_quota',
        'used_quota',
        'cost_per_human_ticket',
    ];

    protected $hidden = [
        'ai_api_key',
        'telegram_bot_token',
    ];

    protected function casts(): array
    {
        return [
            'ai_api_key' => 'encrypted',
            'telegram_bot_token' => 'encrypted',
            'confidence_threshold' => 'float',
            'monthly_quota' => 'integer',
            'used_quota' => 'integer',
            'cost_per_human_ticket' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(KnowledgeDocument::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function hasAvailableQuota(): bool
    {
        return $this->used_quota < $this->monthly_quota && $this->subscription_status === 'active';
    }

    public function getMaskedApiKeyAttribute(): ?string
    {
        $rawKey = $this->ai_api_key;
        if (empty($rawKey)) {
            return null;
        }
        $len = strlen($rawKey);
        if ($len <= 8) {
            return '••••••••';
        }
        return substr($rawKey, 0, 6) . '••••••••' . substr($rawKey, -4);
    }
}
