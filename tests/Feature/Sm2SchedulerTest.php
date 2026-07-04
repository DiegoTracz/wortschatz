<?php

use App\Models\Card;
use App\Services\Sm2Scheduler;

function makeCard(array $attributes = []): Card
{
    return new Card([
        'front' => 'der Hund',
        'back' => 'o cachorro',
        'ease_factor' => 2.5,
        'interval_days' => 0,
        'repetitions' => 0,
        'lapses' => 0,
        'due_at' => now(),
        ...$attributes,
    ]);
}

test('a progressão de intervalos segue o SM-2: 1, 6, depois intervalo × fator', function () {
    $scheduler = new Sm2Scheduler;
    $card = makeCard();

    $scheduler->apply($card, Sm2Scheduler::GOOD);
    expect($card->repetitions)->toBe(1)->and($card->interval_days)->toBe(1);

    $scheduler->apply($card, Sm2Scheduler::GOOD);
    expect($card->repetitions)->toBe(2)->and($card->interval_days)->toBe(6);

    $scheduler->apply($card, Sm2Scheduler::GOOD);
    expect($card->repetitions)->toBe(3)->and($card->interval_days)->toBe(15); // 6 × 2.5
});

test('errar reseta as repetições e agenda para a mesma sessão', function () {
    $scheduler = new Sm2Scheduler;
    $card = makeCard(['repetitions' => 3, 'interval_days' => 15]);

    $scheduler->apply($card, Sm2Scheduler::AGAIN);

    expect($card->repetitions)->toBe(0)
        ->and($card->interval_days)->toBe(0)
        ->and($card->lapses)->toBe(1)
        ->and($card->ease_factor)->toBe(1.7) // 2.5 - 0.8
        ->and($card->due_at->isToday())->toBeTrue();
});

test('difícil reduz e fácil aumenta o fator de facilidade', function () {
    $scheduler = new Sm2Scheduler;

    $hard = makeCard();
    $scheduler->apply($hard, Sm2Scheduler::HARD);
    expect($hard->ease_factor)->toBe(2.36); // 2.5 - 0.14

    $easy = makeCard();
    $scheduler->apply($easy, Sm2Scheduler::EASY);
    expect($easy->ease_factor)->toBe(2.6); // 2.5 + 0.1
});

test('o fator de facilidade nunca cai abaixo de 1.3', function () {
    $scheduler = new Sm2Scheduler;
    $card = makeCard(['ease_factor' => 1.3]);

    $scheduler->apply($card, Sm2Scheduler::AGAIN);

    expect($card->ease_factor)->toBe(1.3);
});

test('a prévia mostra o intervalo de cada resposta sem alterar o cartão', function () {
    $scheduler = new Sm2Scheduler;
    $card = makeCard(['repetitions' => 2, 'interval_days' => 6]);

    $previews = $scheduler->previewIntervals($card);

    expect($previews[Sm2Scheduler::AGAIN])->toBe(0)
        ->and($previews[Sm2Scheduler::GOOD])->toBe(15)
        ->and($previews[Sm2Scheduler::EASY])->toBe(16) // 6 × 2.6
        ->and($card->repetitions)->toBe(2)
        ->and($card->interval_days)->toBe(6);
});
