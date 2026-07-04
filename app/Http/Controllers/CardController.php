<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\Highlight;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CardController extends Controller
{
    public function index(Request $request): Response
    {
        $cards = $request->user()->cards()
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request) {
                $search = '%'.$request->string('search').'%';
                $query->where(fn ($q) => $q->where('front', 'like', $search)->orWhere('back', 'like', $search));
            })
            ->orderBy('due_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Card $card) => [
                'id' => $card->id,
                'front' => $card->front,
                'back' => $card->back,
                'context' => $card->context,
                'interval_days' => $card->interval_days,
                'repetitions' => $card->repetitions,
                'due_at' => $card->due_at->toDateString(),
                'is_due' => $card->due_at->isPast(),
            ]);

        return Inertia::render('Cards/Index', [
            'cards' => $cards,
            'search' => $request->string('search')->toString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'front' => ['required', 'string', 'max:500'],
            'back' => ['required', 'string', 'max:1000'],
            'context' => ['nullable', 'string', 'max:2000'],
            'highlight_id' => ['nullable', 'integer', 'exists:highlights,id'],
        ], [], ['front' => 'frente', 'back' => 'verso', 'context' => 'contexto']);

        if ($data['highlight_id'] ?? null) {
            $highlight = Highlight::with('book')->findOrFail($data['highlight_id']);
            abort_unless($highlight->book->user_id === $request->user()->id, 403);
        }

        $request->user()->cards()->create([...$data, 'due_at' => now()]);

        return back()->with('success', 'Cartão criado!');
    }

    public function update(Request $request, Card $card): RedirectResponse
    {
        abort_unless($card->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'front' => ['required', 'string', 'max:500'],
            'back' => ['required', 'string', 'max:1000'],
            'context' => ['nullable', 'string', 'max:2000'],
        ], [], ['front' => 'frente', 'back' => 'verso', 'context' => 'contexto']);

        $card->update($data);

        return back()->with('success', 'Cartão atualizado!');
    }

    public function destroy(Request $request, Card $card): RedirectResponse
    {
        abort_unless($card->user_id === $request->user()->id, 403);

        $card->delete();

        return back();
    }
}
