<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PdfPage extends Model
{
    protected $fillable = ['book_id', 'page', 'text'];

    protected function casts(): array
    {
        return [
            'page' => 'integer',
        ];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
