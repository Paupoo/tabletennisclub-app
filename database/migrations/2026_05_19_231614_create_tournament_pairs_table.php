<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tournament_pairs');
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tournament_pairs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player1_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('player2_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('registered_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['tournament_id', 'player1_id']);
            $table->unique(['tournament_id', 'player2_id']);
        });
    }
};
