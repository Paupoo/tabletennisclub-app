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
        Schema::create('tournament_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pool_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('tournament_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('player1_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('player2_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('winner_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('round')->nullable(); // 'round_16', 'quarterfinal', 'semifinal', 'final', 'bronze'
            $table->string('status')->default('scheduled'); // scheduled, in_progress, completed
            $table->integer('match_order');
            $table->timestamp('scheduled_time')->nullable();
            $table->integer('table_number')->nullable();
            $table->foreignId('next_match_id')->nullable()->references('id')->on('tournament_matches')->nullOnDelete();
            $table->foreignId('bronze_match_id')->nullable()->references('id')->on('tournament_matches')->nullOnDelete();
            $table->boolean('is_bronze_match')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tournament_matches');
    }
};
