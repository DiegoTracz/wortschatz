<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            // 'kindle' (destaques importados) ou 'pdf' (arquivo lido no leitor embutido).
            $table->string('source')->default('kindle')->after('author');
            $table->unsignedInteger('page_count')->nullable()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn(['source', 'page_count']);
        });
    }
};
