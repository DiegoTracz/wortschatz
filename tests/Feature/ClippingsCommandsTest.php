<?php

use App\Models\User;

function clippingsFixturePath(): string
{
    $content = "Der Prozess (Franz Kafka)\n- Seu destaque ou posição 100-102 | Adicionado: sexta-feira, 12 de abril de 2024 11:22:33\n\nEr wusste nicht, warum.\n==========\n";
    $path = sys_get_temp_dir().'/wortschatz-clippings-fixture.txt';
    file_put_contents($path, $content);

    return $path;
}

test('clippings:import importa para o único usuário sem precisar de --user', function () {
    $user = User::factory()->create();

    $this->artisan('clippings:import', ['path' => clippingsFixturePath()])
        ->expectsOutputToContain('Importados: 1')
        ->assertSuccessful();

    expect($user->books()->count())->toBe(1);
});

test('clippings:import falha com arquivo inexistente', function () {
    User::factory()->create();

    $this->artisan('clippings:import', ['path' => '/caminho/que/nao/existe.txt'])
        ->assertFailed();
});

test('clippings:import exige --user quando há mais de um usuário', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $this->artisan('clippings:import', ['path' => clippingsFixturePath()])
        ->assertFailed();

    $this->artisan('clippings:import', [
        'path' => clippingsFixturePath(),
        '--user' => $userB->email,
    ])->assertSuccessful();

    expect($userB->books()->count())->toBe(1)
        ->and($userA->books()->count())->toBe(0);
});

test('clippings:token gera e substitui o token', function () {
    $user = User::factory()->create();

    $this->artisan('clippings:token')->assertSuccessful();
    $first = $user->fresh()->import_token;

    $this->artisan('clippings:token')->assertSuccessful();
    $second = $user->fresh()->import_token;

    expect($first)->toHaveLength(64)
        ->and($second)->toHaveLength(64)
        ->and($second)->not->toBe($first);
});

test('clippings:token --revoke anula o token', function () {
    $user = User::factory()->create();
    $user->forceFill(['import_token' => str_repeat('a', 64)])->save();

    $this->artisan('clippings:token', ['--revoke' => true])->assertSuccessful();

    expect($user->fresh()->import_token)->toBeNull();
});
