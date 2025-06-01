<?php

declare(strict_types=1);

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
        Schema::create('match_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_match_id')->nullable()->constrained()->onDelete('cascade');
            $table->integer('set_number');
            $table->integer('player1_score');
            $table->integer('player2_score');
            $table->foreignId('winner_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('match_sets');
    }
};
