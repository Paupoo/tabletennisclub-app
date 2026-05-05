<?php

declare(strict_types=1);

use App\Models\ClubAdmin\Subscription\Subscription;
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
        Schema::dropIfExists('subscription_training_pack');
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('subscription_training_pack', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Subscription::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(TrainingPack::class)->constrained()->cascadeOnDelete();
            $table->boolean('discount')->default(false);
            $table->timestamps();
        });
    }
};
