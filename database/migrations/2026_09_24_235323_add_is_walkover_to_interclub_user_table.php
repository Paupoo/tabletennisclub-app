<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The player written on the match sheet who will not play.
 *
 * Playing one short (C.25.6, C.25.7) still means naming a fourth player: they
 * are aligned — so C.20.1 bars them from any other team of the category that
 * week — but lose their matches by walkover. Rule C.22.1.3 counts only players
 * who played a point, so the threshold of the teams below must ignore them.
 */
return new class extends Migration
{
    public function down(): void
    {
        Schema::table('interclub_user', function (Blueprint $table): void {
            $table->dropColumn('is_walkover');
        });
    }

    public function up(): void
    {
        Schema::table('interclub_user', function (Blueprint $table): void {
            $table->boolean('is_walkover')->default(false)->after('is_selected');
        });
    }
};
