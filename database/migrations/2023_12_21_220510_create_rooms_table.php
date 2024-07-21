<?php

use App\Models\Club;
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
            $table->string('name', 100)->unique();
            $table->string('building_name', 255)->nullable();
            $table->string('street', 100);
            $table->char('city_code', 4);
            $table->string('city_name');
            $table->string('floor', 2)->nullable();
            $table->string('access_description', 255)->nullable();
            $table->unsignedTinyInteger('capacity_for_trainings');
            $table->unsignedTinyInteger('capacity_for_interclubs');
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
