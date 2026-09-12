<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A player appears once in a team, and the database says so.
     *
     * The pivot carried no unique index at all — not even this one — so nothing
     * but `sync()`'s good manners stood between the roster and a player listed
     * twice in the same team. The wider rule (one core per category, per season)
     * cannot live here: the season sits on `teams` and the category on `leagues`,
     * and expressing it in SQL would mean denormalising both onto the pivot and
     * resynchronising them every time a team changes division. That rule is
     * enforced on the model instead; this index covers what SQL can actually say.
     */
    public function down(): void
    {
        Schema::table('team_user', function (Blueprint $table): void {
            $table->dropUnique('team_user_team_id_user_id_unique');
        });
    }

    public function up(): void
    {
        Schema::table('team_user', function (Blueprint $table): void {
            $table->unique(['team_id', 'user_id']);
        });
    }
};
