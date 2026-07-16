<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Book extends Model
{
    protected $fillable = ['user_id', 'title', 'author', 'source', 'language', 'page_count', 'cover_url', 'cover_fetched_at'];

    protected function casts(): array
    {
        return [
            'cover_fetched_at' => 'datetime',
            'page_count' => 'integer',
        ];
    }

    /**
     * Caminho relativo do PDF no disco 'local' (quando source === 'pdf').
     */
    public function pdfPath(): string
    {
        return "pdfs/{$this->id}.pdf";
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function highlights(): HasMany
    {
        return $this->hasMany(Highlight::class);
    }

    public function pdfPages(): HasMany
    {
        return $this->hasMany(PdfPage::class);
    }
}
