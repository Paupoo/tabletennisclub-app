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
        Schema::dropIfExists('family_groups');
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('family_groups', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });
    }
};
