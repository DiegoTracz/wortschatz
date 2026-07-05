<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Chave da OpenAI salva pela interface (criptografada via cast).
            // Quando null, o app cai para OPEN_IA_TOKEN do .env.
            $table->text('openai_api_key')->nullable()->after('openai_model');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('openai_api_key');
        });
    }
};
