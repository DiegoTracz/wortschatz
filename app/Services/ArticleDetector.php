<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Detecta o artigo (der/die/das) de um substantivo alemão consultando o
 * Wiktionary alemão: o template "Deutsch Substantiv Übersicht" traz o
 * gênero gramatical em "|Genus=m|f|n".
 */
class ArticleDetector
{
    private const ARTICLES = ['m' => 'der', 'f' => 'die', 'n' => 'das'];

    public function detect(string $word): ?string
    {
        $word = trim($word);

        if ($word === '') {
            return null;
        }

        // Substantivos alemães são capitalizados; tenta como veio e capitalizado.
        foreach (array_unique([$word, mb_ucfirst(mb_strtolower($word))]) as $candidate) {
            if ($article = $this->lookup($candidate)) {
                return $article;
            }
        }

        return null;
    }

    private function lookup(string $word): ?string
    {
        $response = Http::timeout(10)->get('https://de.wiktionary.org/w/api.php', [
            'action' => 'parse',
            'page' => $word,
            'prop' => 'wikitext',
            'format' => 'json',
            'formatversion' => 2,
            'redirects' => 1,
        ]);

        if (! $response->successful()) {
            return null;
        }

        $wikitext = $response->json('parse.wikitext');

        if (! is_string($wikitext) || ! preg_match('/\|\s*Genus(?:\s*\d+)?\s*=\s*([mfn])\b/u', $wikitext, $match)) {
            return null;
        }

        return self::ARTICLES[$match[1]] ?? null;
    }
}
