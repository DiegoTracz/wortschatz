<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Services\BookCoverFetcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookCoverController extends Controller
{
    /**
     * Busca e persiste a capa de um livro sob demanda (chamado do front para
     * os livros que ainda não têm capa). Marca cover_fetched_at mesmo quando
     * não acha nada, para não repetir a consulta ao Google Books.
     */
    public function __invoke(Request $request, Book $book, BookCoverFetcher $fetcher): JsonResponse
    {
        abort_unless($book->user_id === $request->user()->id, 403);

        if ($book->cover_fetched_at === null) {
            $book->forceFill([
                'cover_url' => $fetcher->fetch($book->title, $book->author),
                'cover_fetched_at' => now(),
            ])->save();
        }

        return response()->json(['cover_url' => $book->cover_url]);
    }
}
