<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Ce que le réassort automatique a changé, et quand.
 *
 * Le journal d'audit le sait aussi, signé « Système » ; cette table-ci sert le
 * digest du samedi, qui doit dire en une ligne « Coca Zero : max 24 → 30 » sans
 * relire des différences JSON.
 */
return new class extends Migration
{
    public function down(): void
    {
        Schema::dropIfExists('bar_restocking_adjustments');
    }

    public function up(): void
    {
        Schema::create('bar_restocking_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('bar_products')->cascadeOnDelete();
            $table->unsignedSmallInteger('old_min')->nullable();
            $table->unsignedSmallInteger('new_min');
            $table->unsignedSmallInteger('old_max')->nullable();
            $table->unsignedSmallInteger('new_max');
            $table->timestamps();
        });
    }
};
