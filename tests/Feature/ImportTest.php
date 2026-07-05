<?php

use App\Models\Book;
use App\Models\Highlight;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

// A importação busca capas no Google Books; fake para não sair para a rede.
beforeEach(fn () => Http::fake(['www.googleapis.com/*' => Http::response(['items' => []])]));

function clippingsFile(): UploadedFile
{
    $content = "Der Prozess (Franz Kafka)\n- Seu destaque ou posição 100-102 | Adicionado: sexta-feira, 12 de abril de 2024 11:22:33\n\nEr wusste nicht, warum.\n==========\nMomo (Michael Ende)\n- Seu destaque ou posição 200 | Adicionado: sábado, 13 de abril de 2024 09:00:00\n\nZeit ist Leben.\n==========\n";

    return UploadedFile::fake()->createWithContent('My Clippings.txt', $content);
}

test('importa destaques agrupados por livro', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('import.store'), ['file' => clippingsFile()]);

    $response->assertRedirect(route('import.create'));
    $response->assertSessionHas('import_result', ['imported' => 2, 'skipped' => 0, 'books' => 2]);

    expect(Book::count())->toBe(2)
        ->and(Highlight::count())->toBe(2)
        ->and($user->books()->where('title', 'Der Prozess')->first()->author)->toBe('Franz Kafka');
});

test('reimportar o mesmo arquivo não duplica destaques', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('import.store'), ['file' => clippingsFile()]);
    $response = $this->actingAs($user)->post(route('import.store'), ['file' => clippingsFile()]);

    $response->assertSessionHas('import_result', ['imported' => 0, 'skipped' => 2, 'books' => 2]);

    expect(Highlight::count())->toBe(2);
});

test('importar busca a capa do livro no Google Books', function () {
    Http::fake(['www.googleapis.com/*' => Http::response([
        'items' => [['volumeInfo' => ['imageLinks' => ['thumbnail' => 'http://books.google.com/books/content?id=abc&edge=curl']]]],
    ])]);

    $user = User::factory()->create();
    $this->actingAs($user)->post(route('import.store'), ['file' => clippingsFile()]);

    $book = $user->books()->where('title', 'Der Prozess')->first();

    expect($book->cover_url)->toBe('https://books.google.com/books/content?id=abc')
        ->and($book->cover_fetched_at)->not->toBeNull();
});

test('exige um arquivo', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('import.create'))
        ->post(route('import.store'), [])
        ->assertSessionHasErrors('file');
});
