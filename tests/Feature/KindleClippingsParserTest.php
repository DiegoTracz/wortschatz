<?php

use App\Services\KindleClippingsParser;

function clippingsSample(): string
{
    $entries = [
        // Kindle em português (destaque)
        "Der Prozess (Franz Kafka)\r\n- Seu destaque ou posição 1234-1236 | Adicionado: sexta-feira, 12 de abril de 2024 11:22:33\r\n\r\nDer Hund lief schnell über die Straße.",
        // Kindle em inglês (highlight com página e posição)
        "Die Verwandlung (Kafka, Franz)\r\n- Your Highlight on page 45 | Location 678-680 | Added on Friday, April 12, 2024 11:22:33 AM\r\n\r\nAls Gregor Samsa eines Morgens aus unruhigen Träumen erwachte.",
        // Kindle em alemão (nota)
        "Momo (Michael Ende)\r\n- Ihre Notiz bei Position 99 | Hinzugefügt am Freitag, 12. April 2024 11:22:33\r\n\r\nGrammatik: Verb am Ende des Nebensatzes.",
        // Marcador de página (deve ser ignorado)
        "Momo (Michael Ende)\r\n- Seu marcador ou posição 55 | Adicionado: quinta-feira, 11 de abril de 2024 10:00:00\r\n",
    ];

    return "\xEF\xBB\xBF".implode("\r\n==========\r\n", $entries)."\r\n==========\r\n";
}

test('faz o parse de destaques em português, inglês e alemão', function () {
    $entries = app(KindleClippingsParser::class)->parse(clippingsSample());

    expect($entries)->toHaveCount(3);

    expect($entries[0])
        ->title->toBe('Der Prozess')
        ->author->toBe('Franz Kafka')
        ->type->toBe('highlight')
        ->location->toBe('1234-1236')
        ->content->toBe('Der Hund lief schnell über die Straße.');
    expect($entries[0]['highlighted_at']->format('Y-m-d H:i:s'))->toBe('2024-04-12 11:22:33');

    expect($entries[1])
        ->title->toBe('Die Verwandlung')
        ->author->toBe('Kafka, Franz')
        ->type->toBe('highlight')
        ->page->toBe('45')
        ->location->toBe('678-680');
    expect($entries[1]['highlighted_at']->format('Y-m-d'))->toBe('2024-04-12');

    expect($entries[2])
        ->title->toBe('Momo')
        ->author->toBe('Michael Ende')
        ->type->toBe('note')
        ->location->toBe('99');
});

test('gera o mesmo hash para a mesma entrada (deduplicação)', function () {
    $parser = app(KindleClippingsParser::class);

    $first = $parser->parse(clippingsSample());
    $second = $parser->parse(clippingsSample());

    expect($first[0]['hash'])->toBe($second[0]['hash'])
        ->and($first->pluck('hash')->unique())->toHaveCount(3);
});

test('ignora blocos vazios ou malformados', function () {
    $entries = app(KindleClippingsParser::class)->parse("==========\r\nsó uma linha\r\n==========\r\n");

    expect($entries)->toBeEmpty();
});

test('lida com título sem autor', function () {
    $entries = app(KindleClippingsParser::class)->parse(
        "Deutsche Grammatik\n- Your Highlight on Location 10-11 | Added on Monday, January 1, 2024 09:00:00 AM\n\nder Wortschatz\n==========\n"
    );

    expect($entries[0])->title->toBe('Deutsche Grammatik')->author->toBeNull();
});
