<?php

use App\Models\Book;
use App\Models\Highlight;
use App\Models\User;
use App\Services\UserDataExporter;
use App\Services\UserDataImporter;
use Illuminate\Http\UploadedFile;

/**
 * Monta um usuário com um livro, um destaque, um cartão e uma revisão — o
 * conjunto mínimo que exercita todas as coleções do snapshot de sync.
 */
function syncSeedUser(): User
{
    $user = User::factory()->create();

    $book = Book::create(['user_id' => $user->id, 'title' => 'Die Verwandlung', 'author' => 'Franz Kafka']);

    $highlight = $book->highlights()->create([
        'type' => 'highlight',
        'content' => 'Ungeziefer',
        'location' => '100',
        'hash' => Highlight::computeHash('Die Verwandlung', 'highlight', '100', null, 'Ungeziefer'),
    ]);

    $card = $user->cards()->create([
        'highlight_id' => $highlight->id,
        'front' => 'das Ungeziefer',
        'back' => 'o inseto',
        'due_at' => now()->subDay(),
    ]);

    $card->reviews()->create([
        'user_id' => $user->id,
        'rating' => 3,
        'interval_before' => 0,
        'interval_after' => 4,
        'stability_after' => 3.71,
        'difficulty_after' => 5.16,
    ]);

    return $user;
}

function syncSnapshotOf(User $user): array
{
    return app(UserDataExporter::class)->export($user);
}

test('a página de sincronização carrega com as contagens', function () {
    $user = syncSeedUser();

    $this->actingAs($user)->get(route('sync.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('Sync')
            ->where('stats.books', 1)
            ->where('stats.highlights', 1)
            ->where('stats.cards', 1)
            ->where('stats.reviews', 1));
});

test('o export baixa um snapshot completo dos dados do usuário', function () {
    $user = syncSeedUser();

    // Dados de outro usuário não podem vazar para o snapshot.
    syncSeedUser();

    $snapshot = syncSnapshotOf($user);

    expect($snapshot['format'])->toBe('wortschatz-sync')
        ->and($snapshot['version'])->toBe(1)
        ->and($snapshot['books'])->toHaveCount(1)
        ->and($snapshot['highlights'])->toHaveCount(1)
        ->and($snapshot['cards'])->toHaveCount(1)
        ->and($snapshot['reviews'])->toHaveCount(1)
        ->and($snapshot['cards'][0]['uuid'])->toBe($user->cards()->first()->uuid)
        ->and($snapshot['cards'][0]['highlight_hash'])->toBe($user->books()->first()->highlights()->first()->hash);

    $response = $this->actingAs($user)->get(route('sync.export'));

    $response->assertSuccessful();
    expect($response->headers->get('content-disposition'))->toContain('wortschatz-');
});

test('importar em uma conta vazia recria tudo e reagenda pelo histórico', function () {
    $source = syncSeedUser();
    $snapshot = syncSnapshotOf($source);

    $target = User::factory()->create();
    app(UserDataImporter::class)->import($target, $snapshot);

    $card = $target->cards()->first();
    $sourceCard = $source->cards()->first();

    expect($target->books()->count())->toBe(1)
        ->and($card)->not->toBeNull()
        ->and($card->uuid)->toBe($sourceCard->uuid)
        ->and($card->highlight->hash)->toBe($sourceCard->highlight->hash)
        ->and($card->reviews()->count())->toBe(1)
        // Estado FSRS reconstruído do histórico, não copiado do snapshot.
        ->and($card->stability)->not->toBeNull()
        ->and($card->repetitions)->toBe(1)
        ->and($card->due_at->isFuture())->toBeTrue();
});

test('importar o mesmo snapshot duas vezes não cria nada novo', function () {
    $source = syncSeedUser();
    $snapshot = syncSnapshotOf($source);

    $target = User::factory()->create();
    $importer = app(UserDataImporter::class);

    $importer->import($target, $snapshot);
    $second = $importer->import($target, $snapshot);

    expect($second)->toBe(['books' => 0, 'highlights' => 0, 'cards' => 0, 'cards_updated' => 0, 'reviews' => 0])
        ->and($target->cards()->count())->toBe(1)
        ->and($target->cards()->first()->reviews()->count())->toBe(1);
});

test('históricos divergentes convergem: revisões novas somam e o replay refaz o agendamento', function () {
    $source = syncSeedUser();
    $sourceCard = $source->cards()->first();

    // A "outra máquina" já tem o mesmo cartão (mesmo uuid) com a mesma revisão…
    $target = User::factory()->create();
    $importer = app(UserDataImporter::class);
    $importer->import($target, syncSnapshotOf($source));

    // …e a máquina de origem ganha uma revisão nova depois disso.
    $this->travel(2)->days();
    $sourceCard->reviews()->create([
        'user_id' => $source->id,
        'rating' => 4,
        'interval_before' => 4,
        'interval_after' => 10,
        'stability_after' => 10.0,
        'difficulty_after' => 4.5,
    ]);

    $result = $importer->import($target, syncSnapshotOf($source));

    $card = $target->cards()->first();

    expect($result['reviews'])->toBe(1)
        ->and($card->reviews()->count())->toBe(2)
        ->and($card->repetitions)->toBe(2)
        ->and($card->last_reviewed_at->isSameDay(now()))->toBeTrue();
});

test('edição mais recente do cartão no snapshot atualiza o conteúdo local', function () {
    $source = syncSeedUser();
    $target = User::factory()->create();
    $importer = app(UserDataImporter::class);

    $importer->import($target, syncSnapshotOf($source));

    $this->travel(1)->hour();
    $source->cards()->first()->update(['front' => 'das Ungeziefer, -']);

    $result = $importer->import($target, syncSnapshotOf($source));

    expect($result['cards_updated'])->toBe(1)
        ->and($target->cards()->first()->front)->toBe('das Ungeziefer, -');
});

test('o import via upload valida o arquivo e mostra o resultado', function () {
    $source = syncSeedUser();
    $snapshot = syncSnapshotOf($source);

    $target = User::factory()->create();

    $file = UploadedFile::fake()->createWithContent('wortschatz-2026-07-16.json', json_encode($snapshot));

    $this->actingAs($target)
        ->post(route('sync.import'), ['file' => $file])
        ->assertRedirect(route('sync.index'))
        ->assertSessionHas('sync_result');

    expect($target->cards()->count())->toBe(1);
});

test('arquivo que não é um export do Wortschatz é rejeitado com mensagem', function () {
    $user = User::factory()->create();

    $file = UploadedFile::fake()->createWithContent('outro.json', json_encode(['foo' => 'bar']));

    $this->actingAs($user)
        ->post(route('sync.import'), ['file' => $file])
        ->assertRedirect(route('sync.index'))
        ->assertSessionHas('sync_error');
});
