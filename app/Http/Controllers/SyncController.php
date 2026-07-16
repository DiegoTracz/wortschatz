<?php

namespace App\Http\Controllers;

use App\Models\Highlight;
use App\Models\Review;
use App\Services\UserDataExporter;
use App\Services\UserDataImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sincronização manual entre máquinas: exporta um snapshot JSON dos dados do
 * usuário (para guardar no Google Drive ou similar) e importa o snapshot de
 * outra máquina com merge idempotente (UserDataImporter).
 */
class SyncController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Sync', [
            'stats' => [
                'books' => $user->books()->count(),
                'highlights' => Highlight::query()->whereHas('book', fn ($query) => $query->where('user_id', $user->id))->count(),
                'cards' => $user->cards()->count(),
                'reviews' => Review::query()->where('user_id', $user->id)->count(),
            ],
        ]);
    }

    public function export(Request $request, UserDataExporter $exporter): StreamedResponse
    {
        $snapshot = $exporter->export($request->user());

        $filename = 'wortschatz-'.now()->format('Y-m-d').'.json';

        return response()->streamDownload(
            fn () => print json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            $filename,
            ['Content-Type' => 'application/json'],
        );
    }

    public function import(Request $request, UserDataImporter $importer): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:51200'],
        ], [], ['file' => 'arquivo']);

        $snapshot = json_decode($request->file('file')->get(), true);

        if (! is_array($snapshot)) {
            return redirect()->route('sync.index')->with('sync_error', 'O arquivo não é um JSON válido.');
        }

        try {
            $result = $importer->import($request->user(), $snapshot);
        } catch (InvalidArgumentException $e) {
            return redirect()->route('sync.index')->with('sync_error', $e->getMessage());
        }

        return redirect()->route('sync.index')->with('sync_result', $result);
    }
}
