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
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('name')->unique();
            $table->string('street');
            $table->char('city_code', 4);
            $table->string('city_name');
            $table->string('building_name')->nullable();
            $table->string('access_description')->nullable();
            $table->unsignedSmallInteger('capacity_trainings');
            $table->unsignedSmallInteger('capacity_matches');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
