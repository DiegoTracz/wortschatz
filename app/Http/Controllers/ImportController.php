<?php

namespace App\Http\Controllers;

use App\Services\ClippingsImporter;
use App\Services\KindleClippingsParser;
use App\Services\KindleDriveLocator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Native\Laravel\Facades\Notification;

class ImportController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Import');
    }

    public function store(Request $request, KindleClippingsParser $parser, ClippingsImporter $importer): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240'],
        ], [], ['file' => 'arquivo']);

        $entries = $parser->parse($request->file('file')->get());

        $result = $importer->import($request->user(), $entries);

        return redirect()->route('import.create')->with('import_result', $result);
    }

    /**
     * Importação direta no app desktop: lê o My Clippings.txt do Kindle
     * conectado por USB, sem upload. Reusa parser + importer; dispara uma
     * notificação nativa com o resultado.
     */
    public function kindle(Request $request, KindleDriveLocator $locator, KindleClippingsParser $parser, ClippingsImporter $importer): RedirectResponse
    {
        $path = $locator->locate();

        if ($path === null) {
            return redirect()->route('import.create')->with(
                'import_error',
                'Nenhum Kindle encontrado. Conecte-o pelo cabo USB e tente de novo.',
            );
        }

        $entries = $parser->parse((string) file_get_contents($path));

        $result = $importer->import($request->user(), $entries);

        $this->notifyResult($result);

        return redirect()->route('import.create')->with('import_result', $result);
    }

    /**
     * Notificação nativa com o resultado — só faz sentido (e só existe a ponte)
     * quando o app roda embarcado no NativePHP.
     */
    private function notifyResult(array $result): void
    {
        if (! config('nativephp-internal.running')) {
            return;
        }

        Notification::title('Kindle sincronizado')
            ->message("{$result['imported']} novo(s) destaque(s) em {$result['books']} livro(s).")
            ->show();
    }
}
