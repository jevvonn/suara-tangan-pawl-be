<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('favorite_sentences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('sentence');
            $table->timestamps();

            // One copy per user; the _ci collation makes "halo" = "HALO".
            $table->unique(['user_id', 'sentence'], 'favorite_sentences_user_sentence_unique');
            $table->index(['user_id', 'created_at'], 'favorite_sentences_user_created_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('favorite_sentences');
    }
};
