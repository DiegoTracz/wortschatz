<?php

namespace App\Services;

use App\Models\Card;
use Illuminate\Support\Carbon;

/**
 * Implementação do FSRS-4.5 (Free Spaced Repetition Scheduler), o algoritmo
 * do Anki moderno, com os parâmetros padrão publicados pelo projeto
 * open-spaced-repetition.
 *
 * Cada cartão carrega um estado (stability, difficulty):
 *   - stability: dias para a probabilidade de lembrança cair até 90%
 *   - difficulty: quão difícil é o cartão, na escala 1-10
 *
 * O intervalo é derivado da estabilidade e da retenção alvo (config srs.retention).
 *
 * Notas (qualidade da resposta):
 *   1 = errei    → reaparece na mesma sessão
 *   2 = difícil
 *   3 = bom
 *   4 = fácil
 */
class FsrsScheduler
{
    public const AGAIN = 1;

    public const HARD = 2;

    public const GOOD = 3;

    public const EASY = 4;

    public const RATINGS = [self::AGAIN, self::HARD, self::GOOD, self::EASY];

    private const DECAY = -0.5;

    private const FACTOR = 19 / 81; // garante R(t = stability) = 0.9

    private const MIN_DIFFICULTY = 1.0;

    private const MAX_DIFFICULTY = 10.0;

    private const MIN_STABILITY = 0.01;

    /** Parâmetros padrão do FSRS-4.5. */
    private const W = [
        0.4872, 1.4003, 3.7145, 13.8206, 5.1618, 1.2298, 0.8975, 0.031, 1.6474,
        0.1367, 1.0461, 2.1072, 0.0793, 0.3246, 1.587, 0.2272, 2.8755,
    ];

    /**
     * Aplica a resposta ao cartão (sem persistir) e devolve o cartão atualizado.
     */
    public function apply(Card $card, int $rating, ?Carbon $reviewedAt = null): Card
    {
        $reviewedAt ??= now();

        $elapsed = $card->last_reviewed_at
            ? max(0.0, $card->last_reviewed_at->diffInSeconds($reviewedAt) / 86400)
            : 0.0;

        [$stability, $difficulty] = $this->nextState($card->stability, $card->difficulty, $elapsed, $rating);

        $card->stability = $stability;
        $card->difficulty = $difficulty;
        $card->last_reviewed_at = $reviewedAt;

        if ($rating === self::AGAIN) {
            $card->lapses = $card->lapses + 1;
            $card->interval_days = 0; // reaparece hoje, na mesma sessão
        } else {
            $card->repetitions = $card->repetitions + 1;
            $card->interval_days = $this->intervalFor($stability);
        }

        $card->due_at = $card->interval_days === 0
            ? $reviewedAt
            : $reviewedAt->copy()->addDays($card->interval_days)->startOfDay();

        return $card;
    }

    /**
     * Prevê o intervalo (em dias) de cada resposta possível, para exibir nos botões.
     *
     * @return array<int, int> rating => dias
     */
    public function previewIntervals(Card $card): array
    {
        $elapsed = $card->last_reviewed_at
            ? max(0.0, $card->last_reviewed_at->diffInSeconds(now()) / 86400)
            : 0.0;

        $previews = [];

        foreach (self::RATINGS as $rating) {
            if ($rating === self::AGAIN) {
                $previews[$rating] = 0;

                continue;
            }

            [$stability] = $this->nextState($card->stability, $card->difficulty, $elapsed, $rating);
            $previews[$rating] = $this->intervalFor($stability);
        }

        return $previews;
    }

    /**
     * Reprocessa um histórico de revisões e devolve o estado final do cartão.
     * Usado na conversão de dados SM-2 → FSRS.
     *
     * @param  iterable<array{0: int, 1: Carbon}>  $history  pares [nota (1-4), revisado em]
     * @return array{stability: ?float, difficulty: ?float, interval_days: int, repetitions: int, lapses: int, last_reviewed_at: ?Carbon, due_at: ?Carbon}
     */
    public function replay(iterable $history): array
    {
        $stability = $difficulty = $last = null;
        $repetitions = $lapses = $interval = 0;

        foreach ($history as [$rating, $reviewedAt]) {
            $elapsed = $last ? max(0.0, $last->diffInSeconds($reviewedAt) / 86400) : 0.0;

            [$stability, $difficulty] = $this->nextState($stability, $difficulty, $elapsed, $rating);

            $rating === self::AGAIN ? $lapses++ : $repetitions++;
            $interval = $rating === self::AGAIN ? 0 : $this->intervalFor($stability);
            $last = $reviewedAt;
        }

        return [
            'stability' => $stability,
            'difficulty' => $difficulty,
            'interval_days' => $interval,
            'repetitions' => $repetitions,
            'lapses' => $lapses,
            'last_reviewed_at' => $last,
            'due_at' => $last ? ($interval === 0 ? $last : $last->copy()->addDays($interval)->startOfDay()) : null,
        ];
    }

    /**
     * Transição de estado (stability, difficulty) para uma resposta.
     *
     * @return array{0: float, 1: float}
     */
    private function nextState(?float $stability, ?float $difficulty, float $elapsedDays, int $rating): array
    {
        if ($stability === null || $difficulty === null) {
            return [$this->initialStability($rating), $this->initialDifficulty($rating)];
        }

        $retrievability = $this->retrievability($elapsedDays, $stability);

        $newDifficulty = $this->nextDifficulty($difficulty, $rating);
        $newStability = $rating === self::AGAIN
            ? $this->stabilityAfterLapse($difficulty, $stability, $retrievability)
            : $this->stabilityAfterSuccess($difficulty, $stability, $retrievability, $rating);

        return [$newStability, $newDifficulty];
    }

    /** Probabilidade de lembrar após $elapsedDays com a estabilidade dada. */
    private function retrievability(float $elapsedDays, float $stability): float
    {
        return (1 + self::FACTOR * $elapsedDays / $stability) ** self::DECAY;
    }

    /** Intervalo (dias) para atingir a retenção alvo com a estabilidade dada. */
    private function intervalFor(float $stability): int
    {
        $retention = config('srs.retention');
        $interval = (int) round($stability / self::FACTOR * ($retention ** (1 / self::DECAY) - 1));

        return max(1, min($interval, config('srs.max_interval')));
    }

    private function initialStability(int $rating): float
    {
        return max(self::MIN_STABILITY, self::W[$rating - 1]);
    }

    private function initialDifficulty(int $rating): float
    {
        return $this->clampDifficulty(self::W[4] - ($rating - 3) * self::W[5]);
    }

    private function nextDifficulty(float $difficulty, int $rating): float
    {
        $updated = $difficulty - self::W[6] * ($rating - 3);

        // Reversão à média: evita que a dificuldade fique presa nos extremos.
        return $this->clampDifficulty(self::W[7] * self::W[4] + (1 - self::W[7]) * $updated);
    }

    private function stabilityAfterSuccess(float $difficulty, float $stability, float $retrievability, int $rating): float
    {
        $hardPenalty = $rating === self::HARD ? self::W[15] : 1.0;
        $easyBonus = $rating === self::EASY ? self::W[16] : 1.0;

        $growth = exp(self::W[8])
            * (11 - $difficulty)
            * $stability ** -self::W[9]
            * (exp(self::W[10] * (1 - $retrievability)) - 1)
            * $hardPenalty
            * $easyBonus;

        return max(self::MIN_STABILITY, $stability * (1 + $growth));
    }

    private function stabilityAfterLapse(float $difficulty, float $stability, float $retrievability): float
    {
        $newStability = self::W[11]
            * $difficulty ** -self::W[12]
            * (($stability + 1) ** self::W[13] - 1)
            * exp(self::W[14] * (1 - $retrievability));

        // Esquecer não pode aumentar a estabilidade.
        return max(self::MIN_STABILITY, min($newStability, $stability));
    }

    private function clampDifficulty(float $difficulty): float
    {
        return max(self::MIN_DIFFICULTY, min(self::MAX_DIFFICULTY, $difficulty));
    }
}
