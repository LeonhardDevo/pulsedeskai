<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'title',
        'category',
        'content',
        'chunks_count',
        'status',
        'last_indexed_at',
    ];

    protected function casts(): array
    {
        return [
            'chunks_count' => 'integer',
            'last_indexed_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
