<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Highlight extends Model
{
    protected $fillable = ['book_id', 'type', 'content', 'location', 'page', 'highlighted_at', 'hash'];

    protected function casts(): array
    {
        return [
            'highlighted_at' => 'datetime',
        ];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }
}
