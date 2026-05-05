<?php

declare(strict_types=1);

use App\Models\ClubEvents\Training\TrainingPack;
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
        Schema::table('trainings', function (Blueprint $table) {
            $table->dropForeign(['training_pack_id']);
            $table->dropColumn('training_pack_id');
        });
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('trainings', function (Blueprint $table) {
            $table->foreignIdFor(TrainingPack::class)->nullable()->constrained()->cascadeOnDelete();
        });
    }
};
