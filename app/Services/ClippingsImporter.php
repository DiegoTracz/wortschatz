<?php

namespace App\Services;

use App\Models\Book;
use App\Models\Highlight;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Persiste entradas de destaques para um usuário, deduplicando por hash
 * (Highlight::computeHash) dentro de cada livro. Aceita tanto as entradas do
 * KindleClippingsParser quanto payloads JSON soltos (ex.: scraper do Amazon
 * Notebook), que chegam sem hash e com campos opcionais ausentes.
 *
 * O livro casa por título apenas (autor preenchido só na criação): as fontes
 * grafam o autor de formas diferentes ("Kafka, Franz" × "Franz Kafka") e uma
 * chave com autor duplicaria o livro, anulando o dedupe.
 */
class ClippingsImporter
{
    /**
     * @param  Collection<int, array>  $entries
     * @return array{imported: int, skipped: int, books: int}
     */
    public function import(User $user, Collection $entries): array
    {
        $imported = 0;
        $skipped = 0;
        $books = [];

        foreach ($entries->groupBy('title') as $group) {
            $book = Book::firstOrCreate(
                ['user_id' => $user->id, 'title' => $group->first()['title']],
                ['author' => $group->first()['author'] ?? null],
            );

            $books[$book->id] = true;

            foreach ($group as $entry) {
                $entry = $this->normalize($entry);

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

        return ['imported' => $imported, 'skipped' => $skipped, 'books' => count($books)];
    }

    private function normalize(array $entry): array
    {
        $entry['type'] ??= 'highlight';
        $entry['location'] ??= null;
        $entry['page'] ??= null;
        $entry['highlighted_at'] = $this->parseDate($entry['highlighted_at'] ?? null);
        $entry['hash'] ??= Highlight::computeHash(
            $entry['title'],
            $entry['type'],
            $entry['location'],
            $entry['page'],
            $entry['content'],
        );

        return $entry;
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
