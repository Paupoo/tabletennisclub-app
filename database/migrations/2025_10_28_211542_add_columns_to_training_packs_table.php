<?php

use App\Enums\TrainingLevel;
use App\Enums\TrainingType;
use App\Models\Room;
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
        Schema::table('training_packs', function (Blueprint $table) {
            $table->enum('level', array_column(TrainingLevel::cases(), 'name'));
            $table->enum('type', array_column(TrainingType::cases(), 'name'));
            $table->foreignIdFor(Room::class)->constrained();
            $table->unsignedBigInteger('trainer_id')->nullable();

            $table->foreign('trainer_id')->references('id')->on('users')->nullable()->constrained();
        });
    }

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
};
