<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Texto extraído por página no upload (smalot/pdfparser). Alimenta a
        // busca dentro do livro; o texto autoritativo do destaque vem do client.
        Schema::create('pdf_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('page');
            $table->text('text');
            $table->timestamps();

            $table->unique(['book_id', 'page']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdf_pages');
    }
};
