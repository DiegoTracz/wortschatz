<?php

use App\Models\Highlight;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Recalcula os hashes com a fórmula normalizada de Highlight::computeHash
        // (whitespace colapsado, localização reduzida ao número inicial). Destaques
        // que a normalização revelar como duplicados têm seus cards reapontados
        // para o sobrevivente e são removidos.
        $highlights = DB::table('highlights')
            ->join('books', 'books.id', '=', 'highlights.book_id')
            ->select('highlights.*', 'books.title as book_title')
            ->orderBy('highlights.id')
            ->get();

        foreach ($highlights as $highlight) {
            $hash = Highlight::computeHash(
                $highlight->book_title,
                $highlight->type,
                $highlight->location,
                $highlight->page,
                $highlight->content,
            );

            if ($hash === $highlight->hash) {
                continue;
            }

            $survivor = DB::table('highlights')
                ->where('book_id', $highlight->book_id)
                ->where('hash', $hash)
                ->where('id', '!=', $highlight->id)
                ->first();

            if ($survivor !== null) {
                DB::table('cards')->where('highlight_id', $highlight->id)->update(['highlight_id' => $survivor->id]);
                DB::table('highlights')->where('id', $highlight->id)->delete();
            } else {
                DB::table('highlights')->where('id', $highlight->id)->update(['hash' => $hash]);
            }
        }
    }

    public function down(): void
    {
        // Sem reversão: a fórmula antiga é reproduzível, mas voltar a ela não tem utilidade.
    }
};
