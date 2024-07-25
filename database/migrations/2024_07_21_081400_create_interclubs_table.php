<?php

use App\Enums\InterclubResult;
use App\Models\League;
use App\Models\Room;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('interclubs', function (Blueprint $table) {
            $table->id();
            $table->dateTime('start_date_time', 0);
            $table->unsignedTinyInteger('competition_week_number');
            $table->unsignedTinyInteger('total_players');
            $table->string('score', 5);
            $table->enum('result', array_column(InterclubResult::cases(), 'value'));
            $table->unsignedBigInteger('visited_team_id');
            $table->unsignedBigInteger('visiting_team_id');
            $table->foreignIdFor(Room::class)->constrained();
            $table->foreignIdFor(League::class)->constrained();
            $table->timestamps();

            $table->foreign('visited_team_id')->references('id')->on('users');
            $table->foreign('visiting_team_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interclubs');
    }
};
