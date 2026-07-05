<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Enriquece um cartão de vocabulário com a OpenAI, no estilo dos cartões Anki
 * do dono do projeto: tradução com vários significados, frase(s) de exemplo em
 * alemão e uma Eselsbrücke (mnemônico) em português.
 *
 * Cada chamada registra tokens e custo estimado em `ai_usages`. Diferente do
 * Translator/ArticleDetector (que degradam para null), aqui os erros viram
 * RuntimeException com mensagem em pt-BR para o usuário saber o que corrigir.
 */
class OpenAiEnricher
{
    private const ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    /** Chave da OpenAI configurada? (chave do usuário ou fallback do .env) */
    public function configured(?User $user = null): bool
    {
        return filled($this->tokenFor($user));
    }

    /** Chave a usar: a salva pelo usuário tem precedência sobre o .env. */
    private function tokenFor(?User $user): ?string
    {
        return $user?->openai_api_key ?: config('services.openai.token');
    }

    /**
     * @return array{article:string, meanings:string, examples:array<int,string>, mnemonic:string, usage:array}
     */
    public function enrich(User $user, string $word, ?string $context = null): array
    {
        $token = $this->tokenFor($user);

        if (blank($token)) {
            throw new RuntimeException('Configure sua chave da OpenAI para usar a geração com IA.');
        }

        $model = $this->modelFor($user);

        $response = Http::withToken($token)
            ->timeout(30)
            ->acceptJson()
            ->post(self::ENDPOINT, [
                'model' => $model,
                'temperature' => 0.7,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $this->systemPrompt()],
                    ['role' => 'user', 'content' => $this->userPrompt($word, $context)],
                ],
            ]);

        if ($response->status() === 401) {
            throw new RuntimeException('Chave da OpenAI inválida. Verifique OPEN_IA_TOKEN no .env.');
        }

        if ($response->status() === 429) {
            if ($response->json('error.code') === 'insufficient_quota') {
                throw new RuntimeException('Sua conta da OpenAI está sem créditos de API. Adicione um método de pagamento e compre créditos em platform.openai.com › Billing (a API é cobrada à parte do ChatGPT Plus).');
            }

            throw new RuntimeException('Muitas requisições à OpenAI em pouco tempo. Aguarde alguns segundos e tente novamente.');
        }

        if (! $response->successful()) {
            throw new RuntimeException('A OpenAI não respondeu agora. Tente novamente em instantes.');
        }

        $content = $response->json('choices.0.message.content');
        $parsed = is_string($content) ? json_decode($content, true) : null;

        if (! is_array($parsed)) {
            throw new RuntimeException('Não foi possível interpretar a resposta da IA. Tente novamente.');
        }

        $usage = $this->recordUsage($user, $model, $response->json('usage', []));

        return [
            'article' => $this->normalizeArticle($parsed['article'] ?? ''),
            'meanings' => trim((string) ($parsed['meanings'] ?? '')),
            'examples' => $this->normalizeExamples($parsed['examples'] ?? ($parsed['example'] ?? [])),
            'mnemonic' => trim((string) ($parsed['mnemonic'] ?? '')),
            'usage' => $usage,
        ];
    }

    private function modelFor(User $user): string
    {
        $model = $user->openai_model ?: config('ai.default_model');

        // Só aceita modelos conhecidos (com preço configurado) para não gerar custo desconhecido.
        return array_key_exists($model, config('ai.models', [])) ? $model : config('ai.default_model');
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
        Você é um professor de alemão que cria flashcards para um estudante brasileiro (nível A2–B1).
        Responda SEMPRE em JSON com exatamente estas chaves:
        - "article": para substantivos, o artigo definido — "der", "die" ou "das". Para outras classes (verbos, adjetivos, expressões), use "".
        - "meanings": string em português (pt-BR) com os principais significados da palavra/expressão, separados por vírgula, do mais comum ao menos comum. Inclua a classe gramatical entre parênteses quando ajudar (ex.: "verbo separável", "reflexivo"). Seja conciso.
        - "examples": array com 1 ou 2 frases de exemplo EM ALEMÃO, naturais e curtas, usando a palavra. Marque a palavra-alvo com <b>...</b>.
        - "mnemonic": string em português com uma Eselsbrücke criativa (associação sonora ou de significado) que ajude a lembrar a palavra. Para substantivos, quando fizer sentido, inclua um elemento que ajude a fixar o gênero — no app as cores dos gêneros são der = azul, die = rosa, das = verde. Não use emoji nem o rótulo "Eselsbrücke", apenas o texto da dica.
        Não escreva nada fora do JSON.
        PROMPT;
    }

    private function userPrompt(string $word, ?string $context): string
    {
        $prompt = "Palavra ou expressão em alemão: {$word}";

        if (filled($context)) {
            $prompt .= "\nContexto em que apareceu (opcional): {$context}";
        }

        return $prompt;
    }

    private function normalizeArticle(mixed $article): string
    {
        $article = strtolower(trim((string) $article));

        return in_array($article, ['der', 'die', 'das'], true) ? $article : '';
    }

    /**
     * @param  mixed  $examples
     * @return array<int,string>
     */
    private function normalizeExamples($examples): array
    {
        $examples = is_array($examples) ? $examples : [$examples];

        return collect($examples)
            ->map(fn ($example) => trim((string) $example))
            ->filter()
            ->take(2)
            ->values()
            ->all();
    }

    /**
     * @param  array<string,mixed>  $usage
     */
    private function recordUsage(User $user, string $model, array $usage): array
    {
        $prompt = (int) ($usage['prompt_tokens'] ?? 0);
        $completion = (int) ($usage['completion_tokens'] ?? 0);
        $total = (int) ($usage['total_tokens'] ?? $prompt + $completion);
        $cost = $this->cost($model, $prompt, $completion);

        $user->aiUsages()->create([
            'model' => $model,
            'kind' => 'enrichment',
            'prompt_tokens' => $prompt,
            'completion_tokens' => $completion,
            'total_tokens' => $total,
            'cost' => $cost,
        ]);

        return [
            'model' => $model,
            'prompt_tokens' => $prompt,
            'completion_tokens' => $completion,
            'total_tokens' => $total,
            'cost' => $cost,
        ];
    }

    private function cost(string $model, int $promptTokens, int $completionTokens): float
    {
        $prices = config("ai.models.{$model}");

        if (! is_array($prices)) {
            return 0.0;
        }

        return round(
            $promptTokens / 1_000_000 * (float) $prices['input']
            + $completionTokens / 1_000_000 * (float) $prices['output'],
            6
        );
    }
}
