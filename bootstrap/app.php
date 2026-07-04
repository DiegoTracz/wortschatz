<?php

use App\Http\Middleware\AutoLoginNativeUser;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            // No app desktop autentica o único usuário; inerte no web/testes.
            AutoLoginNativeUser::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Garante a ordem: StartSession → AutoLoginNativeUser → Authenticate.
        // Sem isso, a prioridade padrão roda `auth` antes do nosso middleware e
        // a rota redireciona pro /login antes do auto-login acontecer.
        $middleware->prependToPriorityList(
            before: Authenticate::class,
            prepend: AutoLoginNativeUser::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
