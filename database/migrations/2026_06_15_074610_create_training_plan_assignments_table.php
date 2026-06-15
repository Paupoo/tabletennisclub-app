<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Trainings\Models\TrainingPlan;
use App\Domains\Trainings\Models\TrainingPlanPack;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::dropIfExists('training_plan_assignments');
    }

    public function up(): void
    {
        Schema::create('training_plan_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(TrainingPlan::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(TrainingPlanPack::class)->nullable()->constrained()->cascadeOnDelete();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->integer('position')->default(0);
            $table->timestamps();

            // A member appears at most once per plan (pool or a single pack).
            $table->unique(['training_plan_id', 'user_id']);
            $table->index(['training_plan_id', 'training_plan_pack_id']);
        });
    }
};
