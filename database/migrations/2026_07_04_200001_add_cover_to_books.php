<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('cover_url')->nullable()->after('author');
            // Marca que já tentamos buscar a capa (mesmo sem sucesso) para não
            // repetir a consulta ao Google Books a cada carregamento da biblioteca.
            $table->timestamp('cover_fetched_at')->nullable()->after('cover_url');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn(['cover_url', 'cover_fetched_at']);
        });
    }
};
