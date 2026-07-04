<?php

use App\Models\Book;
use App\Models\Highlight;
use App\Models\User;
use Illuminate\Http\UploadedFile;

function tokenUser(string $token = 'token-de-teste'): User
{
    $user = User::factory()->create();
    $user->forceFill(['import_token' => $token])->save();

    return $user;
}

function apiClippingsFile(): UploadedFile
{
    $content = "Der Prozess (Kafka, Franz)\n- Seu destaque ou posição 100-102 | Adicionado: sexta-feira, 12 de abril de 2024 11:22:33\n\nEr wusste nicht, warum.\n==========\nMomo (Michael Ende)\n- Seu destaque ou posição 200 | Adicionado: sábado, 13 de abril de 2024 09:00:00\n\nZeit ist Leben.\n==========\n";

    return UploadedFile::fake()->createWithContent('My Clippings.txt', $content);
}

test('os endpoints exigem token válido', function (string $route) {
    tokenUser();

    $this->postJson(route($route))->assertUnauthorized();
    $this->withToken('token-errado')->postJson(route($route))->assertUnauthorized();
})->with(['api.import.file', 'api.import.entries']);

test('a resposta 401 vem em JSON mesmo sem header Accept', function () {
    $this->post(route('api.import.file'))
        ->assertUnauthorized()
        ->assertJson(['message' => 'Token de importação inválido.']);
});

test('o token identifica o usuário dono dos destaques', function () {
    $userA = tokenUser('token-do-usuario-a');
    $userB = tokenUser('token-do-usuario-b');

    $this->withToken('token-do-usuario-b')
        ->postJson(route('api.import.file'), ['file' => apiClippingsFile()])
        ->assertOk()
        ->assertJson(['imported' => 2]);

    expect($userB->books()->count())->toBe(2)
        ->and($userA->books()->count())->toBe(0);
});

test('importa o arquivo e reenviá-lo não duplica', function () {
    tokenUser();

    $this->withToken('token-de-teste')
        ->postJson(route('api.import.file'), ['file' => apiClippingsFile()])
        ->assertOk()
        ->assertExactJson(['imported' => 2, 'skipped' => 0, 'books' => 2]);

    $this->withToken('token-de-teste')
        ->postJson(route('api.import.file'), ['file' => apiClippingsFile()])
        ->assertOk()
        ->assertExactJson(['imported' => 0, 'skipped' => 2, 'books' => 2]);

    expect(Highlight::count())->toBe(2);
});

test('o endpoint de arquivo exige um arquivo', function () {
    tokenUser();

    $this->withToken('token-de-teste')
        ->postJson(route('api.import.file'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');
});

test('importa entradas estruturadas', function () {
    tokenUser();

    $this->withToken('token-de-teste')
        ->postJson(route('api.import.entries'), [
            'entries' => [
                [
                    'title' => 'Der Prozess',
                    'author' => 'Franz Kafka',
                    'content' => 'Jemand musste Josef K. verleumdet haben.',
                    'location' => '25',
                    'highlighted_at' => '2024-04-12 11:22:33',
                ],
            ],
        ])
        ->assertOk()
        ->assertExactJson(['imported' => 1, 'skipped' => 0, 'books' => 1]);

    $highlight = Highlight::sole();

    expect($highlight->type)->toBe('highlight')
        ->and($highlight->highlighted_at->toDateTimeString())->toBe('2024-04-12 11:22:33')
        ->and($highlight->book->author)->toBe('Franz Kafka');
});

test('o endpoint de entradas valida título e conteúdo', function () {
    tokenUser();

    $this->withToken('token-de-teste')
        ->postJson(route('api.import.entries'), [
            'entries' => [['author' => 'Franz Kafka']],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['entries.0.title', 'entries.0.content']);
});

test('deduplica o mesmo destaque vindo do arquivo e do scraper', function () {
    tokenUser();

    $this->withToken('token-de-teste')
        ->postJson(route('api.import.file'), ['file' => apiClippingsFile()]);

    // Mesmo destaque como o Amazon Notebook o entregaria: só a localização
    // inicial (o clippings grava "100-102"), whitespace diferente e o autor
    // no formato "Nome Sobrenome" em vez de "Sobrenome, Nome".
    $this->withToken('token-de-teste')
        ->postJson(route('api.import.entries'), [
            'entries' => [
                [
                    'title' => 'Der Prozess',
                    'author' => 'Franz Kafka',
                    'content' => ' Er  wusste nicht,  warum. ',
                    'location' => '100',
                ],
            ],
        ])
        ->assertOk()
        ->assertExactJson(['imported' => 0, 'skipped' => 1, 'books' => 1]);

    expect(Highlight::count())->toBe(2)
        ->and(Book::count())->toBe(2);
});

test('computeHash normaliza localização e whitespace sem perder distinções', function () {
    $base = Highlight::computeHash('Der Prozess', 'highlight', '1234-1240', null, 'Zeit ist Leben.');

    expect(Highlight::computeHash('Der Prozess', 'highlight', '1.234-1.240', null, 'Zeit ist Leben.'))->toBe($base)
        ->and(Highlight::computeHash('Der Prozess', 'highlight', '1234', null, 'Zeit ist Leben.'))->toBe($base)
        ->and(Highlight::computeHash('Der  Prozess ', 'highlight', '1234', null, ' Zeit  ist Leben. '))->toBe($base)
        ->and(Highlight::computeHash('Der Prozess', 'highlight', '2000', null, 'Zeit ist Leben.'))->not->toBe($base)
        ->and(Highlight::computeHash('Der Prozess', 'note', '1234', null, 'Zeit ist Leben.'))->not->toBe($base);
});

test('import_token não vaza na serialização do usuário', function () {
    $user = tokenUser();

    expect($user->fresh()->toArray())->not->toHaveKey('import_token');
});
