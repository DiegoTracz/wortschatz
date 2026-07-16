<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Traduz do idioma do livro (alemão ou inglês) → português usando a API
 * gratuita do MyMemory.
 *
 * Sem chave: ~5.000 caracteres/dia. Definindo MYMEMORY_EMAIL no .env
 * o limite sobe para ~50.000 caracteres/dia.
 */
class Translator
{
    public function translate(string $text, string $source = 'de'): ?string
    {
        $query = [
            'q' => $text,
            'langpair' => "{$source}|pt-BR",
        ];

        if ($email = config('services.mymemory.email')) {
            $query['de'] = $email;
        }

        $response = Http::timeout(10)
            ->get('https://api.mymemory.translated.net/get', $query);

        if (! $response->successful()) {
            return null;
        }

        $translation = $response->json('responseData.translatedText');

        // A API devolve mensagens de erro dentro de translatedText em alguns casos.
        if (! is_string($translation) || str_contains(strtoupper($translation), 'MYMEMORY WARNING')) {
            return null;
        }

        return html_entity_decode($translation, ENT_QUOTES | ENT_HTML5);
    }
}
