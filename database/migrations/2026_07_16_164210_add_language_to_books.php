<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Idioma do conteúdo do livro ('de' ou 'en'): direciona o OCR do modo
     * recorte, o par da tradução e a detecção de artigo (só alemão).
     */
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('language', 5)->default('de')->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn('language');
        });
    }
};
