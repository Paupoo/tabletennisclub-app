<?php

declare(strict_types=1);

use App\Models\Season;
use App\Models\User;
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
        Schema::dropIfExists('subscriptions');
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Season::class);
            $table->foreignIdFor(User::class);
            $table->enum(column: 'status', allowed: ['pending', 'confirmed', 'paid', 'refunded', 'cancelled'])->default('pending');
            $table->boolean('is_competitive')->default(false);
            $table->boolean('has_other_family_members')->default(false);
            $table->unsignedTinyInteger('trainings_count')->default(0);
            $table->unsignedSmallInteger('subscription_price')->default(0);
            $table->unsignedSmallInteger('training_unit_price')->default(0);
            $table->unsignedSmallInteger('amount_due')->default(0);
            $table->unsignedSmallInteger('amount_paid')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
    }
};
