<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

test('traduz do idioma do livro para pt-BR', function (string $source, string $langpair) {
    Http::fake(['api.mymemory.translated.net/*' => Http::response([
        'responseData' => ['translatedText' => 'tradução'],
    ])]);

    $payload = ['text' => 'Wort'];
    if ($source !== '') {
        $payload['source'] = $source;
    }

    $this->actingAs(User::factory()->create())
        ->postJson(route('translate'), $payload)
        ->assertOk()
        ->assertJson(['translation' => 'tradução']);

    Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), "langpair={$langpair}"));
})->with([
    'alemão (padrão)' => ['', 'de|pt-BR'],
    'alemão explícito' => ['de', 'de|pt-BR'],
    'inglês' => ['en', 'en|pt-BR'],
]);

test('rejeita idioma de origem fora do conjunto', function () {
    $this->actingAs(User::factory()->create())
        ->postJson(route('translate'), ['text' => 'Wort', 'source' => 'fr'])
        ->assertUnprocessable();
});
