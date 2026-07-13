<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Highlight;
use App\Models\PdfPage;
use App\Services\WordFrequencyAnalyzer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class BookController extends Controller
{
    public function index(Request $request): Response
    {
        $books = $request->user()->books()
            ->withCount('highlights')
            ->withMax('highlights', 'highlighted_at')
            ->withMax('highlights', 'created_at')
            // Biblioteca ordenada pelos livros com destaques mais recentes;
            // cai para a data de importação quando o destaque não tem data.
            ->orderByRaw('COALESCE(highlights_max_highlighted_at, highlights_max_created_at) DESC')
            ->get()
            ->map(fn (Book $book) => [
                'id' => $book->id,
                'title' => $book->title,
                'author' => $book->author,
                'source' => $book->source,
                'cover_url' => $book->cover_url,
                'cover_pending' => $book->cover_fetched_at === null,
                'highlights_count' => $book->highlights_count,
                'cards_count' => $book->highlights()->has('cards')->count(),
                'last_highlight_at' => ($latest = $book->highlights_max_highlighted_at ?? $book->highlights_max_created_at)
                    ? Carbon::parse($latest)->toDateString()
                    : null,
            ]);

        return Inertia::render('Books/Index', ['books' => $books]);
    }

    public function show(Request $request, Book $book, WordFrequencyAnalyzer $analyzer): Response
    {
        abort_unless($book->user_id === $request->user()->id, 403);

        $highlights = $book->highlights()
            ->with('cards:id,highlight_id,front')
            ->orderByRaw('cast(location as integer)')
            ->get();

        // Mapa de vocabulário: só o texto dos destaques (não das notas pessoais).
        $frequencies = $analyzer->analyze($highlights->where('type', 'highlight')->pluck('content'), 1000);

        $dates = $highlights->pluck('highlighted_at')->filter();

        // Palavras que já viraram cartão (normalizadas, sem artigo) para separar
        // "o que ainda falta estudar" do que já está no baralho.
        $carded = $highlights->flatMap(fn ($highlight) => $highlight->cards->pluck('front'))
            ->flatMap(fn ($front) => preg_split('/[^\p{L}]+/u', mb_strtolower($front), -1, PREG_SPLIT_NO_EMPTY))
            ->reject(fn ($word) => in_array($word, ['der', 'die', 'das'], true))
            ->flip();

        $stats = [
            'highlights' => $highlights->where('type', 'highlight')->count(),
            'notes' => $highlights->where('type', 'note')->count(),
            'cards' => $highlights->sum(fn ($highlight) => $highlight->cards->count()),
            'unique_words' => count($frequencies),
            'first_at' => $dates->min()?->toDateString(),
            'last_at' => $dates->max()?->toDateString(),
        ];

        return Inertia::render('Books/Show', [
            'book' => $book->only(['id', 'title', 'author', 'source', 'cover_url']),
            'highlights' => $highlights->map(fn ($highlight) => [
                'id' => $highlight->id,
                'type' => $highlight->type,
                'content' => $highlight->content,
                'location' => $highlight->location,
                'page' => $highlight->page,
                'highlighted_at' => $highlight->highlighted_at?->toDateString(),
                'cards' => $highlight->cards->map->only(['id', 'front']),
            ]),
            'stats' => $stats,
            'words' => collect(array_slice($frequencies, 0, 40))
                ->map(fn ($word) => [...$word, 'has_card' => $carded->has($word['word'])])
                ->all(),
            'distribution' => $this->positionDistribution($highlights->where('type', 'highlight')),
            'timeline' => $this->readingTimeline($dates),
        ]);
    }

    /**
     * Leitor de PDF embutido: renderiza o arquivo do livro e os destaques já
     * marcados (com âncora de página/coordenadas) para reexibir como overlays.
     */
    public function read(Request $request, Book $book): Response
    {
        abort_unless($book->user_id === $request->user()->id, 403);
        abort_unless($book->source === 'pdf', 404);

        $highlights = $book->highlights()
            ->whereNotNull('anchor')
            ->with('cards:id,highlight_id,front')
            ->get();

        return Inertia::render('Books/Reader', [
            'book' => $book->only(['id', 'title', 'page_count']),
            // URL relativa: o servidor embarcado do NativePHP troca de porta a cada boot.
            'fileUrl' => route('books.file', $book, absolute: false),
            'highlights' => $highlights->map(fn (Highlight $highlight) => [
                'id' => $highlight->id,
                'content' => $highlight->content,
                'page' => (int) $highlight->page,
                'anchor' => $highlight->anchor,
                'cards' => $highlight->cards->map->only(['id', 'front']),
            ]),
        ]);
    }

    /**
     * Busca full-text dentro do PDF (texto extraído por página no upload).
     * Retorna as páginas que casam, com um trecho ao redor do termo.
     */
    public function search(Request $request, Book $book): JsonResponse
    {
        abort_unless($book->user_id === $request->user()->id, 403);

        $query = trim((string) $request->string('q'));

        if (mb_strlen($query) < 2) {
            return response()->json(['results' => []]);
        }

        $results = $book->pdfPages()
            ->where('text', 'like', '%'.$query.'%')
            ->orderBy('page')
            ->limit(50)
            ->get()
            ->map(fn (PdfPage $page) => [
                'page' => $page->page,
                'snippet' => $this->searchSnippet($page->text, $query),
            ]);

        return response()->json(['results' => $results]);
    }

    /**
     * Trecho de ~120 caracteres ao redor da primeira ocorrência do termo.
     */
    private function searchSnippet(string $text, string $query): string
    {
        $position = mb_stripos($text, $query);

        if ($position === false) {
            return mb_substr($text, 0, 120);
        }

        $start = max(0, $position - 50);
        $snippet = mb_substr($text, $start, 120);

        return ($start > 0 ? '…' : '').trim($snippet).'…';
    }

    /**
     * Distribui os destaques em ~20 faixas de posição (Kindle location) para
     * mostrar em que trechos do livro a leitura foi mais marcada.
     *
     * @return list<array{start: int, end: int, count: int}>
     */
    private function positionDistribution($highlights): array
    {
        $positions = $highlights
            ->map(fn ($highlight) => preg_match('/\d+/', str_replace(['.', ','], '', (string) $highlight->location), $m) ? (int) $m[0] : null)
            ->filter(fn ($position) => $position !== null)
            ->values();

        if ($positions->count() < 2 || $positions->min() === $positions->max()) {
            return [];
        }

        $min = $positions->min();
        $max = $positions->max();
        $bins = 20;
        $size = ($max - $min) / $bins;
        $counts = array_fill(0, $bins, 0);

        foreach ($positions as $position) {
            $counts[(int) min($bins - 1, floor(($position - $min) / $size))]++;
        }

        return array_map(fn ($count, $i) => [
            'start' => (int) round($min + $i * $size),
            'end' => (int) round($min + ($i + 1) * $size),
            'count' => $count,
        ], $counts, array_keys($counts));
    }

    /**
     * Contagem de destaques por dia, em ordem cronológica.
     *
     * @return list<array{date: string, count: int}>
     */
    private function readingTimeline($dates): array
    {
        return $dates
            ->groupBy(fn ($date) => $date->toDateString())
            ->map(fn ($group, $date) => ['date' => $date, 'count' => $group->count()])
            ->sortBy('date')
            ->values()
            ->all();
    }

    public function destroy(Request $request, Book $book): RedirectResponse
    {
        abort_unless($book->user_id === $request->user()->id, 403);

        $book->delete();

        return redirect()->route('books.index');
    }
}
