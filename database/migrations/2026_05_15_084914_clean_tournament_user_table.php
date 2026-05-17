<?php

declare(strict_types=1);

use App\Models\ClubEvents\Tournament\Pool;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        // Drop user_id FK first — it's backed by the unique(user_id, tournament_id) index
        Schema::table('tournament_user', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropUnique(['user_id', 'tournament_id']);
        });

        Schema::table('tournament_user', function (Blueprint $table) {
            $table->foreignIdFor(Pool::class)->nullable()->constrained()->nullOnDelete();
            $table->integer('matches_won')->default(0);
            $table->integer('sets_won')->default(0);
            $table->integer('points_won')->default(0);
            $table->unique(['user_id', 'tournament_id', 'pool_id']);
        });

        Schema::table('tournament_user', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function up(): void
    {
        // MySQL reuses the unique(user_id, tournament_id, pool_id) index as backing
        // for the user_id FK. Both FKs must be dropped before the unique index can be removed.
        Schema::table('tournament_user', function (Blueprint $table) {
            $table->dropForeign(['pool_id']);
            $table->dropForeign(['user_id']);
        });

        Schema::table('tournament_user', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'tournament_id', 'pool_id']);
            $table->dropColumn(['pool_id', 'matches_won', 'sets_won', 'points_won']);
            $table->unique(['user_id', 'tournament_id']);
        });

        Schema::table('tournament_user', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }
};
