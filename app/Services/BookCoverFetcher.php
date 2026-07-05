<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Busca a capa de um livro na API pública de volumes do Google Books
 * (sem chave). Pesquisa por título + autor e devolve a URL da miniatura,
 * degradando para null em qualquer falha — o front trata a ausência
 * gerando uma capa de fallback com o título.
 */
class BookCoverFetcher
{
    public function fetch(string $title, ?string $author = null): ?string
    {
        $terms = 'intitle:'.$title;

        if ($author) {
            $terms .= ' inauthor:'.$author;
        }

        $response = Http::timeout(10)->get('https://www.googleapis.com/books/v1/volumes', [
            'q' => $terms,
            'maxResults' => 1,
            'printType' => 'books',
            'country' => 'US',
        ]);

        if (! $response->successful()) {
            return null;
        }

        $thumbnail = $response->json('items.0.volumeInfo.imageLinks.thumbnail')
            ?? $response->json('items.0.volumeInfo.imageLinks.smallThumbnail');

        if (! is_string($thumbnail) || $thumbnail === '') {
            return null;
        }

        // Serve por https e remove o efeito de "página dobrada" para uma capa limpa.
        return str_replace(['http://', '&edge=curl'], ['https://', ''], $thumbnail);
    }
}
