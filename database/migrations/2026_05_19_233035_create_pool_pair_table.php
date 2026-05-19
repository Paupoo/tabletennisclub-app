<?php

declare(strict_types=1);

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
        Schema::dropIfExists('pool_pair');
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pool_pair', function (Blueprint $table) {
            $table->foreignId('pool_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tournament_pair_id')->constrained()->cascadeOnDelete();
            $table->primary(['pool_id', 'tournament_pair_id']);
        });
    }
};
