<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BookController extends Controller
{
    public function index(Request $request): Response
    {
        $books = $request->user()->books()
            ->withCount('highlights')
            ->orderBy('title')
            ->get()
            ->map(fn (Book $book) => [
                'id' => $book->id,
                'title' => $book->title,
                'author' => $book->author,
                'highlights_count' => $book->highlights_count,
                'cards_count' => $book->highlights()->has('cards')->count(),
            ]);

        return Inertia::render('Books/Index', ['books' => $books]);
    }

    public function show(Request $request, Book $book): Response
    {
        abort_unless($book->user_id === $request->user()->id, 403);

        $highlights = $book->highlights()
            ->with('cards:id,highlight_id,front')
            ->orderByRaw('cast(location as integer)')
            ->get()
            ->map(fn ($highlight) => [
                'id' => $highlight->id,
                'type' => $highlight->type,
                'content' => $highlight->content,
                'location' => $highlight->location,
                'page' => $highlight->page,
                'highlighted_at' => $highlight->highlighted_at?->toDateString(),
                'cards' => $highlight->cards->map->only(['id', 'front']),
            ]);

        return Inertia::render('Books/Show', [
            'book' => $book->only(['id', 'title', 'author']),
            'highlights' => $highlights,
        ]);
    }

    public function destroy(Request $request, Book $book): RedirectResponse
    {
        abort_unless($book->user_id === $request->user()->id, 403);

        $book->delete();

        return redirect()->route('books.index');
    }
}
