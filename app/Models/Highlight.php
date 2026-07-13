<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Highlight extends Model
{
    protected $fillable = ['book_id', 'type', 'content', 'location', 'page', 'anchor', 'highlighted_at', 'hash'];

    /**
     * Hash de deduplicação, único por livro. Normaliza as diferenças entre as
     * fontes de import (My Clippings.txt × Amazon Notebook): whitespace é
     * colapsado e a localização vira só o número inicial — o clippings grava
     * intervalos ("100-102") e o notebook só o início, às vezes com separador
     * de milhar ("1.234").
     */
    public static function computeHash(string $title, string $type, ?string $location, ?string $page, string $content): string
    {
        $locationKey = $location !== null && preg_match('/\d+/', str_replace(['.', ','], '', $location), $m)
            ? $m[0]
            : ($page ?? '');

        $squash = fn (string $s): string => trim(preg_replace('/\s+/u', ' ', $s));

        return sha1(mb_strtolower($squash($title).'|'.$type.'|'.$locationKey.'|'.$squash($content)));
    }

    protected function casts(): array
    {
        return [
            'highlighted_at' => 'datetime',
            'anchor' => 'array',
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
