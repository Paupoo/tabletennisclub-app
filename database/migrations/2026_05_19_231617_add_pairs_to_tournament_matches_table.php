<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('tournament_matches', function (Blueprint $table) {
            $table->dropForeign(['pair1_id']);
            $table->dropForeign(['pair2_id']);
            $table->dropColumn(['pair1_id', 'pair2_id']);
        });
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tournament_matches', function (Blueprint $table) {
            $table->foreignId('pair1_id')->nullable()->after('player2_id')->constrained('tournament_pairs')->nullOnDelete();
            $table->foreignId('pair2_id')->nullable()->after('pair1_id')->constrained('tournament_pairs')->nullOnDelete();
        });
    }
};
