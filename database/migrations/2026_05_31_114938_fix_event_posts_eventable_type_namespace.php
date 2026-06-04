<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('event_posts')
            ->where('eventable_type', 'App\Domains\Competitions\Tournament\Models\Tournament')
            ->update(['eventable_type' => 'App\Models\ClubEvents\Tournament\Tournament']);

        DB::table('event_posts')
            ->where('eventable_type', 'App\Domains\Trainings\Models\TrainingPack')
            ->update(['eventable_type' => 'App\Models\ClubEvents\Training\TrainingPack']);
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('event_posts')
            ->where('eventable_type', 'App\Models\ClubEvents\Tournament\Tournament')
            ->update(['eventable_type' => 'App\Domains\Competitions\Tournament\Models\Tournament']);

        DB::table('event_posts')
            ->where('eventable_type', 'App\Models\ClubEvents\Training\TrainingPack')
            ->update(['eventable_type' => 'App\Domains\Trainings\Models\TrainingPack']);
    }
};
