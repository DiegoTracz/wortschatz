<?php

use App\Models\Book;
use App\Models\User;
use App\Services\KindleCoverResolver;
use App\Services\KindleDriveLocator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

// A importação ainda tenta capa online para livro novo; fake para não sair à rede.
beforeEach(function () {
    Http::fake(['www.googleapis.com/*' => Http::response(['items' => []])]);
    Storage::fake('local');
});

/**
 * Cria um volume falso de Kindle (com system/thumbnails e documents/Downloads/Items01)
 * e devolve a raiz.
 */
function kindleCoverVolume(): string
{
    $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'kindlecover_'.uniqid();
    mkdir($root.'/documents/Downloads/Items01', 0777, true);
    mkdir($root.'/system/thumbnails', 0777, true);

    return $root;
}

/** Adiciona uma pasta .sdr "<título> -_<ASIN>" e, opcionalmente, a miniatura do ASIN. */
function kindleCoverAddBook(string $root, string $sdrTitle, string $asin, bool $withThumbnail = true): void
{
    mkdir($root.'/documents/Downloads/Items01/'.$sdrTitle.' -_'.$asin.'.sdr', 0777, true);

    if ($withThumbnail) {
        file_put_contents($root.'/system/thumbnails/thumbnail_'.$asin.'_EBOK_portrait.jpg', 'JPG-'.$asin);
    }
}

test('syncCovers casa a capa por título (exato e por prefixo) e grava no storage', function () {
    $root = kindleCoverVolume();
    kindleCoverAddBook($root, 'Tintenherz', 'B01AAAAAAA');
    kindleCoverAddBook($root, 'Nordische Mythologie_ Ein fesselnder Überblick', 'B0BY5BXZRY');

    $user = User::factory()->create();
    $exact = Book::create(['user_id' => $user->id, 'title' => 'Tintenherz']);
    $prefix = Book::create(['user_id' => $user->id, 'title' => 'Nordische Mythologie']);

    $applied = app(KindleCoverResolver::class)->syncCovers($user, $root);

    expect($applied)->toBe(2)
        ->and($exact->refresh()->cover_url)->toBe('/livros/'.$exact->id.'/capa.jpg')
        ->and($prefix->refresh()->cover_url)->toBe('/livros/'.$prefix->id.'/capa.jpg')
        ->and(Storage::disk('local')->get("covers/{$exact->id}.jpg"))->toBe('JPG-B01AAAAAAA');
});

test('syncCovers ignora livro sem pasta no Kindle ou sem miniatura', function () {
    $root = kindleCoverVolume();
    kindleCoverAddBook($root, 'Kotlin em ação', 'B075SM5LKG', withThumbnail: false); // pasta sem thumb
    kindleCoverAddBook($root, 'Percy Jackson 3 Der Fluch', 'B004Z5X5YO');             // outro número

    $user = User::factory()->create();
    $semPasta = Book::create(['user_id' => $user->id, 'title' => 'Momo']);
    $semThumb = Book::create(['user_id' => $user->id, 'title' => 'Kotlin em ação']);
    $numeroDiferente = Book::create(['user_id' => $user->id, 'title' => 'Percy Jackson 2']);

    $applied = app(KindleCoverResolver::class)->syncCovers($user, $root);

    expect($applied)->toBe(0)
        ->and($semPasta->refresh()->cover_url)->toBeNull()
        ->and($semThumb->refresh()->cover_url)->toBeNull()
        ->and($numeroDiferente->refresh()->cover_url)->toBeNull();
});

test('syncCovers extrai a capa do .kfx quando não há miniatura Amazon', function () {
    $root = kindleCoverVolume();
    $cover = minimalJpeg(400, 600);
    // Livro sideloaded: .kfx com capa embutida e sem miniatura no system/thumbnails.
    file_put_contents($root.'/documents/Downloads/Items01/Tintenherz_ABCDEF0123456789.kfx', 'KFX'.$cover);

    $user = User::factory()->create();
    $book = Book::create(['user_id' => $user->id, 'title' => 'Tintenherz']);

    $applied = app(KindleCoverResolver::class)->syncCovers($user, $root);

    expect($applied)->toBe(1)
        ->and($book->refresh()->cover_url)->toBe('/livros/'.$book->id.'/capa.jpg')
        ->and(Storage::disk('local')->get("covers/{$book->id}.jpg"))->toBe($cover);
});

test('a miniatura Amazon tem prioridade sobre a extração do .kfx', function () {
    $root = kindleCoverVolume();
    kindleCoverAddBook($root, 'Tintenherz', 'B01AAAAAAA'); // miniatura pronta
    file_put_contents($root.'/documents/Downloads/Items01/Tintenherz_ABCDEF0123456789.kfx', 'KFX'.minimalJpeg(400, 600));

    $user = User::factory()->create();
    $book = Book::create(['user_id' => $user->id, 'title' => 'Tintenherz']);

    app(KindleCoverResolver::class)->syncCovers($user, $root);

    // Vem da miniatura (conteúdo "JPG-<ASIN>"), não do KFX.
    expect(Storage::disk('local')->get("covers/{$book->id}.jpg"))->toBe('JPG-B01AAAAAAA');
});

test('syncCovers não sobrescreve capa já existente', function () {
    $root = kindleCoverVolume();
    kindleCoverAddBook($root, 'Tintenherz', 'B01AAAAAAA');

    $user = User::factory()->create();
    $book = Book::create(['user_id' => $user->id, 'title' => 'Tintenherz', 'cover_url' => 'https://existente/capa.jpg']);

    app(KindleCoverResolver::class)->syncCovers($user, $root);

    expect($book->refresh()->cover_url)->toBe('https://existente/capa.jpg');
});

test('a rota serve a capa ao dono e nega a terceiros', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $book = Book::create(['user_id' => $owner->id, 'title' => 'Tintenherz']);
    Storage::disk('local')->put("covers/{$book->id}.jpg", 'JPG-BYTES');

    $this->actingAs($owner)->get(route('books.cover.image', $book))
        ->assertOk()
        ->assertHeader('content-type', 'image/jpeg');

    $this->actingAs($other)->get(route('books.cover.image', $book))->assertForbidden();
});

test('a rota devolve 404 quando o arquivo da capa não existe', function () {
    $user = User::factory()->create();
    $book = Book::create(['user_id' => $user->id, 'title' => 'Sem Capa']);

    $this->actingAs($user)->get(route('books.cover.image', $book))->assertNotFound();
});

test('sincronizar Kindle preenche as capas locais após importar', function () {
    $root = kindleCoverVolume();
    kindleCoverAddBook($root, 'Tintenherz', 'B01AAAAAAA');
    $clippings = "Tintenherz (Cornelia Funke)\n- Seu destaque ou posição 100-102 | Adicionado: sexta-feira, 12 de abril de 2024 11:22:33\n\nEr las weiter.\n==========\n";
    file_put_contents($root.'/documents/My Clippings.txt', $clippings);

    $path = $root.'/documents/My Clippings.txt';
    $this->instance(KindleDriveLocator::class, new class($path) extends KindleDriveLocator
    {
        public function __construct(private string $path) {}

        public function locate(): ?string
        {
            return $this->path;
        }
    });

    $user = User::factory()->create();
    $this->actingAs($user)->post(route('import.kindle'))->assertRedirect(route('import.create'));

    $book = $user->books()->where('title', 'Tintenherz')->first();

    expect($book->cover_url)->toBe('/livros/'.$book->id.'/capa.jpg')
        ->and(Storage::disk('local')->exists("covers/{$book->id}.jpg"))->toBeTrue();
});
