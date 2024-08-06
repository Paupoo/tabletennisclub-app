<?php

use App\Enums\LeagueCategory;
use App\Enums\LeagueLevel;
use App\Models\Season;
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
            $table->string('division');
            $table->enum('level', array_column(LeagueLevel::cases(), 'name'));
            $table->enum('category', array_column(LeagueCategory::cases(), 'name'));
            $table->foreignIdFor(Season::class)->constrained();
            $table->timestamps();
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
