<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de uma chamada à OpenAI: tokens consumidos e custo estimado (USD),
 * para acompanhar o gasto na página de configurações.
 */
class AiUsage extends Model
{
    protected $fillable = [
        'user_id', 'model', 'kind',
        'prompt_tokens', 'completion_tokens', 'total_tokens', 'cost',
    ];

    protected function casts(): array
    {
        return [
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'total_tokens' => 'integer',
            'cost' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
