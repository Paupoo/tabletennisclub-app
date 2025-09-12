<?php

declare(strict_types=1);

use App\Enums\LeagueCategory;
use App\Enums\LeagueLevel;
use App\Models\Season;
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
        Schema::dropIfExists('leagues');
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('leagues', function (Blueprint $table) {
            $table->id();
            $table->string('division');
            $table->string('level');
            $table->string('category');
            $table->foreignIdFor(Season::class)->constrained();
            $table->timestamps();
        });
    }
};
