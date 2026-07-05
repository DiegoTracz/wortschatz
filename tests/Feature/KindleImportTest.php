<?php

use App\Models\Book;
use App\Models\Highlight;
use App\Models\User;
use App\Services\KindleDriveLocator;
use Illuminate\Support\Facades\Http;

// A importação busca capas no Google Books; fake para não sair para a rede.
beforeEach(fn () => Http::fake(['www.googleapis.com/*' => Http::response(['items' => []])]));

/**
 * Cria um volume falso de Kindle (raiz com documents/My Clippings.txt) num
 * diretório temporário e devolve o caminho da raiz.
 */
function kindleVolumeWith(string $content): string
{
    $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'kindle_'.uniqid();
    mkdir($root.DIRECTORY_SEPARATOR.'documents', 0777, true);
    file_put_contents($root.DIRECTORY_SEPARATOR.'documents'.DIRECTORY_SEPARATOR.'My Clippings.txt', $content);

    return $root;
}

function kindleClippingsSample(): string
{
    return "Der Prozess (Franz Kafka)\n- Seu destaque ou posição 100-102 | Adicionado: sexta-feira, 12 de abril de 2024 11:22:33\n\nEr wusste nicht, warum.\n==========\nMomo (Michael Ende)\n- Seu destaque ou posição 200 | Adicionado: sábado, 13 de abril de 2024 09:00:00\n\nZeit ist Leben.\n==========\n";
}

test('locateIn encontra o My Clippings.txt num volume conectado', function () {
    $root = kindleVolumeWith(kindleClippingsSample());
    $locator = new KindleDriveLocator;

    $expected = $root.DIRECTORY_SEPARATOR.'documents'.DIRECTORY_SEPARATOR.'My Clippings.txt';

    expect($locator->locateIn([$root]))->toBe($expected)
        ->and($locator->locateIn([sys_get_temp_dir().DIRECTORY_SEPARATOR.'sem_kindle_'.uniqid()]))->toBeNull();
});

test('sincronizar Kindle importa direto do drive conectado', function () {
    $user = User::factory()->create();
    $path = kindleVolumeWith(kindleClippingsSample()).DIRECTORY_SEPARATOR.'documents'.DIRECTORY_SEPARATOR.'My Clippings.txt';

    $this->instance(KindleDriveLocator::class, new class($path) extends KindleDriveLocator
    {
        public function __construct(private string $path) {}

        public function locate(): ?string
        {
            return $this->path;
        }
    });

    $response = $this->actingAs($user)->post(route('import.kindle'));

    $response->assertRedirect(route('import.create'));
    $response->assertSessionHas('import_result', ['imported' => 2, 'skipped' => 0, 'books' => 2]);

    expect(Book::count())->toBe(2)->and(Highlight::count())->toBe(2);
});

test('sincronizar Kindle sem dispositivo devolve erro amigável', function () {
    $user = User::factory()->create();

    $this->instance(KindleDriveLocator::class, new class extends KindleDriveLocator
    {
        public function locate(): ?string
        {
            return null;
        }
    });

    $this->actingAs($user)
        ->post(route('import.kindle'))
        ->assertRedirect(route('import.create'))
        ->assertSessionHas('import_error');

    expect(Highlight::count())->toBe(0);
});
