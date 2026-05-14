<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('final_position');
        });
    }

    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('final_position', 30)->nullable()->after('season_id');
        });
    }
};
