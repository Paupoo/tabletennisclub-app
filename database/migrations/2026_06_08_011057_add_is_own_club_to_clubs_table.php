<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->dropIndex('clubs_is_own_club_unique');
            $table->dropColumn('is_own_club');
        });
    }

    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->boolean('is_own_club')->default(false)->after('is_active');
        });

        // Partial unique index: only one row can have is_own_club = true
        DB::statement('CREATE UNIQUE INDEX clubs_is_own_club_unique ON clubs (is_own_club) WHERE is_own_club = 1');
    }
};
