<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * La tournée de courses du bar, et la liste qu'elle a figée.
 *
 * Une tournée garde la liste telle qu'elle était au départ — stock, quantité
 * proposée, conditionnement — parce que tout cela bouge pendant qu'on est au
 * magasin : on vend, quelqu'un règle un max. La clôture doit porter sur ce que
 * la personne a eu sous les yeux, pas sur ce que l'écran calculerait après.
 *
 * `shopper_id` est celui qui tient la tournée *maintenant* : reprendre une
 * tournée change la personne, pas la liste ni les cases déjà cochées.
 */
return new class extends Migration
{
    public function down(): void
    {
        Schema::dropIfExists('bar_restocking_lines');
        Schema::dropIfExists('bar_restockings');
    }

    public function up(): void
    {
        Schema::create('bar_restockings', function (Blueprint $table): void {
            $table->id();
            $table->string('status', 20)->index();
            $table->foreignId('shopper_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('abandoned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('bar_restocking_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restocking_id')->constrained('bar_restockings')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('bar_products')->restrictOnDelete();
            $table->string('section', 10);
            $table->unsignedInteger('stock_at_start');
            $table->unsignedSmallInteger('pack_size');
            $table->string('pack_label', 30)->nullable();
            $table->unsignedSmallInteger('proposed_packs');
            $table->boolean('in_cart')->default(false);
            $table->unsignedSmallInteger('bought_packs')->nullable();
            $table->timestamps();

            $table->unique(['restocking_id', 'product_id']);
        });
    }
};
