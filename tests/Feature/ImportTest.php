<?php

use App\Models\Book;
use App\Models\Highlight;
use App\Models\User;
use Illuminate\Http\UploadedFile;

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

test('exige um arquivo', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('import.create'))
        ->post(route('import.store'), [])
        ->assertSessionHasErrors('file');
});
