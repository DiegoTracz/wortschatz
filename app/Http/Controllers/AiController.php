<?php

namespace App\Http\Controllers;

use App\Models\AiUsage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class AiController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        $models = collect(config('ai.models'))
            ->map(fn (array $data, string $value) => ['value' => $value, 'label' => $data['label']])
            ->values();

        $hasCustomKey = filled($user->openai_api_key);
        $hasEnvKey = filled(config('services.openai.token'));

        return Inertia::render('Ai', [
            'configured' => $hasCustomKey || $hasEnvKey,
            'has_custom_key' => $hasCustomKey,
            'env_key' => $hasEnvKey,
            'model' => $user->openai_model ?: config('ai.default_model'),
            'models' => $models,
            'usage' => $this->usage($request),
            'usd_brl' => $this->usdToBrl(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'model' => ['required', 'string', Rule::in(array_keys(config('ai.models')))],
            'api_key' => ['nullable', 'string', 'max:255'],
            'remove_key' => ['boolean'],
        ]);

        $user = $request->user();
        $user->openai_model = $data['model'];

        if ($data['remove_key'] ?? false) {
            $user->openai_api_key = null;
        } elseif (filled($data['api_key'] ?? null)) {
            $user->openai_api_key = trim($data['api_key']);
        }

        $user->save();

        return to_route('ai.edit');
    }

    /**
     * Cotação USD→BRL ao vivo (AwesomeAPI), cacheada por 12h. Em falha de rede,
     * devolve o câmbio de reserva do config sem cachear (tenta de novo depois).
     */
    private function usdToBrl(): float
    {
        if (($cached = Cache::get('ai.usd_brl')) !== null) {
            return (float) $cached;
        }

        try {
            $bid = Http::timeout(5)
                ->get('https://economia.awesomeapi.com.br/last/USD-BRL')
                ->json('USDBRL.bid');

            if (is_numeric($bid)) {
                $rate = round((float) $bid, 4);
                Cache::put('ai.usd_brl', $rate, now()->addHours(12));

                return $rate;
            }
        } catch (Throwable) {
            // Rede indisponível: cai para o câmbio de reserva.
        }

        return (float) config('ai.usd_brl_fallback');
    }

    private function usage(Request $request): array
    {
        $base = $request->user()->aiUsages();

        $summarize = fn ($query) => [
            'requests' => (clone $query)->count(),
            'tokens' => (int) (clone $query)->sum('total_tokens'),
            'cost' => (float) (clone $query)->sum('cost'),
        ];

        return [
            'total' => $summarize($base->clone()),
            'month' => $summarize($base->clone()->where('created_at', '>=', now()->startOfMonth())),
            'recent' => $base->clone()
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn (AiUsage $usage) => [
                    'id' => $usage->id,
                    'model' => $usage->model,
                    'total_tokens' => $usage->total_tokens,
                    'cost' => $usage->cost,
                    'created_at' => $usage->created_at->toDateTimeString(),
                ]),
        ];
    }
}
