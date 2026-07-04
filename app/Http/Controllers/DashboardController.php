<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $reviewDates = Review::where('user_id', $user->id)
            ->selectRaw('date(created_at) as day, count(*) as total')
            ->groupBy('day')
            ->orderByDesc('day')
            ->limit(365)
            ->pluck('total', 'day');

        return Inertia::render('Dashboard', [
            'stats' => [
                'due' => $user->cards()->due()->count(),
                'new' => $user->cards()->where('repetitions', 0)->where('lapses', 0)->count(),
                'cards' => $user->cards()->count(),
                'books' => $user->books()->count(),
                'highlights' => $user->books()->withCount('highlights')->get()->sum('highlights_count'),
                'reviewsToday' => $reviewDates[today()->toDateString()] ?? 0,
                'streak' => $this->streak($reviewDates->keys()->all()),
                'lastWeek' => $this->lastWeek($reviewDates),
            ],
        ]);
    }

    /**
     * Dias consecutivos (terminando hoje ou ontem) com pelo menos uma revisão.
     */
    private function streak(array $days): int
    {
        $streak = 0;
        $cursor = today();

        if (! in_array($cursor->toDateString(), $days, true)) {
            $cursor = $cursor->subDay();
        }

        while (in_array($cursor->toDateString(), $days, true)) {
            $streak++;
            $cursor = $cursor->subDay();
        }

        return $streak;
    }

    private function lastWeek($reviewDates): array
    {
        return collect(range(6, 0))
            ->map(function (int $daysAgo) use ($reviewDates) {
                $date = today()->subDays($daysAgo);

                return [
                    'label' => mb_substr($date->locale('pt_BR')->minDayName, 0, 3),
                    'total' => $reviewDates[$date->toDateString()] ?? 0,
                ];
            })
            ->all();
    }
}
