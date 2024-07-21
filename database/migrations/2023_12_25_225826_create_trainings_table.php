<?php

use App\Enums\TrainingLevel;
use App\Enums\TrainingType;
use App\Models\Room;
use App\Models\User;
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
        Schema::create('trainings', function (Blueprint $table) {
            $table->id();
            $table->enum('level', array_column(TrainingLevel::cases(), 'value'));
            $table->enum('type', array_column(TrainingType::cases(), 'value'));
            $table->dateTime('start', $precision = 0);
            $table->dateTime('end', $precision = 0);
            $table->foreignIdFor(Room::class)->constrained();
            $table->foreignIdFor(User::class, 'trainer_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trainings');
    }
};
