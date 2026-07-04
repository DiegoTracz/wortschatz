<?php

namespace App\Http\Controllers;

use App\Services\ClippingsImporter;
use App\Services\KindleClippingsParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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
}
