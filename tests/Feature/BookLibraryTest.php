<?php

use App\Models\Book;
use App\Models\Highlight;
use App\Models\User;
use Illuminate\Support\Facades\Http;

function libraryBook(User $user, string $title, ?string $highlightedAt, array $attributes = []): Book
{
    $book = $user->books()->create(array_merge(['title' => $title], $attributes));

    $book->highlights()->create([
        'type' => 'highlight',
        'content' => "Destaque de {$title}",
        'location' => '100',
        'highlighted_at' => $highlightedAt,
        'hash' => Highlight::computeHash($title, 'highlight', '100', null, $title),
    ]);

    return $book;
}

test('a biblioteca ordena os livros pelos destaques mais recentes', function () {
    $user = User::factory()->create();

    libraryBook($user, 'Antigo', '2024-01-01 10:00:00');
    libraryBook($user, 'Recente', '2024-06-01 10:00:00');
    libraryBook($user, 'Meio', '2024-03-01 10:00:00');

    $this->actingAs($user)
        ->get(route('books.index'))
        ->assertInertia(fn ($page) => $page
            ->component('Books/Index')
            ->where('books.0.title', 'Recente')
            ->where('books.1.title', 'Meio')
            ->where('books.2.title', 'Antigo')
            ->where('books.0.last_highlight_at', '2024-06-01'));
});

test('o endpoint de capa busca no Google Books e persiste o resultado', function () {
    Http::fake(['www.googleapis.com/*' => Http::response([
        'items' => [['volumeInfo' => ['imageLinks' => ['thumbnail' => 'http://books.google.com/books/content?id=abc&edge=curl']]]],
    ])]);

    $user = User::factory()->create();
    $book = libraryBook($user, 'Momo', '2024-01-01 10:00:00');

    $this->actingAs($user)
        ->postJson(route('books.cover', $book))
        ->assertOk()
        ->assertJson(['cover_url' => 'https://books.google.com/books/content?id=abc']);

    expect($book->fresh()->cover_url)->toBe('https://books.google.com/books/content?id=abc')
        ->and($book->fresh()->cover_fetched_at)->not->toBeNull();
});

test('o endpoint de capa marca como buscado mesmo sem resultado', function () {
    Http::fake(['www.googleapis.com/*' => Http::response(['items' => []])]);

    $user = User::factory()->create();
    $book = libraryBook($user, 'Sem Capa', '2024-01-01 10:00:00');

    $this->actingAs($user)
        ->postJson(route('books.cover', $book))
        ->assertOk()
        ->assertJson(['cover_url' => null]);

    expect($book->fresh()->cover_fetched_at)->not->toBeNull();
});

test('não busca a capa de novo se já foi buscada', function () {
    Http::fake();

    $user = User::factory()->create();
    $book = libraryBook($user, 'Já Buscado', '2024-01-01 10:00:00', ['cover_url' => null, 'cover_fetched_at' => now()]);

    $this->actingAs($user)
        ->postJson(route('books.cover', $book))
        ->assertOk();

    Http::assertNothingSent();
});

test('não deixa buscar a capa do livro de outro usuário', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $book = libraryBook($owner, 'Privado', '2024-01-01 10:00:00');

    $this->actingAs($other)
        ->postJson(route('books.cover', $book))
        ->assertForbidden();
});

test('a página do livro traz estatísticas, mapa de palavras, distribuição e timeline', function () {
    $user = User::factory()->create();
    $book = $user->books()->create(['title' => 'Momo']);

    $first = null;
    foreach (['Die Zeit ist Leben.', 'Zeit für Zeit.'] as $i => $content) {
        $highlight = $book->highlights()->create([
            'type' => 'highlight',
            'content' => $content,
            'location' => (string) (100 + $i),
            'highlighted_at' => '2024-0'.($i + 1).'-01 10:00:00',
            'hash' => Highlight::computeHash('Momo', 'highlight', (string) (100 + $i), null, $content),
        ]);
        $first ??= $highlight;
    }
    $book->highlights()->create([
        'type' => 'note',
        'content' => 'minha anotação pessoal',
        'location' => '300',
        'hash' => Highlight::computeHash('Momo', 'note', '300', null, 'nota'),
    ]);

    // "zeit" já virou cartão → deve aparecer como has_card no mapa.
    $first->cards()->create(['user_id' => $user->id, 'front' => 'die Zeit', 'back' => 'o tempo', 'due_at' => now()]);

    $this->actingAs($user)
        ->get(route('books.show', $book))
        ->assertInertia(fn ($page) => $page
            ->component('Books/Show')
            ->where('stats.highlights', 2)
            ->where('stats.notes', 1)
            ->where('stats.cards', 1)
            ->where('stats.first_at', '2024-01-01')
            ->where('stats.last_at', '2024-02-01')
            ->where('words.0.word', 'zeit')
            ->where('words.0.count', 3)
            ->where('words.0.has_card', true)
            ->has('distribution', 20)
            ->has('timeline', 2)
            ->where('timeline.0', ['date' => '2024-01-01', 'count' => 1]));
});

test('a biblioteca exige autenticação', function () {
    $this->get(route('books.index'))->assertRedirect(route('login'));
});
