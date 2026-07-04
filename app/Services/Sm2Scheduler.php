<?php

namespace App\Services;

use App\Models\Card;

/**
 * Implementação do algoritmo SM-2 (SuperMemo 2), o mesmo usado pelo Anki clássico.
 *
 * Notas (qualidade da resposta):
 *   0 = errei    → volta para o começo e reaparece na mesma sessão
 *   3 = difícil  → avança, mas o fator de facilidade cai
 *   4 = bom      → avança normalmente
 *   5 = fácil    → avança e o fator de facilidade sobe
 */
class Sm2Scheduler
{
    public const AGAIN = 0;

    public const HARD = 3;

    public const GOOD = 4;

    public const EASY = 5;

    public const RATINGS = [self::AGAIN, self::HARD, self::GOOD, self::EASY];

    private const MIN_EASE = 1.3;

    /**
     * Aplica a resposta ao cartão (sem persistir) e devolve o cartão atualizado.
     */
    public function apply(Card $card, int $rating): Card
    {
        $ease = $this->nextEase($card->ease_factor, $rating);

        if ($rating < 3) {
            $card->repetitions = 0;
            $card->lapses = $card->lapses + 1;
            $card->interval_days = 0; // reaparece hoje, na mesma sessão
        } else {
            $card->repetitions = $card->repetitions + 1;
            $card->interval_days = $this->nextInterval($card->repetitions, $card->interval_days, $ease);
        }

        $card->ease_factor = $ease;
        $card->due_at = $card->interval_days === 0 ? now() : now()->addDays($card->interval_days)->startOfDay();

        return $card;
    }

    /**
     * Prevê o intervalo (em dias) de cada resposta possível, para exibir nos botões.
     *
     * @return array<int, int> rating => dias
     */
    public function previewIntervals(Card $card): array
    {
        $previews = [];

        foreach (self::RATINGS as $rating) {
            if ($rating < 3) {
                $previews[$rating] = 0;

                continue;
            }

            $ease = $this->nextEase($card->ease_factor, $rating);
            $previews[$rating] = $this->nextInterval($card->repetitions + 1, $card->interval_days, $ease);
        }

        return $previews;
    }

    private function nextEase(float $ease, int $rating): float
    {
        $ease = $ease + (0.1 - (5 - $rating) * (0.08 + (5 - $rating) * 0.02));

        return max(self::MIN_EASE, round($ease, 2));
    }

    private function nextInterval(int $repetitions, int $currentInterval, float $ease): int
    {
        return match (true) {
            $repetitions <= 1 => 1,
            $repetitions === 2 => 6,
            default => max($currentInterval + 1, (int) round($currentInterval * $ease)),
        };
    }
}
