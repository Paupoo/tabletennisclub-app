<?php

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
            $table->dateTime('start', $precision = 0);
            $table->dateTime('end', $precision = 0);
            $table->foreignId('room_id');
            $table->enum('type', ['Directed', 'Free', 'Supervised']);
            $table->string('level');
            $table->string('trainer_name')->nullable();
            $table->decimal('price', 4, 2);
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
