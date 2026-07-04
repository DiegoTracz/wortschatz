<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesImportUser;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateImportToken extends Command
{
    use ResolvesImportUser;

    protected $signature = 'clippings:token
        {--user= : E-mail do usuário}
        {--revoke : Revoga o token em vez de gerar um novo}';

    protected $description = 'Gera (ou revoga) o token dos endpoints de importação automática';

    public function handle(): int
    {
        $user = $this->resolveUser();

        if ($user === null) {
            return self::FAILURE;
        }

        if ($this->option('revoke')) {
            $user->forceFill(['import_token' => null])->save();
            $this->info("Token de {$user->email} revogado.");

            return self::SUCCESS;
        }

        $token = Str::random(64);
        $user->forceFill(['import_token' => $token])->save();

        $this->info("Token de importação de {$user->email} (substitui o anterior; guarde agora):");
        $this->line($token);

        return self::SUCCESS;
    }
}
