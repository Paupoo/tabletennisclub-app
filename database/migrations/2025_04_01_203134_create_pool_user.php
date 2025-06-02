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
        Schema::dropIfExists('pool_user');
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pool_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pool_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            // Rendre la combinaison unique pour éviter les doublons
            $table->unique(['pool_id', 'user_id']);
        });
    }
};
