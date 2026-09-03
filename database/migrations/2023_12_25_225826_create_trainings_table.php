<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\TrainingType;
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
            // Valeurs figées en littéral : l'enum TrainingLevel dont elles
            // venaient a été remplacé par la table `training_levels`. Une
            // migration doit rester rejouable sans dépendre du code vivant.
            $table->enum('level', ['KIDS', 'BEGINNERS', 'YOUNG_POTENTIAL', 'INTERMEDIATE', 'ELITE', 'OPEN']);
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
