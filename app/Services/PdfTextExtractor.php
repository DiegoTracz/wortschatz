<?php

namespace App\Services;

use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Extrai o texto de um PDF por página (smalot/pdfparser — PHP puro, sem binário,
 * seguro no empacotamento Electron). Alimenta a busca dentro do livro; o texto
 * autoritativo de um destaque vem sempre da seleção do usuário no client.
 */
class PdfTextExtractor
{
    public function __construct(private Parser $parser) {}

    /**
     * @return array{page_count: int, pages: array<int, string>} páginas indexadas por número (1-based)
     */
    public function extract(string $path): array
    {
        try {
            $pdf = $this->parser->parseFile($path);
            $pages = $pdf->getPages();
        } catch (Throwable) {
            // Extração é opcional (habilita busca): degrada para vazio em PDFs
            // que a lib não consegue ler, sem impedir o import/leitura.
            return ['page_count' => 0, 'pages' => []];
        }

        $texts = [];

        foreach ($pages as $index => $page) {
            try {
                $text = trim($page->getText());
            } catch (Throwable) {
                $text = '';
            }

            if ($text !== '') {
                $texts[$index + 1] = $text;
            }
        }

        return ['page_count' => count($pages), 'pages' => $texts];
    }
}
