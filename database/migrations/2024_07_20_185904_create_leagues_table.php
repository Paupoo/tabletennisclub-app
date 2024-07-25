<?php

use App\Enums\LeagueCategory;
use App\Enums\LeagueLevel;
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
        Schema::create('leagues', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('division');
            $table->enum('level', array_column(LeagueLevel::cases(), 'value'));
            $table->enum('category', array_column(LeagueCategory::cases(), 'value'));
            $table->unsignedSmallInteger('start_year');
            $table->unsignedSmallInteger('end_year');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leagues');
    }
};
