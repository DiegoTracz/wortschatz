<?php

namespace App\Providers;

use Illuminate\Support\Facades\Artisan;
use Native\Laravel\Contracts\ProvidesPhpIni;
use Native\Laravel\Facades\Window;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    /**
     * Executed once the native application has been booted.
     * Use this method to open windows, register global shortcuts, etc.
     */
    public function boot(): void
    {
        // O banco do usuário fica no diretório de dados do app e persiste entre
        // atualizações — então updates que trazem migrations novas precisam
        // aplicá-las no boot, senão a tela que usa a tabela nova dá 500.
        // Idempotente: só roda as migrations pendentes.
        Artisan::call('migrate', ['--force' => true]);

        Window::open()
            ->title('Wortschatz')
            ->route('dashboard')
            ->width(1280)
            ->height(860)
            ->minWidth(1024)
            ->minHeight(680)
            ->rememberState();
    }

    /**
     * Return an array of php.ini directives to be set.
     */
    public function phpIni(): array
    {
        // O PHP embutido sobe com os defaults (post_max_size 8M), que barram o
        // upload de PDFs — a validação do import aceita até 100M, então os
        // limites do runtime precisam acompanhar.
        return [
            'upload_max_filesize' => '100M',
            'post_max_size' => '110M',
            'memory_limit' => '512M',
        ];
    }
}
