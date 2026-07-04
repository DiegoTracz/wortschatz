<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesImportUser;
use App\Services\ClippingsImporter;
use App\Services\KindleClippingsParser;
use Illuminate\Console\Command;

class ImportClippings extends Command
{
    use ResolvesImportUser;

    protected $signature = 'clippings:import
        {path : Caminho do My Clippings.txt}
        {--user= : E-mail do usuário dono dos destaques}';

    protected $description = 'Importa um arquivo My Clippings.txt do Kindle';

    public function handle(KindleClippingsParser $parser, ClippingsImporter $importer): int
    {
        $user = $this->resolveUser();

        if ($user === null) {
            return self::FAILURE;
        }

        $path = $this->argument('path');

        if (! is_file($path)) {
            $this->error("Arquivo não encontrado: {$path}");

            return self::FAILURE;
        }

        $result = $importer->import($user, $parser->parse((string) file_get_contents($path)));

        $this->info("Importados: {$result['imported']} · Ignorados: {$result['skipped']} · Livros: {$result['books']}");

        return self::SUCCESS;
    }
}
