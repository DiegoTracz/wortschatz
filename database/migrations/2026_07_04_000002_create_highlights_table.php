<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('highlights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('highlight'); // highlight | note
            $table->text('content');
            $table->string('location')->nullable();
            $table->string('page')->nullable();
            $table->timestamp('highlighted_at')->nullable();
            $table->string('hash', 40);
            $table->timestamps();

            $table->unique(['book_id', 'hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('highlights');
    }
};
