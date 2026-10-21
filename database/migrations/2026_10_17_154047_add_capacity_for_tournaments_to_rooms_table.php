<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->integer('total_playable_tables')->default(0);
        });

        DB::table('rooms')->update([
            'total_playable_tables' => DB::raw("(select count(*) from tables where tables.room_id = rooms.id and tables.state <> 'out_of_service')"),
        ]);

        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn('capacity_for_tournaments');
        });
    }

    /**
     * Run the migrations.
     *
     * The room a tournament takes, typed in like the two others and started
     * from the training one. The stored count of playable tables goes: nothing
     * kept it up to date, and the tables are counted where they are now.
     */
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->unsignedTinyInteger('capacity_for_tournaments')->default(0)->after('capacity_for_interclubs');
        });

        DB::table('rooms')->update(['capacity_for_tournaments' => DB::raw('capacity_for_trainings')]);

        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn('total_playable_tables');
        });
    }
};
