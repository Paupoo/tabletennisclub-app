<?php

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
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('interest');
            $table->text('message');
            $table->unsignedTinyInteger('membership_family_members')->nullable();
            $table->unsignedTinyInteger('membership_competitors')->nullable();
            $table->unsignedTinyInteger('membership_training_sessions')->nullable();
            $table->unsignedInteger('membership_total_cost')->nullable();
            $table->enum('status', ['new', 'pending', 'processed', 'rejected'])->default('new');
            $table->foreignIdFor(User::class, 'owner_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
