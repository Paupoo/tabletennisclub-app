<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Club\Models\Room;
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
        Schema::table('training_packs', function (Blueprint $table) {
            $table->dropColumn('level');
            $table->dropColumn('type');
        });
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('training_packs', function (Blueprint $table) {
            // Valeurs figées en littéral : l'enum TrainingLevel dont elles
            // venaient a été remplacé par la table `training_levels`. Une
            // migration doit rester rejouable sans dépendre du code vivant.
            $table->enum('level', ['Kids', 'Beginners', 'Young potential', 'Intermediate', 'Elite', 'Open']);
            $table->enum('type', array_column(TrainingType::cases(), 'value'));
            $table->foreignIdFor(Room::class)->constrained();
            $table->unsignedBigInteger('trainer_id')->nullable();

            $table->foreign('trainer_id')->references('id')->on('users')->nullable()->constrained();
        });
    }
};
