<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

function fakeOpenAi(array $payload, array $usage = ['prompt_tokens' => 1000, 'completion_tokens' => 500, 'total_tokens' => 1500]): void
{
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode($payload)]]],
            'usage' => $usage,
        ]),
    ]);
}

test('gera enriquecimento e registra tokens e custo', function () {
    config(['services.openai.token' => 'sk-test']);
    fakeOpenAi([
        'article' => '',
        'meanings' => 'diminuir, reduzir (verbo separável)',
        'examples' => ['Die Arbeitsleistung <b>nimmt</b> nach 50 Minuten <b>ab</b>.'],
        'mnemonic' => 'ab-NEM: você nega o lanche e a balança vai caindo.',
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('enrich'), [
        'word' => 'abnehmen',
        'context' => null,
    ]);

    $response->assertOk()
        ->assertJsonPath('article', '')
        ->assertJsonPath('meanings', 'diminuir, reduzir (verbo separável)')
        ->assertJsonPath('examples.0', 'Die Arbeitsleistung <b>nimmt</b> nach 50 Minuten <b>ab</b>.')
        ->assertJsonPath('mnemonic', 'ab-NEM: você nega o lanche e a balança vai caindo.')
        ->assertJsonPath('usage.model', 'gpt-4o-mini');

    // gpt-4o-mini: 1000 tokens input × 0,15/1M + 500 output × 0,60/1M = 0,00045 USD.
    expect($user->aiUsages()->count())->toBe(1);
    $usage = $user->aiUsages()->first();
    expect($usage->total_tokens)->toBe(1500)
        ->and($usage->cost)->toEqualWithDelta(0.00045, 0.0000001)
        ->and($usage->kind)->toBe('enrichment');
});

test('usa o modelo escolhido pelo usuário no cálculo do custo', function () {
    config(['services.openai.token' => 'sk-test']);
    fakeOpenAi(['meanings' => 'a', 'examples' => [], 'mnemonic' => 'b']);

    $user = User::factory()->create(['openai_model' => 'gpt-4o']);

    $this->actingAs($user)
        ->postJson(route('enrich'), ['word' => 'Haus'])
        ->assertOk()
        ->assertJsonPath('usage.model', 'gpt-4o');

    // gpt-4o: 1000 × 2,50/1M + 500 × 10/1M = 0,0075 USD.
    expect($user->aiUsages()->first()->cost)->toEqualWithDelta(0.0075, 0.0000001);
});

test('devolve o gênero do substantivo normalizado', function () {
    config(['services.openai.token' => 'sk-test']);
    fakeOpenAi([
        'article' => 'Das',
        'meanings' => 'a saudade de lugares distantes',
        'examples' => ['Ich habe <b>Fernweh</b>.'],
        'mnemonic' => 'fern (longe) + weh (dor): dor do longe.',
    ]);

    $this->actingAs(User::factory()->create())
        ->postJson(route('enrich'), ['word' => 'Fernweh'])
        ->assertOk()
        ->assertJsonPath('article', 'das');
});

test('a chave salva pelo usuário tem precedência sobre o .env', function () {
    config(['services.openai.token' => 'sk-env']);
    fakeOpenAi(['article' => '', 'meanings' => 'a', 'examples' => [], 'mnemonic' => 'b']);

    $user = User::factory()->create();
    $user->openai_api_key = 'sk-user';
    $user->save();

    $this->actingAs($user)->postJson(route('enrich'), ['word' => 'Haus'])->assertOk();

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer sk-user'));
});

test('devolve 422 quando não há chave configurada', function () {
    config(['services.openai.token' => null]);

    $this->actingAs(User::factory()->create())
        ->postJson(route('enrich'), ['word' => 'Haus'])
        ->assertStatus(422);
});

test('devolve 502 quando a OpenAI recusa a chave', function () {
    config(['services.openai.token' => 'sk-invalida']);
    Http::fake(['api.openai.com/*' => Http::response([], 401)]);

    $this->actingAs(User::factory()->create())
        ->postJson(route('enrich'), ['word' => 'Haus'])
        ->assertStatus(502);
});

test('explica falta de créditos de API (insufficient_quota)', function () {
    config(['services.openai.token' => 'sk-sem-credito']);
    Http::fake(['api.openai.com/*' => Http::response([
        'error' => ['code' => 'insufficient_quota', 'type' => 'insufficient_quota'],
    ], 429)]);

    $this->actingAs(User::factory()->create())
        ->postJson(route('enrich'), ['word' => 'Haus'])
        ->assertStatus(502)
        ->assertJson(fn ($json) => $json->where('message', fn ($m) => str_contains($m, 'créditos de API'))->etc());
});

test('exige autenticação', function () {
    $this->postJson(route('enrich'), ['word' => 'Haus'])->assertUnauthorized();
});
