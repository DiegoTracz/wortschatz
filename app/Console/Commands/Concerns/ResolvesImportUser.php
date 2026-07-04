<?php

namespace App\Console\Commands\Concerns;

use App\Models\User;

trait ResolvesImportUser
{
    /**
     * Resolve o usuário alvo: `--user=email` quando informado; sem a opção,
     * só funciona se houver exatamente um usuário (o caso do app na prática).
     */
    private function resolveUser(): ?User
    {
        $email = $this->option('user');

        if ($email !== null) {
            $user = User::where('email', $email)->first();

            if ($user === null) {
                $this->error("Usuário {$email} não encontrado.");
            }

            return $user;
        }

        $count = User::count();

        if ($count === 1) {
            return User::sole();
        }

        $this->error($count === 0
            ? 'Nenhum usuário cadastrado.'
            : 'Há mais de um usuário; informe --user=email.');

        return null;
    }
}
