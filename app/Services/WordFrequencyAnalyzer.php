<?php

namespace App\Services;

/**
 * Conta as palavras mais frequentes de um conjunto de textos em alemão para
 * montar o "mapa de vocabulário" de um livro. Normaliza para minúsculas (une
 * "Zeit"/"zeit"), descarta pontuação, números, tokens curtos e uma lista de
 * palavras funcionais (Stoppwörter) que não agregam ao estudo.
 */
class WordFrequencyAnalyzer
{
    /** Palavras funcionais alemãs ignoradas na contagem. */
    private const STOPWORDS = [
        'der', 'die', 'das', 'den', 'dem', 'des', 'ein', 'eine', 'einen', 'einem', 'einer', 'eines',
        'und', 'oder', 'aber', 'sondern', 'denn', 'doch', 'sowie', 'als', 'wie', 'wenn', 'weil', 'dass',
        'ich', 'du', 'er', 'sie', 'es', 'wir', 'ihr', 'mir', 'mich', 'dir', 'dich', 'ihm', 'ihn', 'ihnen',
        'uns', 'euch', 'man', 'sich', 'mein', 'dein', 'sein', 'ihre', 'ihrer', 'ihren', 'unser',
        'ist', 'sind', 'war', 'waren', 'bin', 'bist', 'seid', 'sein', 'gewesen', 'werden', 'wird', 'wurde', 'worden',
        'hat', 'habe', 'hast', 'haben', 'hatte', 'hatten', 'wird', 'kann', 'konnte', 'muss', 'musste',
        'nicht', 'kein', 'keine', 'keinen', 'nur', 'auch', 'schon', 'noch', 'sehr', 'mehr', 'wieder', 'immer',
        'in', 'im', 'ins', 'an', 'am', 'auf', 'aus', 'bei', 'mit', 'nach', 'von', 'vom', 'vor', 'zu', 'zum', 'zur',
        'um', 'über', 'unter', 'durch', 'gegen', 'ohne', 'für', 'bis', 'ab', 'seit', 'wegen', 'zwischen',
        'hier', 'dort', 'da', 'dann', 'so', 'nun', 'jetzt', 'heute', 'schon', 'ganz', 'etwas', 'alle', 'alles',
        'was', 'wer', 'wo', 'wann', 'warum', 'welche', 'welcher', 'welches', 'dies', 'diese', 'dieser', 'dieses',
        'sein', 'ihre', 'diesem', 'jede', 'jeder', 'jedes', 'zwar', 'also', 'mal', 'ja', 'nein',
    ];

    /**
     * @param  iterable<string>  $texts
     * @return list<array{word: string, count: int}>
     */
    public function analyze(iterable $texts, int $limit = 40): array
    {
        $stopwords = array_flip(self::STOPWORDS);
        $counts = [];

        foreach ($texts as $text) {
            $tokens = preg_split('/[^\p{L}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY);

            foreach ($tokens as $token) {
                if (mb_strlen($token) < 3 || isset($stopwords[$token])) {
                    continue;
                }

                $counts[$token] = ($counts[$token] ?? 0) + 1;
            }
        }

        // Ordena por frequência (desc) e, em empate, alfabeticamente.
        uksort($counts, fn ($a, $b) => $counts[$b] <=> $counts[$a] ?: strcmp($a, $b));

        return array_map(
            fn ($word, $count) => ['word' => $word, 'count' => $count],
            array_keys($slice = array_slice($counts, 0, $limit, true)),
            array_values($slice),
        );
    }
}
