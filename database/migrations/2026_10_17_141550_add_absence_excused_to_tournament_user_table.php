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
        Schema::table('tournament_user', function (Blueprint $table) {
            $table->dropColumn('absence_excused');
        });
    }

    /**
     * Run the migrations.
     *
     * Set with a `no_show`: whether the player had warned. An excused absence
     * drops the entry fee, an unexcused one keeps it due.
     */
    public function up(): void
    {
        Schema::table('tournament_user', function (Blueprint $table) {
            $table->boolean('absence_excused')->nullable()->after('qr_confirmed');
        });
    }
};
