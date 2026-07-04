<?php

namespace App\Http\Controllers;

use App\Services\ArticleDetector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ArticleDetectionController extends Controller
{
    public function __invoke(Request $request, ArticleDetector $detector): JsonResponse
    {
        $data = $request->validate([
            'word' => ['required', 'string', 'max:100'],
        ]);

        $word = trim($data['word']);

        // Cacheia também os "não encontrados" (como string vazia) para não
        // repetir consultas ao Wiktionary.
        $article = Cache::remember(
            'artigo:'.mb_strtolower($word),
            now()->addDays(30),
            fn () => $detector->detect($word) ?? ''
        );

        return response()->json(['article' => $article ?: null]);
    }
}
