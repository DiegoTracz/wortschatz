<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ClippingsImporter;
use App\Services\KindleClippingsParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    /**
     * Recebe o My Clippings.txt bruto (multipart) — usado pelo watcher USB.
     */
    public function storeFile(Request $request, KindleClippingsParser $parser, ClippingsImporter $importer): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240'],
        ], [], ['file' => 'arquivo']);

        $entries = $parser->parse($request->file('file')->get());

        return response()->json($importer->import($request->user(), $entries));
    }

    /**
     * Recebe entradas já estruturadas — usado pelo scraper do Amazon Notebook.
     */
    public function storeEntries(Request $request, ClippingsImporter $importer): JsonResponse
    {
        $data = $request->validate([
            'entries' => ['required', 'array', 'max:5000'],
            'entries.*.title' => ['required', 'string', 'max:500'],
            'entries.*.content' => ['required', 'string'],
            'entries.*.type' => ['nullable', 'string', 'in:highlight,note'],
            'entries.*.author' => ['nullable', 'string', 'max:500'],
            'entries.*.location' => ['nullable', 'string', 'max:50'],
            'entries.*.page' => ['nullable', 'string', 'max:50'],
            'entries.*.highlighted_at' => ['nullable', 'string', 'max:100'],
        ]);

        return response()->json($importer->import($request->user(), collect($data['entries'])));
    }
}
