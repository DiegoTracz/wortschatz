<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

test('a página de IA carrega com uso, status da chave e câmbio', function () {
    config(['services.openai.token' => 'sk-env']);
    Http::fake(['economia.awesomeapi.com.br/*' => Http::response(['USDBRL' => ['bid' => '5.25']])]);

    $user = User::factory()->create();
    $user->aiUsages()->create([
        'model' => 'gpt-4o-mini',
        'prompt_tokens' => 1000,
        'completion_tokens' => 500,
        'total_tokens' => 1500,
        'cost' => 0.00045,
    ]);

    $this->actingAs($user)
        ->get(route('ai.edit'))
        ->assertInertia(fn ($page) => $page
            ->component('Ai')
            ->where('configured', true)
            ->where('has_custom_key', false)
            ->where('env_key', true)
            ->where('usage.total.requests', 1)
            ->where('usage.total.tokens', 1500)
            ->where('usd_brl', 5.25)
            ->has('models')
            ->has('usage.recent', 1)
        );
});

test('usa o câmbio de reserva quando a cotação falha', function () {
    config(['ai.usd_brl_fallback' => 5.4]);
    Http::fake(['economia.awesomeapi.com.br/*' => Http::response('', 500)]);

    $this->actingAs(User::factory()->create())
        ->get(route('ai.edit'))
        ->assertInertia(fn ($page) => $page->component('Ai')->where('usd_brl', 5.4));
});

test('salvar escolhe o modelo do usuário', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('ai.update'), ['model' => 'gpt-4o'])
        ->assertRedirect(route('ai.edit'));

    expect($user->refresh()->openai_model)->toBe('gpt-4o');
});

test('rejeita modelo desconhecido', function () {
    $this->actingAs(User::factory()->create())
        ->patch(route('ai.update'), ['model' => 'gpt-inexistente'])
        ->assertSessionHasErrors('model');
});

test('salva a chave da OpenAI criptografada', function () {
    config(['services.openai.token' => null]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('ai.update'), ['model' => 'gpt-4o-mini', 'api_key' => 'sk-user-123'])
        ->assertRedirect(route('ai.edit'));

    // O cast 'encrypted' decodifica de volta; o valor cru no banco é diferente.
    expect($user->refresh()->openai_api_key)->toBe('sk-user-123')
        ->and($user->getRawOriginal('openai_api_key'))->not->toBe('sk-user-123');
});

test('manter em branco não apaga a chave já salva', function () {
    $user = User::factory()->create();
    $user->openai_api_key = 'sk-antiga';
    $user->save();

    $this->actingAs($user)->patch(route('ai.update'), ['model' => 'gpt-4o', 'api_key' => '']);

    expect($user->refresh()->openai_api_key)->toBe('sk-antiga');
});

test('remover a chave salva volta a usar o .env', function () {
    $user = User::factory()->create();
    $user->openai_api_key = 'sk-antiga';
    $user->save();

    $this->actingAs($user)->patch(route('ai.update'), ['model' => 'gpt-4o', 'remove_key' => true]);

    expect($user->refresh()->openai_api_key)->toBeNull();
});
