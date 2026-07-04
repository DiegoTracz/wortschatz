<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * O app desktop (NativePHP) é single-user: não há tela de login útil quando
 * só existe um dono na máquina. Este middleware autentica automaticamente o
 * único usuário — criando-o na primeira execução — mas apenas quando o app
 * está rodando embarcado no NativePHP (`nativephp-internal.running`). No
 * servidor web tradicional e nos testes ele é inerte, preservando o fluxo de
 * login/registro e as checagens de `user_id` dos controllers.
 */
class AutoLoginNativeUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('nativephp-internal.running') && ! Auth::check()) {
            Auth::login($this->resolveSingleUser(), remember: true);
        }

        return $next($request);
    }

    protected function resolveSingleUser(): User
    {
        return User::query()->orderBy('id')->first()
            ?? User::forceCreate([
                'name' => 'Wortschatz',
                'email' => 'local@wortschatz.app',
                'password' => Hash::make(Str::random(40)),
                'email_verified_at' => now(),
            ]);
    }
}
