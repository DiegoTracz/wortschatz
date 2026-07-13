<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streama o arquivo PDF de um livro para o leitor embutido. A URL guardada/gerada
 * é relativa (resolvida contra a origem atual) porque o servidor do NativePHP
 * troca de porta a cada boot — mesmo padrão do BookCoverImageController.
 */
class BookFileController extends Controller
{
    public function __invoke(Request $request, Book $book): StreamedResponse
    {
        abort_unless($book->user_id === $request->user()->id, 403);

        abort_unless(Storage::disk('local')->exists($book->pdfPath()), 404);

        return Storage::disk('local')->response($book->pdfPath(), "livro-{$book->id}.pdf", [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
