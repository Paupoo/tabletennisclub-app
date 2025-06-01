<?php

declare(strict_types=1);

use App\Models\Club;
use App\Models\Room;
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
        Schema::create('club_room', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Club::class)->constrained();
            $table->foreignIdFor(Room::class)->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('club_room');
    }
};
