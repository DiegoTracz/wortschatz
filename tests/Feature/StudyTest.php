<?php

use App\Models\Card;
use App\Models\User;

function createCard(User $user, array $attributes = []): Card
{
    return $user->cards()->create([
        'front' => 'der Hund',
        'back' => 'o cachorro',
        'due_at' => now()->subMinute(),
        ...$attributes,
    ]);
}

test('revisar um cartão atualiza o agendamento e registra a revisão', function () {
    $user = User::factory()->create();
    $card = createCard($user);

    $response = $this->actingAs($user)->postJson(route('study.review', $card), ['rating' => 3]);

    $response->assertOk()
        ->assertJsonPath('card.repetitions', 1)
        ->assertJsonPath('card.interval_days', 4) // estabilidade inicial do "bom" no FSRS ≈ 3.71 dias
        ->assertJsonPath('remaining', 0);

    $card->refresh();
    expect($card->due_at->isSameDay(today()->addDays(4)))->toBeTrue()
        ->and($card->stability)->not->toBeNull()
        ->and($card->reviews()->count())->toBe(1)
        ->and($card->reviews()->first()->rating)->toBe(3)
        ->and($card->reviews()->first()->stability_after)->toEqualWithDelta($card->stability, 0.0001);
});

test('rejeita notas fora da escala', function (int $rating) {
    $user = User::factory()->create();
    $card = createCard($user);

    $this->actingAs($user)
        ->postJson(route('study.review', $card), ['rating' => $rating])
        ->assertUnprocessable();
})->with([0, 5]);

test('não permite revisar cartão de outro usuário', function () {
    $owner = User::factory()->create();
    $card = createCard($owner);

    $this->actingAs(User::factory()->create())
        ->postJson(route('study.review', $card), ['rating' => 4])
        ->assertForbidden();
});

test('a tela de estudo lista apenas cartões vencidos', function () {
    $user = User::factory()->create();
    createCard($user); // vencido
    createCard($user, ['front' => 'die Katze', 'due_at' => now()->addDays(3)]); // futuro

    $this->actingAs($user)
        ->get(route('study.index'))
        ->assertInertia(fn ($page) => $page->component('Study')->has('cards', 1));
});
