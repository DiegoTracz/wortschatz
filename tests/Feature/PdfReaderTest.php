<?php

use App\Models\Book;
use App\Models\Highlight;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Monta um PDF mínimo, porém válido (com xref de offsets corretos), com uma
 * linha de texto por página — o suficiente para o smalot/pdfparser extrair.
 */
function buildTestPdf(array $pageTexts): string
{
    $objects = [];
    $n = count($pageTexts);
    $pageNums = [];
    for ($i = 0; $i < $n; $i++) {
        $pageNums[$i] = 4 + $i * 2;
    }

    $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $kids = implode(' ', array_map(fn ($p) => "{$p} 0 R", $pageNums));
    $objects[2] = "<< /Type /Pages /Kids [{$kids}] /Count {$n} >>";
    $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

    for ($i = 0; $i < $n; $i++) {
        $pageObj = $pageNums[$i];
        $contentObj = $pageObj + 1;
        $objects[$pageObj] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents {$contentObj} 0 R /Resources << /Font << /F1 3 0 R >> >> >>";
        $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $pageTexts[$i]);
        $stream = "BT /F1 24 Tf 72 700 Td ({$text}) Tj ET";
        $objects[$contentObj] = '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream";
    }

    ksort($objects);

    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objects as $num => $body) {
        $offsets[$num] = strlen($pdf);
        $pdf .= "{$num} 0 obj\n{$body}\nendobj\n";
    }

    $xrefOffset = strlen($pdf);
    $size = count($objects) + 1;
    $pdf .= "xref\n0 {$size}\n0000000000 65535 f \n";
    for ($num = 1; $num < $size; $num++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$num]);
    }
    $pdf .= "trailer\n<< /Size {$size} /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
}

function pdfBook(User $user, array $attributes = []): Book
{
    return $user->books()->create(array_merge([
        'title' => 'Deutsches Buch',
        'source' => 'pdf',
        'page_count' => 2,
        'cover_fetched_at' => now(),
    ], $attributes));
}

test('importar um PDF cria um livro de leitura com o texto extraído por página', function () {
    Storage::fake('local');
    $user = User::factory()->create();

    $file = UploadedFile::fake()->createWithContent('Deutsch Lesen.pdf', buildTestPdf(['Hallo Welt', 'Zweite Seite']));

    $response = $this->actingAs($user)->post(route('books.import_pdf'), ['file' => $file]);

    $book = $user->books()->firstOrFail();

    $response->assertRedirect(route('books.read', $book));

    expect($book->source)->toBe('pdf')
        ->and($book->title)->toBe('Deutsch Lesen')
        ->and($book->page_count)->toBe(2);

    Storage::disk('local')->assertExists("pdfs/{$book->id}.pdf");

    expect($book->pdfPages()->count())->toBe(2)
        ->and($book->pdfPages()->where('page', 1)->value('text'))->toContain('Hallo')
        ->and($book->pdfPages()->where('page', 2)->value('text'))->toContain('Zweite');
});

test('o import recusa arquivos que não são PDF', function () {
    Storage::fake('local');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('books.import_pdf'), ['file' => UploadedFile::fake()->create('nota.txt', 10, 'text/plain')])
        ->assertSessionHasErrors('file');

    expect($user->books()->count())->toBe(0);
});

test('marcar uma seleção no PDF cria um destaque com âncora', function () {
    $user = User::factory()->create();
    $book = pdfBook($user);

    $payload = [
        'content' => 'Fernweh',
        'page' => 3,
        'anchor' => ['page' => 3, 'rects' => [['x0' => 10, 'y0' => 700, 'x1' => 80, 'y1' => 720]]],
    ];

    $response = $this->actingAs($user)->postJson(route('highlights.store', $book), $payload);

    $response->assertOk()->assertJson(['content' => 'Fernweh', 'page' => 3]);

    $highlight = $book->highlights()->firstOrFail();
    expect($highlight->type)->toBe('highlight')
        ->and($highlight->page)->toBe('3')
        ->and($highlight->anchor['rects'][0]['x0'])->toBe(10);
});

test('remarcar a mesma seleção não duplica o destaque', function () {
    $user = User::factory()->create();
    $book = pdfBook($user);

    $payload = [
        'content' => 'Fernweh',
        'page' => 3,
        'anchor' => ['page' => 3, 'rects' => [['x0' => 10, 'y0' => 700, 'x1' => 80, 'y1' => 720]]],
    ];

    $first = $this->actingAs($user)->postJson(route('highlights.store', $book), $payload);
    $second = $this->actingAs($user)->postJson(route('highlights.store', $book), $payload);

    expect($book->highlights()->count())->toBe(1)
        ->and($first->json('id'))->toBe($second->json('id'));
});

test('não deixa marcar destaque no livro de outro usuário', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $book = pdfBook($owner);

    $this->actingAs($other)
        ->postJson(route('highlights.store', $book), [
            'content' => 'Fernweh',
            'page' => 1,
            'anchor' => ['rects' => [['x0' => 1, 'y0' => 2, 'x1' => 3, 'y1' => 4]]],
        ])
        ->assertForbidden();
});

test('um destaque de PDF vira cartão pelo fluxo comum de cartões', function () {
    $user = User::factory()->create();
    $book = pdfBook($user);
    $highlight = $book->highlights()->create([
        'type' => 'highlight',
        'content' => 'das Fernweh',
        'page' => '3',
        'anchor' => ['page' => 3, 'rects' => [['x0' => 10, 'y0' => 700, 'x1' => 80, 'y1' => 720]]],
        'hash' => Highlight::computeHash($book->title, 'highlight', '700', '3', 'das Fernweh'),
    ]);

    $this->actingAs($user)->post(route('cards.store'), [
        'front' => 'das Fernweh',
        'back' => 'saudade de lugares distantes',
        'highlight_id' => $highlight->id,
    ])->assertRedirect();

    expect($user->cards()->where('highlight_id', $highlight->id)->exists())->toBeTrue();
});

test('remover um destaque de PDF', function () {
    $user = User::factory()->create();
    $book = pdfBook($user);
    $highlight = $book->highlights()->create([
        'type' => 'highlight',
        'content' => 'Fernweh',
        'page' => '3',
        'anchor' => ['rects' => [['x0' => 10, 'y0' => 700, 'x1' => 80, 'y1' => 720]]],
        'hash' => 'abc123',
    ]);

    $this->actingAs($user)
        ->deleteJson(route('highlights.destroy', $highlight))
        ->assertOk();

    expect($book->highlights()->count())->toBe(0);
});

test('o leitor abre livros PDF e recusa livros do Kindle', function () {
    $user = User::factory()->create();
    $pdf = pdfBook($user);
    $kindle = $user->books()->create(['title' => 'Momo', 'source' => 'kindle']);

    $this->actingAs($user)
        ->get(route('books.read', $pdf))
        ->assertInertia(fn ($page) => $page->component('Books/Reader')->where('book.title', 'Deutsches Buch'));

    $this->actingAs($user)->get(route('books.read', $kindle))->assertNotFound();
});

test('a busca dentro do PDF retorna as páginas que casam', function () {
    $user = User::factory()->create();
    $book = pdfBook($user);
    $book->pdfPages()->createMany([
        ['page' => 1, 'text' => 'Hallo Welt und guten Morgen'],
        ['page' => 2, 'text' => 'Die zweite Seite mit Fernweh'],
    ]);

    $this->actingAs($user)
        ->getJson(route('books.search', ['book' => $book, 'q' => 'Fernweh']))
        ->assertOk()
        ->assertJsonCount(1, 'results')
        ->assertJsonPath('results.0.page', 2);
});

test('o download do PDF exige ser o dono', function () {
    Storage::fake('local');
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $book = pdfBook($owner);
    Storage::disk('local')->put($book->pdfPath(), '%PDF-1.4 fake');

    $this->actingAs($other)->get(route('books.file', $book))->assertForbidden();
    $this->actingAs($owner)->get(route('books.file', $book))->assertOk();
});
