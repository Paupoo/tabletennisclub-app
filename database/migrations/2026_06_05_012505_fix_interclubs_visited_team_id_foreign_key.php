<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('interclubs', function (Blueprint $table) {
            $table->dropForeign(['visited_team_id']);
            $table->dropForeign(['visiting_team_id']);

            $table->foreign('visited_team_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('visiting_team_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function up(): void
    {
        Schema::table('interclubs', function (Blueprint $table) {
            $table->dropForeign(['visited_team_id']);
            $table->dropForeign(['visiting_team_id']);

            $table->foreign('visited_team_id')->references('id')->on('teams')->nullOnDelete();
            $table->foreign('visiting_team_id')->references('id')->on('teams')->nullOnDelete();
        });
    }
};
