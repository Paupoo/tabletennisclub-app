<?php

declare(strict_types=1);

use App\Enums\TrainingLevel;
use App\Enums\TrainingType;
use App\Models\Room;
use App\Models\Season;
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
        Schema::dropIfExists('trainings');
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('trainings', function (Blueprint $table) {
            $table->id();
            $table->enum('level', array_column(TrainingLevel::cases(), 'name'));
            $table->enum('type', array_column(TrainingType::cases(), 'name'));
            $table->dateTime('start', $precision = 0);
            $table->dateTime('end', $precision = 0);
            $table->foreignIdFor(Room::class)->constrained();
            $table->unsignedBigInteger('trainer_id')->nullable();
            $table->foreignIdFor(Season::class);
            $table->timestamps();

            $table->foreign('trainer_id')->references('id')->on('users')->nullable()->constrained();
        });
    }
};
