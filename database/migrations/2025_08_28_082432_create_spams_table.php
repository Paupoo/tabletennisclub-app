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
        Schema::dropIfExists('spams');
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('spams', function (Blueprint $table) {
            $table->id();
            $table->string('ip')->nullable();
            $table->text('user_agent')->nullable();
            $table->json('inputs')->nullable();
            $table->boolean('is_blocked')->default(false);
            $table->timestamps();
        });

    }
};
