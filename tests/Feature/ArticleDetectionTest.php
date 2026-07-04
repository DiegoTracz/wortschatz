<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

function wiktionaryResponse(string $genus): array
{
    return [
        'parse' => [
            'wikitext' => "== Wort ({{Sprache|Deutsch}}) ==\n{{Deutsch Substantiv Übersicht\n|Genus={$genus}\n|Nominativ Singular=Wort\n}}",
        ],
    ];
}

test('detecta o artigo pelo gênero do Wiktionary alemão', function (string $genus, string $article) {
    Http::fake(['de.wiktionary.org/*' => Http::response(wiktionaryResponse($genus))]);

    $this->actingAs(User::factory()->create())
        ->postJson(route('article.detect'), ['word' => 'Wort'.$genus])
        ->assertOk()
        ->assertJson(['article' => $article]);
})->with([
    ['m', 'der'],
    ['f', 'die'],
    ['n', 'das'],
]);

test('devolve null quando a palavra não existe no Wiktionary', function () {
    Http::fake(['de.wiktionary.org/*' => Http::response(['error' => ['code' => 'missingtitle']])]);

    $this->actingAs(User::factory()->create())
        ->postJson(route('article.detect'), ['word' => 'Xyzabc'])
        ->assertOk()
        ->assertJson(['article' => null]);
});

test('exige autenticação', function () {
    $this->postJson(route('article.detect'), ['word' => 'Hund'])->assertUnauthorized();
});
