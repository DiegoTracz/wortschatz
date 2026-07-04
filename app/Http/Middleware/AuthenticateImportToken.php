<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentica os endpoints stateless de importação via "Authorization: Bearer",
 * comparando com o import_token dos usuários (gerado por `clippings:token`).
 */
class AuthenticateImportToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        $user = $token === null ? null : User::whereNotNull('import_token')
            ->get()
            ->first(fn (User $user) => hash_equals($user->import_token, $token));

        abort_if($user === null, 401, 'Token de importação inválido.');

        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
