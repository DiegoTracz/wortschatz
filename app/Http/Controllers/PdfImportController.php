<?php

namespace App\Http\Controllers;

use App\Models\PdfPage;
use App\Services\PdfTextExtractor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Importa um PDF como livro (source = 'pdf') para leitura no leitor embutido.
 * O arquivo vai para o disco 'local' (mesmo padrão das capas), o texto é
 * extraído por página para busca, e o usuário passa a marcar destaques direto
 * no texto — que alimentam o mesmo fluxo de cartão dos destaques do Kindle.
 */
class PdfImportController extends Controller
{
    public function __invoke(Request $request, PdfTextExtractor $extractor): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimetypes:application/pdf', 'max:102400'],
        ], [], ['file' => 'arquivo']);

        $upload = $request->file('file');
        $title = pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME) ?: 'PDF sem título';

        $book = $request->user()->books()->create([
            'title' => $title,
            'source' => 'pdf',
            // Livro de PDF não busca capa no Google Books (o título é o nome do
            // arquivo); marca como resolvido para não virar cover_pending.
            'cover_fetched_at' => now(),
        ]);

        Storage::disk('local')->putFileAs('pdfs', $upload, "{$book->id}.pdf");

        $extracted = $extractor->extract(Storage::disk('local')->path($book->pdfPath()));

        $book->update(['page_count' => $extracted['page_count']]);

        $rows = collect($extracted['pages'])
            ->map(fn (string $text, int $page): array => [
                'book_id' => $book->id,
                'page' => $page,
                'text' => $text,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->values();

        $rows->chunk(200)->each(fn ($chunk) => PdfPage::insert($chunk->all()));

        return redirect()->route('books.read', $book)->with('success', 'PDF importado!');
    }
}
