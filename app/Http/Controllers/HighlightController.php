<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Highlight;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Destaques criados no leitor de PDF: a seleção do usuário vira um Highlight
 * (com âncora de página + coordenadas) que reaparece como overlay e alimenta o
 * mesmo fluxo de cartão dos destaques do Kindle (Card.highlight_id).
 */
class HighlightController extends Controller
{
    public function store(Request $request, Book $book): JsonResponse
    {
        abort_unless($book->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'content' => ['required', 'string', 'max:2000'],
            'page' => ['required', 'integer', 'min:1'],
            'anchor' => ['required', 'array'],
            'anchor.rects' => ['required', 'array', 'min:1'],
            'anchor.rects.*.x0' => ['required', 'numeric'],
            'anchor.rects.*.y0' => ['required', 'numeric'],
            'anchor.rects.*.x1' => ['required', 'numeric'],
            'anchor.rects.*.y1' => ['required', 'numeric'],
            'anchor.quote' => ['nullable', 'array'],
        ]);

        // Posição (y do primeiro retângulo) entra na chave de dedup para que a
        // mesma palavra em pontos diferentes da página não colida; reselecionar
        // exatamente o mesmo trecho devolve o destaque existente.
        $location = (string) (int) round($data['anchor']['rects'][0]['y0']);
        $page = (string) $data['page'];

        $hash = Highlight::computeHash($book->title, 'highlight', $location, $page, $data['content']);

        $highlight = $book->highlights()->firstOrCreate(
            ['hash' => $hash],
            [
                'type' => 'highlight',
                'content' => $data['content'],
                'location' => $location,
                'page' => $page,
                'anchor' => $data['anchor'],
                'highlighted_at' => now(),
            ],
        );

        return response()->json([
            'id' => $highlight->id,
            'content' => $highlight->content,
            'page' => (int) $highlight->page,
            'anchor' => $highlight->anchor,
        ]);
    }

    public function destroy(Request $request, Highlight $highlight): JsonResponse
    {
        abort_unless($highlight->book->user_id === $request->user()->id, 403);

        $highlight->delete();

        return response()->json(['deleted' => true]);
    }
}
