<?php

namespace App\Services;

use App\Models\Card;
use App\Models\Highlight;
use App\Models\Review;
use App\Models\User;

/**
 * Exporta os dados de estudo de um usuário para um snapshot JSON portável,
 * pensado para sincronização manual entre máquinas (ex.: arquivo no Google
 * Drive). O snapshot não usa ids auto-incrementais: livros são referenciados
 * por título, destaques pelo hash de dedupe e cartões/revisões por uuid — o
 * UserDataImporter faz o merge idempotente do outro lado.
 *
 * O arquivo do PDF em si (books.source = 'pdf') não entra no snapshot: só os
 * metadados, destaques e cartões viajam.
 */
class UserDataExporter
{
    public const FORMAT = 'wortschatz-sync';

    public const VERSION = 1;

    /**
     * @return array{format: string, version: int, exported_at: string, books: array, highlights: array, cards: array, reviews: array}
     */
    public function export(User $user): array
    {
        return [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'exported_at' => now()->toIso8601String(),
            'books' => $user->books()->orderBy('title')->get()->map(fn ($book) => [
                'title' => $book->title,
                'author' => $book->author,
                'source' => $book->source,
                'language' => $book->language,
                'page_count' => $book->page_count,
                'cover_url' => $book->cover_url,
            ])->all(),
            'highlights' => Highlight::query()
                ->whereHas('book', fn ($query) => $query->where('user_id', $user->id))
                ->with('book:id,title')
                ->orderBy('id')
                ->get()
                ->map(fn (Highlight $highlight) => [
                    'book_title' => $highlight->book->title,
                    'hash' => $highlight->hash,
                    'type' => $highlight->type,
                    'content' => $highlight->content,
                    'location' => $highlight->location,
                    'page' => $highlight->page,
                    'anchor' => $highlight->anchor,
                    'highlighted_at' => $highlight->highlighted_at?->toIso8601String(),
                ])->all(),
            'cards' => $user->cards()->with('highlight.book:id,title')->orderBy('id')->get()->map(fn (Card $card) => [
                'uuid' => $card->uuid,
                'book_title' => $card->highlight?->book?->title,
                'highlight_hash' => $card->highlight?->hash,
                'front' => $card->front,
                'back' => $card->back,
                'context' => $card->context,
                'mnemonic' => $card->mnemonic,
                'due_at' => $card->due_at->toIso8601String(),
                'created_at' => $card->created_at?->toIso8601String(),
                'updated_at' => $card->updated_at?->toIso8601String(),
            ])->all(),
            'reviews' => Review::query()
                ->where('user_id', $user->id)
                ->with('card:id,uuid')
                ->orderBy('created_at')
                ->get()
                ->map(fn (Review $review) => [
                    'uuid' => $review->uuid,
                    'card_uuid' => $review->card->uuid,
                    'rating' => $review->rating,
                    'interval_before' => $review->interval_before,
                    'interval_after' => $review->interval_after,
                    'stability_after' => $review->stability_after,
                    'difficulty_after' => $review->difficulty_after,
                    'reviewed_at' => $review->created_at->toIso8601String(),
                ])->all(),
        ];
    }
}
