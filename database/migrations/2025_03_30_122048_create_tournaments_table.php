<?php

declare(strict_types=1);

use App\Enums\TournamentStatusEnum;
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
        Schema::dropIfExists('tournaments');
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->datetime('start_date')->nullable();
            $table->datetime('end_date')->nullable();
            $table->integer('total_users')->default(0);
            $table->integer('max_users')->default(0);
            $table->integer('price')->default(0);
            $table->enum('status', collect(TournamentStatusEnum::cases())->pluck('value')->toArray());
            $table->boolean('has_handicap_points')->default(false);
            $table->timestamps();
        });
    }
};
