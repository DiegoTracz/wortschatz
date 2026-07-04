<?php

namespace App\Services;

use App\Models\Highlight;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Faz o parse do arquivo "My Clippings.txt" gerado pelo Kindle.
 *
 * Cada entrada tem o formato:
 *
 *   Título do Livro (Autor)
 *   - Seu destaque ou posição 1234-1236 | Adicionado: sexta-feira, 12 de abril de 2024 11:22:33
 *   (linha em branco)
 *   Texto destacado
 *   ==========
 *
 * Suporta Kindle configurado em português, inglês e alemão.
 */
class KindleClippingsParser
{
    private const SEPARATOR = '==========';

    private const TYPE_PATTERNS = [
        'bookmark' => '/lesezeichen|bookmark|marcador/iu',
        'note' => '/\bnotiz\b|\bnote\b|\bnota\b/iu',
        'highlight' => '/markierung|highlight|destaque|subrayado/iu',
    ];

    private const MONTHS = [
        // Português
        'janeiro' => 'January', 'fevereiro' => 'February', 'março' => 'March', 'abril' => 'April',
        'maio' => 'May', 'junho' => 'June', 'julho' => 'July', 'agosto' => 'August',
        'setembro' => 'September', 'outubro' => 'October', 'novembro' => 'November', 'dezembro' => 'December',
        // Alemão
        'januar' => 'January', 'februar' => 'February', 'märz' => 'March', 'mai' => 'May',
        'juni' => 'June', 'juli' => 'July', 'oktober' => 'October', 'dezember' => 'December',
    ];

    /**
     * @return Collection<int, array{title: string, author: ?string, type: string, content: string, location: ?string, page: ?string, highlighted_at: ?Carbon, hash: string}>
     */
    public function parse(string $contents): Collection
    {
        $contents = str_replace(["\xEF\xBB\xBF", "\r\n"], ['', "\n"], $contents);

        return collect(explode(self::SEPARATOR, $contents))
            ->map(fn (string $block) => $this->parseBlock($block))
            ->filter()
            ->values();
    }

    private function parseBlock(string $block): ?array
    {
        $lines = array_values(array_filter(
            explode("\n", trim($block)),
            fn (string $line) => trim($line) !== ''
        ));

        if (count($lines) < 2 || ! str_starts_with(trim($lines[1]), '- ')) {
            return null;
        }

        $meta = trim($lines[1]);
        $type = $this->detectType($meta);

        // Marcadores de página não têm conteúdo — ignora.
        $content = trim(implode("\n", array_slice($lines, 2)));
        if ($type === 'bookmark' || $content === '') {
            return null;
        }

        [$title, $author] = $this->parseTitleLine(trim($lines[0]));
        $location = $this->match('/(?:position|location|posi[cç][aã]o)\s+(\d+(?:-\d+)?)/iu', $meta);
        $page = $this->match('/(?:page|p[aá]gina|seite)\s+([0-9ivxlc]+)/iu', $meta);

        return [
            'title' => $title,
            'author' => $author,
            'type' => $type,
            'content' => $content,
            'location' => $location,
            'page' => $page,
            'highlighted_at' => $this->parseDate($meta),
            'hash' => Highlight::computeHash($title, $type, $location, $page, $content),
        ];
    }

    private function detectType(string $meta): string
    {
        foreach (self::TYPE_PATTERNS as $type => $pattern) {
            if (preg_match($pattern, $meta)) {
                return $type;
            }
        }

        return 'highlight';
    }

    /**
     * "Der Prozess (Kafka, Franz)" → ["Der Prozess", "Kafka, Franz"]
     */
    private function parseTitleLine(string $line): array
    {
        if (preg_match('/^(?<title>.*\S)\s*\((?<author>[^(]*)\)$/u', $line, $m)) {
            return [trim($m['title']), trim($m['author']) ?: null];
        }

        return [$line, null];
    }

    private function match(string $pattern, string $subject): ?string
    {
        return preg_match($pattern, $subject, $m) ? $m[1] : null;
    }

    private function parseDate(string $meta): ?Carbon
    {
        $segments = explode('|', $meta);
        $raw = trim(end($segments));

        // Remove o prefixo ("Added on", "Adicionado:", "Hinzugefügt am") e o dia da semana.
        $raw = preg_replace('/^\s*-?\s*(added on|adicionado:?|hinzugefügt am)\s*/iu', '', $raw);
        $raw = preg_replace('/^[[:alpha:]çáéà-]+(-feira)?,?\s*/iu', '', $raw);
        $raw = str_ireplace(['de ', '. '], ['', ' '], $raw);
        $raw = str_ireplace(array_keys(self::MONTHS), array_values(self::MONTHS), $raw);

        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }
}
