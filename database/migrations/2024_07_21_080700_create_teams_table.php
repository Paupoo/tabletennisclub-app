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
            $table->string('letter', 4);
            $table->foreignIdFor(Season::class)->nullable()->constrained();
            $table->foreignIdFor(League::class)->nullable()->consrained();
            $table->foreignIdFor(Club::class)->constrained();
            $table->unsignedBigInteger('captain_id')->nullable();
            $table->timestamps();

            $table->foreign('captain_id')->references('id')->on('users');
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
