<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * L'inventaire du bar, et ce qui a été compté produit par produit.
 *
 * Une ligne garde l'attendu au moment où le nombre a été saisi, pas à
 * l'ouverture ni à la validation : le bar vend pendant qu'on compte, et seul
 * l'attendu du moment du comptage donne un écart juste. La validation applique
 * cet écart au stock du moment, ce qui laisse en place les ventes faites après.
 *
 * `unit_price` est le prix de vente figé à la validation : une hausse de prix
 * l'an prochain ne doit pas réécrire la valeur des pertes d'aujourd'hui.
 *
 * `opened_by` accepte le vide pour les inventaires repris des corrections de
 * l'ancien champ Stock, dont certaines n'ont pas d'auteur.
 */
return new class extends Migration
{
    public function down(): void
    {
        Schema::dropIfExists('bar_inventory_lines');
        Schema::dropIfExists('bar_inventories');
    }

    public function up(): void
    {
        Schema::create('bar_inventories', function (Blueprint $table): void {
            $table->id();
            $table->string('status', 20)->index();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->string('comment', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('bar_inventory_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_id')->constrained('bar_inventories')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('bar_products')->restrictOnDelete();
            $table->integer('expected');
            $table->unsignedInteger('counted');
            $table->foreignId('counted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('counted_at');
            $table->string('cause', 40)->nullable();
            $table->string('note', 255)->nullable();
            $table->unsignedInteger('unit_price')->nullable();
            $table->timestamps();

            $table->unique(['inventory_id', 'product_id']);
        });
    }
};
