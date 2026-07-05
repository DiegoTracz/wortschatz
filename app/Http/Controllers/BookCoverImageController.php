<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serve a capa local (miniatura do Kindle copiada para o storage) de um livro.
 * A URL guardada em `books.cover_url` é relativa — resolvida contra a origem
 * atual — porque o servidor embarcado do NativePHP troca de porta a cada boot.
 */
class BookCoverImageController extends Controller
{
    public function __invoke(Request $request, Book $book): StreamedResponse
    {
        abort_unless($book->user_id === $request->user()->id, 403);

        $path = "covers/{$book->id}.jpg";

        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, "capa-{$book->id}.jpg", [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
