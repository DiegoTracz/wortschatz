<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            // Eselsbrücke (mnemônico) gerada pela IA — campo próprio e opcional.
            $table->text('mnemonic')->nullable()->after('context');
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn('mnemonic');
        });
    }
};
