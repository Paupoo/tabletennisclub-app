<?php

use App\Models\Captain;
use App\Models\Club;
use App\Models\League;
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
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('letter', 1);
            $table->foreignIdFor(Season::class)->constrained();
            $table->foreignIdFor(League::class)->consrained();
            $table->foreignIdFor(Club::class)->constrained();
            $table->foreignIdFor(Captain::class)->constrained();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
