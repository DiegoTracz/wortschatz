<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Identidade estável entre máquinas para a sincronização por export/import:
     * cartões e revisões precisam casar entre bancos diferentes, onde os ids
     * auto-incrementais divergem. Destaques já têm o hash e livros casam por
     * título, então só estas duas tabelas ganham uuid.
     */
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
        });

        foreach (['cards', 'reviews'] as $tableName) {
            DB::table($tableName)->whereNull('uuid')->pluck('id')->each(
                fn (int $id) => DB::table($tableName)->where('id', $id)->update(['uuid' => (string) Str::uuid()])
            );
        }

        // Único por usuário (não global): importar o snapshot de outra conta no
        // mesmo banco reusa os uuids do snapshot.
        Schema::table('cards', function (Blueprint $table) {
            $table->unique(['user_id', 'uuid']);
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->unique(['user_id', 'uuid']);
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'uuid']);
            $table->dropColumn('uuid');
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'uuid']);
            $table->dropColumn('uuid');
        });
    }
};
