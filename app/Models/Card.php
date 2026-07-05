<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Card extends Model
{
    protected $fillable = [
        'user_id', 'highlight_id', 'front', 'back', 'context', 'mnemonic',
        'stability', 'difficulty', 'interval_days', 'repetitions', 'lapses',
        'last_reviewed_at', 'due_at',
    ];

    protected function casts(): array
    {
        return [
            'stability' => 'float',
            'difficulty' => 'float',
            'interval_days' => 'integer',
            'repetitions' => 'integer',
            'lapses' => 'integer',
            'last_reviewed_at' => 'datetime',
            'due_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function highlight(): BelongsTo
    {
        return $this->belongsTo(Highlight::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function scopeDue(Builder $query): Builder
    {
        return $query->where('due_at', '<=', now());
    }
}
