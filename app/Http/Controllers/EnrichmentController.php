<?php

namespace App\Http\Controllers;

use App\Services\OpenAiEnricher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class EnrichmentController extends Controller
{
    public function __invoke(Request $request, OpenAiEnricher $enricher): JsonResponse
    {
        $data = $request->validate([
            'word' => ['required', 'string', 'max:200'],
            'context' => ['nullable', 'string', 'max:2000'],
        ], [], ['word' => 'palavra']);

        if (! $enricher->configured($request->user())) {
            return response()->json([
                'message' => 'IA não configurada. Adicione sua chave da OpenAI em Configurações › IA.',
            ], 422);
        }

        try {
            $result = $enricher->enrich($request->user(), $data['word'], $data['context'] ?? null);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        return response()->json($result);
    }
}
