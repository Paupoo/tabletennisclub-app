<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\Shared\Enums\InterclubResult;
use App\Models\ClubEvents\Interclub\League;
use App\Models\ClubEvents\Interclub\Season;
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
        Schema::dropIfExists('interclubs');
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('interclubs', function (Blueprint $table) {
            $table->id();
            $table->string('address', 150);
            $table->dateTime('start_date_time', 0);
            $table->unsignedTinyInteger('week_number')->nullable();
            $table->unsignedTinyInteger('total_players');
            $table->string('score', 5)->nullable();
            $table->enum('result', array_column(InterclubResult::cases(), 'value'))->nullable();
            $table->unsignedBigInteger('visited_team_id')->nullable();
            $table->unsignedBigInteger('visiting_team_id')->nullable();
            $table->foreignIdFor(Room::class)->nullable();
            $table->foreignIdFor(League::class)->nullable();
            $table->foreignIdFor(Season::class)->nullable();
            $table->timestamps();

            $table->foreign('visited_team_id')->references('id')->on('teams')->nullOnDelete();
            $table->foreign('visiting_team_id')->references('id')->on('teams')->nullOnDelete();
        });
    }
};
