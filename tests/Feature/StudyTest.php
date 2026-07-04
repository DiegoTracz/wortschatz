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

    $response = $this->actingAs($user)->postJson(route('study.review', $card), ['rating' => 4]);

    $response->assertOk()
        ->assertJsonPath('card.repetitions', 1)
        ->assertJsonPath('card.interval_days', 1)
        ->assertJsonPath('remaining', 0);

    $card->refresh();
    expect($card->due_at->isTomorrow())->toBeTrue()
        ->and($card->reviews()->count())->toBe(1)
        ->and($card->reviews()->first()->rating)->toBe(4);
});

test('rejeita notas fora da escala', function () {
    $user = User::factory()->create();
    $card = createCard($user);

    $this->actingAs($user)
        ->postJson(route('study.review', $card), ['rating' => 2])
        ->assertUnprocessable();
});

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
