<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Services\KindleClippingsParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ImportController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Import');
    }

    public function store(Request $request, KindleClippingsParser $parser): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240'],
        ], [], ['file' => 'arquivo']);

        $entries = $parser->parse($request->file('file')->get());

        $imported = 0;
        $skipped = 0;
        $books = [];

        foreach ($entries->groupBy(fn (array $e) => $e['title'].'|'.$e['author']) as $group) {
            $book = Book::firstOrCreate([
                'user_id' => $request->user()->id,
                'title' => $group->first()['title'],
                'author' => $group->first()['author'],
            ]);

            $books[$book->id] = true;

            foreach ($group as $entry) {
                $highlight = $book->highlights()->firstOrCreate(
                    ['hash' => $entry['hash']],
                    [
                        'type' => $entry['type'],
                        'content' => $entry['content'],
                        'location' => $entry['location'],
                        'page' => $entry['page'],
                        'highlighted_at' => $entry['highlighted_at'],
                    ]
                );

                $highlight->wasRecentlyCreated ? $imported++ : $skipped++;
            }
        }

        return redirect()->route('import.create')->with('import_result', [
            'imported' => $imported,
            'skipped' => $skipped,
            'books' => count($books),
        ]);
    }
}
