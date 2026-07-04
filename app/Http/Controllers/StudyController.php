<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Services\Sm2Scheduler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StudyController extends Controller
{
    public function index(Request $request, Sm2Scheduler $scheduler): Response
    {
        $cards = $request->user()->cards()
            ->due()
            ->with('highlight.book:id,title')
            ->orderBy('due_at')
            ->limit(100)
            ->get()
            ->map(fn (Card $card) => $this->presentCard($card, $scheduler));

        return Inertia::render('Study', ['cards' => $cards]);
    }

    public function review(Request $request, Card $card, Sm2Scheduler $scheduler): JsonResponse
    {
        abort_unless($card->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'rating' => ['required', 'integer', Rule::in(Sm2Scheduler::RATINGS)],
        ]);

        $intervalBefore = $card->interval_days;

        $scheduler->apply($card, $data['rating']);
        $card->save();

        $card->reviews()->create([
            'user_id' => $request->user()->id,
            'rating' => $data['rating'],
            'interval_before' => $intervalBefore,
            'interval_after' => $card->interval_days,
            'ease_factor_after' => $card->ease_factor,
        ]);

        return response()->json([
            'card' => $this->presentCard($card->fresh('highlight.book'), $scheduler),
            'remaining' => $request->user()->cards()->due()->count(),
        ]);
    }

    private function presentCard(Card $card, Sm2Scheduler $scheduler): array
    {
        return [
            'id' => $card->id,
            'front' => $card->front,
            'back' => $card->back,
            'context' => $card->context,
            'book' => $card->highlight?->book?->title,
            'repetitions' => $card->repetitions,
            'interval_days' => $card->interval_days,
            'is_due' => $card->due_at->isPast(),
            'previews' => $scheduler->previewIntervals($card),
        ];
    }
}
