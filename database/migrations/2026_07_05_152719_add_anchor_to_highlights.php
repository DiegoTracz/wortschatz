<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('highlights', function (Blueprint $table) {
            // Âncora do destaque de PDF: página + retângulos em coordenadas PDF
            // (independentes de zoom) + text-quote para reancoragem robusta.
            // Null para destaques do Kindle, que não têm posição no arquivo.
            $table->json('anchor')->nullable()->after('page');
        });
    }

    public function down(): void
    {
        Schema::table('highlights', function (Blueprint $table) {
            $table->dropColumn('anchor');
        });
    }
};
