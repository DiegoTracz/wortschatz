<?php

namespace App\Http\Controllers;

use App\Services\Translator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TranslationController extends Controller
{
    public function __invoke(Request $request, Translator $translator): JsonResponse
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:500'],
        ]);

        $translation = $translator->translate($data['text']);

        if ($translation === null) {
            return response()->json(['message' => 'Não foi possível traduzir agora. Tente novamente ou preencha manualmente.'], 502);
        }

        return response()->json(['translation' => $translation]);
    }
}
