<?php

declare(strict_types=1);

use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Models\TrainingPlan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::dropIfExists('training_plan_packs');
    }

    public function up(): void
    {
        Schema::create('training_plan_packs', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(TrainingPlan::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(TrainingPack::class, 'source_training_pack_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('level')->nullable();
            $table->unsignedTinyInteger('day_of_week')->nullable();
            $table->integer('max_participants')->nullable();
            $table->integer('position')->default(0);
            $table->timestamps();
        });
    }
};
