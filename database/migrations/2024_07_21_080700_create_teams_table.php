<?php

declare(strict_types=1);

use App\Models\Club;
use App\Models\League;
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
        Schema::dropIfExists('teams');
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name', 1);
            $table->foreignIdFor(League::class)->nullable()->constrained();
            $table->foreignIdFor(Club::class)->nullable()->constrained();
            $table->foreignIdFor(User::class, 'captain_id')->nullable()->nullOnDelete();
            $table->foreignIdFor(Season::class)->constrained();
            $table->timestamps();
        });
    }
};
