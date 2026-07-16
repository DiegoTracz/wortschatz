<?php

namespace App\Services;

use App\Models\Book;
use App\Models\Card;
use App\Models\Highlight;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Faz o merge de um snapshot do UserDataExporter no banco local. É idempotente
 * e aditivo: importar o mesmo arquivo duas vezes (ou snapshots de máquinas
 * diferentes, em qualquer ordem) converge para o mesmo estado.
 *
 * Identidades entre máquinas: livro por (user_id, title) — mesma chave do
 * ClippingsImporter —, destaque pelo hash de dedupe, cartão e revisão por uuid.
 * Revisões são um log append-only; após o merge, o estado FSRS de cada cartão
 * afetado é reconstruído do histórico completo via FsrsScheduler::replay(),
 * então o agendamento converge sem resolução manual de conflito.
 */
class UserDataImporter
{
    public function __construct(private FsrsScheduler $scheduler) {}

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array{books: int, highlights: int, cards: int, cards_updated: int, reviews: int}
     */
    public function import(User $user, array $snapshot): array
    {
        if (($snapshot['format'] ?? null) !== UserDataExporter::FORMAT) {
            throw new InvalidArgumentException('O arquivo não é um export do Wortschatz.');
        }

        if (($snapshot['version'] ?? null) !== UserDataExporter::VERSION) {
            throw new InvalidArgumentException('Versão do export não suportada por esta versão do app.');
        }

        $createdBooks = $this->mergeBooks($user, $snapshot['books'] ?? []);
        $createdHighlights = $this->mergeHighlights($user, $snapshot['highlights'] ?? []);
        [$createdCards, $updatedCards] = $this->mergeCards($user, $snapshot['cards'] ?? []);
        $createdReviews = $this->mergeReviews($user, $snapshot['reviews'] ?? []);

        return [
            'books' => $createdBooks,
            'highlights' => $createdHighlights,
            'cards' => $createdCards,
            'cards_updated' => $updatedCards,
            'reviews' => $createdReviews,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $books
     */
    private function mergeBooks(User $user, array $books): int
    {
        $created = 0;

        foreach ($books as $entry) {
            $book = Book::firstOrCreate(
                ['user_id' => $user->id, 'title' => $entry['title']],
                [
                    'author' => $entry['author'] ?? null,
                    'source' => $entry['source'] ?? 'kindle',
                    'language' => $entry['language'] ?? 'de',
                    'page_count' => $entry['page_count'] ?? null,
                    'cover_url' => $entry['cover_url'] ?? null,
                    'cover_fetched_at' => isset($entry['cover_url']) ? now() : null,
                ],
            );

            $book->wasRecentlyCreated && $created++;
        }

        return $created;
    }

    /**
     * @param  array<int, array<string, mixed>>  $highlights
     */
    private function mergeHighlights(User $user, array $highlights): int
    {
        $created = 0;

        foreach (collect($highlights)->groupBy('book_title') as $title => $group) {
            $book = Book::firstOrCreate(['user_id' => $user->id, 'title' => $title]);

            $existing = $book->highlights()->pluck('id', 'hash');

            foreach ($group as $entry) {
                if ($existing->has($entry['hash'])) {
                    continue;
                }

                $book->highlights()->create([
                    'hash' => $entry['hash'],
                    'type' => $entry['type'] ?? 'highlight',
                    'content' => $entry['content'],
                    'location' => $entry['location'] ?? null,
                    'page' => $entry['page'] ?? null,
                    'anchor' => $entry['anchor'] ?? null,
                    'highlighted_at' => $this->parseDate($entry['highlighted_at'] ?? null),
                ]);

                $created++;
            }
        }

        return $created;
    }

    /**
     * Cartões novos são criados preservando uuid e timestamps; cartões já
     * existentes têm o conteúdo editável (frente/verso/contexto/mnemônico)
     * atualizado quando o snapshot é mais recente que a cópia local.
     *
     * @param  array<int, array<string, mixed>>  $cards
     * @return array{0: int, 1: int} [criados, atualizados]
     */
    private function mergeCards(User $user, array $cards): array
    {
        $created = 0;
        $updated = 0;

        $existing = $user->cards()->pluck('id', 'uuid');
        $highlightIds = Highlight::query()
            ->whereHas('book', fn ($query) => $query->where('user_id', $user->id))
            ->pluck('id', 'hash');

        foreach ($cards as $entry) {
            if ($existing->has($entry['uuid'])) {
                $updated += $this->updateCardContent($user, $entry) ? 1 : 0;

                continue;
            }

            $card = new Card([
                'user_id' => $user->id,
                'highlight_id' => isset($entry['highlight_hash']) ? $highlightIds->get($entry['highlight_hash']) : null,
                'front' => $entry['front'],
                'back' => $entry['back'],
                'context' => $entry['context'] ?? null,
                'mnemonic' => $entry['mnemonic'] ?? null,
                'due_at' => $this->parseDate($entry['due_at'] ?? null) ?? now(),
            ]);

            $card->uuid = $entry['uuid'];
            $card->created_at = $this->parseDate($entry['created_at'] ?? null) ?? now();
            $card->updated_at = $this->parseDate($entry['updated_at'] ?? null) ?? now();
            $card->save();

            $created++;
        }

        return [$created, $updated];
    }

    private function updateCardContent(User $user, array $entry): bool
    {
        $card = $user->cards()->where('uuid', $entry['uuid'])->first();
        $snapshotUpdatedAt = $this->parseDate($entry['updated_at'] ?? null);

        if (! $card || ! $snapshotUpdatedAt || $snapshotUpdatedAt->lessThanOrEqualTo($card->updated_at)) {
            return false;
        }

        $card->fill([
            'front' => $entry['front'],
            'back' => $entry['back'],
            'context' => $entry['context'] ?? null,
            'mnemonic' => $entry['mnemonic'] ?? null,
        ]);

        if (! $card->isDirty()) {
            return false;
        }

        $card->save();

        return true;
    }

    /**
     * @param  array<int, array<string, mixed>>  $reviews
     */
    private function mergeReviews(User $user, array $reviews): int
    {
        $created = 0;
        $affectedCardIds = [];

        $existing = Review::query()->where('user_id', $user->id)->pluck('id', 'uuid');
        $cardIds = $user->cards()->pluck('id', 'uuid');

        foreach ($reviews as $entry) {
            if ($existing->has($entry['uuid'])) {
                continue;
            }

            $cardId = $cardIds->get($entry['card_uuid']);

            if ($cardId === null) {
                continue;
            }

            $reviewedAt = $this->parseDate($entry['reviewed_at'] ?? null) ?? now();

            $review = new Review([
                'card_id' => $cardId,
                'user_id' => $user->id,
                'rating' => $entry['rating'],
                'interval_before' => $entry['interval_before'],
                'interval_after' => $entry['interval_after'],
                'stability_after' => $entry['stability_after'] ?? null,
                'difficulty_after' => $entry['difficulty_after'] ?? null,
            ]);

            $review->uuid = $entry['uuid'];
            $review->created_at = $reviewedAt;
            $review->updated_at = $reviewedAt;
            $review->save();

            $affectedCardIds[$cardId] = true;
            $created++;
        }

        $this->rescheduleCards(array_keys($affectedCardIds));

        return $created;
    }

    /**
     * Reconstrói o estado FSRS dos cartões que receberam revisões novas a
     * partir do histórico completo (local + importado), em ordem cronológica.
     *
     * @param  array<int, int>  $cardIds
     */
    private function rescheduleCards(array $cardIds): void
    {
        foreach (Card::query()->whereIn('id', $cardIds)->get() as $card) {
            $history = $card->reviews()
                ->orderBy('created_at')
                ->orderBy('id')
                ->get(['rating', 'created_at'])
                ->map(fn (Review $review) => [$review->rating, $review->created_at]);

            $card->forceFill($this->scheduler->replay($history))->save();
        }
    }

    private function parseDate(mixed $value): ?Carbon
    {
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
