<?php

use App\Models\Card;
use App\Services\FsrsScheduler;

function fsrsCard(array $attributes = []): Card
{
    return new Card([
        'front' => 'der Hund',
        'back' => 'o cachorro',
        'interval_days' => 0,
        'repetitions' => 0,
        'lapses' => 0,
        'due_at' => now(),
        ...$attributes,
    ]);
}

test('primeira revisão define o estado inicial pelos parâmetros do FSRS-4.5', function () {
    $scheduler = new FsrsScheduler;

    $good = fsrsCard();
    $scheduler->apply($good, FsrsScheduler::GOOD);
    expect($good->stability)->toEqualWithDelta(3.7145, 0.0001)
        ->and($good->difficulty)->toEqualWithDelta(5.1618, 0.0001)
        ->and($good->interval_days)->toBe(4)
        ->and($good->repetitions)->toBe(1)
        ->and($good->due_at->isSameDay(today()->addDays(4)))->toBeTrue();

    $easy = fsrsCard();
    $scheduler->apply($easy, FsrsScheduler::EASY);
    expect($easy->interval_days)->toBe(14);

    $hard = fsrsCard();
    $scheduler->apply($hard, FsrsScheduler::HARD);
    expect($hard->interval_days)->toBe(1);
});

test('errar agenda para a mesma sessão e aumenta a dificuldade', function () {
    $scheduler = new FsrsScheduler;
    $card = fsrsCard();

    $scheduler->apply($card, FsrsScheduler::AGAIN);

    expect($card->interval_days)->toBe(0)
        ->and($card->lapses)->toBe(1)
        ->and($card->repetitions)->toBe(0)
        ->and($card->stability)->toEqualWithDelta(0.4872, 0.0001)
        ->and($card->difficulty)->toBeGreaterThan(5.1618)
        ->and($card->due_at->isToday())->toBeTrue();
});

test('acertar aumenta a estabilidade, e mais ainda quando a revisão foi mais espaçada', function () {
    $scheduler = new FsrsScheduler;

    $recent = fsrsCard(['stability' => 5.0, 'difficulty' => 5.0, 'last_reviewed_at' => now()->subDays(2)]);
    $spaced = fsrsCard(['stability' => 5.0, 'difficulty' => 5.0, 'last_reviewed_at' => now()->subDays(20)]);

    $scheduler->apply($recent, FsrsScheduler::GOOD);
    $scheduler->apply($spaced, FsrsScheduler::GOOD);

    expect($recent->stability)->toBeGreaterThan(5.0)
        ->and($spaced->stability)->toBeGreaterThan($recent->stability);
});

test('esquecer derruba a estabilidade sem nunca aumentá-la', function () {
    $scheduler = new FsrsScheduler;
    $card = fsrsCard(['stability' => 20.0, 'difficulty' => 5.0, 'last_reviewed_at' => now()->subDays(20)]);

    $scheduler->apply($card, FsrsScheduler::AGAIN);

    expect($card->stability)->toBeLessThan(20.0)
        ->and($card->difficulty)->toBeGreaterThan(5.0)
        ->and($card->interval_days)->toBe(0);
});

test('a dificuldade fica limitada entre 1 e 10', function () {
    $scheduler = new FsrsScheduler;

    $hardest = fsrsCard(['stability' => 2.0, 'difficulty' => 9.9, 'last_reviewed_at' => now()->subDay()]);
    $scheduler->apply($hardest, FsrsScheduler::AGAIN);
    expect($hardest->difficulty)->toBeLessThanOrEqual(10.0);

    $easiest = fsrsCard(['stability' => 2.0, 'difficulty' => 1.1, 'last_reviewed_at' => now()->subDay()]);
    $scheduler->apply($easiest, FsrsScheduler::EASY);
    expect($easiest->difficulty)->toBeGreaterThanOrEqual(1.0);
});

test('o intervalo respeita o teto configurado', function () {
    config(['srs.max_interval' => 365]);

    $scheduler = new FsrsScheduler;
    $card = fsrsCard(['stability' => 10000.0, 'difficulty' => 3.0, 'last_reviewed_at' => now()->subDays(300)]);

    $scheduler->apply($card, FsrsScheduler::GOOD);

    expect($card->interval_days)->toBe(365);
});

test('a prévia mostra o intervalo de cada resposta sem alterar o cartão', function () {
    $scheduler = new FsrsScheduler;
    $card = fsrsCard();

    $previews = $scheduler->previewIntervals($card);

    expect($previews)->toBe([
        FsrsScheduler::AGAIN => 0,
        FsrsScheduler::HARD => 1,
        FsrsScheduler::GOOD => 4,
        FsrsScheduler::EASY => 14,
    ])->and($card->stability)->toBeNull();
});

test('replay reconstrói o estado a partir do histórico de revisões', function () {
    $scheduler = new FsrsScheduler;

    $state = $scheduler->replay([
        [FsrsScheduler::GOOD, now()->subDays(10)],
        [FsrsScheduler::GOOD, now()->subDays(6)],
        [FsrsScheduler::AGAIN, now()->subDay()],
    ]);

    expect($state['repetitions'])->toBe(2)
        ->and($state['lapses'])->toBe(1)
        ->and($state['interval_days'])->toBe(0)
        ->and($state['stability'])->not->toBeNull()
        ->and($state['last_reviewed_at']->isYesterday())->toBeTrue();
});
