<?php

use App\Services\WordFrequencyAnalyzer;

test('conta as palavras mais frequentes ignorando stopwords', function () {
    $words = (new WordFrequencyAnalyzer)->analyze([
        'Die Zeit ist Leben und die Zeit vergeht.',
        'Zeit für Kaffee.',
    ]);

    // "zeit" aparece 3×; "die"/"ist"/"und"/"für" são stopwords e ficam de fora.
    expect($words[0])->toBe(['word' => 'zeit', 'count' => 3])
        ->and(collect($words)->pluck('word'))->toContain('leben', 'vergeht', 'kaffee')
        ->and(collect($words)->pluck('word'))->not->toContain('die', 'und', 'ist', 'für');
});

test('normaliza maiúsculas e descarta tokens curtos e números', function () {
    $words = (new WordFrequencyAnalyzer)->analyze(['Haus haus HAUS 42 ab Katze']);

    expect(collect($words)->firstWhere('word', 'haus'))->toBe(['word' => 'haus', 'count' => 3])
        ->and(collect($words)->pluck('word'))->not->toContain('42', 'ab');
});

test('respeita o limite de palavras retornadas', function () {
    $words = (new WordFrequencyAnalyzer)->analyze(['alpha beta gamma delta epsilon'], 2);

    expect($words)->toHaveCount(2);
});
