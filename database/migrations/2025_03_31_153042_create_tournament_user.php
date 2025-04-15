<?php

use App\Models\Pool;
use App\Models\Tournament;
use App\Models\User;
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
        Schema::create('tournament_user', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(Tournament::class)->nullable()->constrained()->nullOnDelete();
            $table->boolean('has_paid')->default(false);
            $table->integer('matches_won')->default(0);
            $table->integer('sets_won')->default(0);
            $table->integer('points_won')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'tournament_id', 'pool_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tournament_user');
    }
};
