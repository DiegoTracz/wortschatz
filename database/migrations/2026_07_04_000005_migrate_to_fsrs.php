<?php

use App\Services\FsrsScheduler;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->float('stability')->nullable()->after('context');
            $table->float('difficulty')->nullable()->after('stability');
            $table->timestamp('last_reviewed_at')->nullable()->after('lapses');
        });

        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn('ease_factor');
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->float('stability_after')->nullable()->after('interval_after');
            $table->float('difficulty_after')->nullable()->after('stability_after');
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn('ease_factor_after');
        });

        // Converte as notas da escala SM-2 (0/3/4/5) para a do FSRS (1/2/3/4).
        // A ordem evita colisão entre valores antigos e novos.
        foreach ([0 => 1, 3 => 2, 4 => 3, 5 => 4] as $old => $new) {
            DB::table('reviews')->where('rating', $old)->update(['rating' => $new]);
        }

        // Reprocessa o histórico de cada cartão pelo FSRS para derivar
        // stability/difficulty e reagendar a próxima revisão.
        $scheduler = new FsrsScheduler;

        DB::table('cards')->orderBy('id')->chunkById(100, function ($cards) use ($scheduler) {
            foreach ($cards as $card) {
                $history = DB::table('reviews')
                    ->where('card_id', $card->id)
                    ->orderBy('created_at')
                    ->get()
                    ->map(fn ($review) => [(int) $review->rating, Carbon::parse($review->created_at)]);

                if ($history->isEmpty()) {
                    continue;
                }

                $state = $scheduler->replay($history);

                DB::table('cards')->where('id', $card->id)->update([
                    'stability' => $state['stability'],
                    'difficulty' => $state['difficulty'],
                    'interval_days' => $state['interval_days'],
                    'repetitions' => $state['repetitions'],
                    'lapses' => $state['lapses'],
                    'last_reviewed_at' => $state['last_reviewed_at'],
                    'due_at' => $state['due_at'],
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->float('ease_factor')->default(2.5);
        });

        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn(['stability', 'difficulty', 'last_reviewed_at']);
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->float('ease_factor_after')->nullable();
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn(['stability_after', 'difficulty_after']);
        });

        foreach ([4 => 5, 3 => 4, 2 => 3, 1 => 0] as $old => $new) {
            DB::table('reviews')->where('rating', $old)->update(['rating' => $new]);
        }
    }
};
