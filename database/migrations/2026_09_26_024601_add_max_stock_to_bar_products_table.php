<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * La cible du réassort, par produit.
 *
 * Le min existe déjà : c'est `low_stock_threshold`, qui décide à la fois de
 * l'alerte au comptoir et de l'entrée dans la liste de courses. Le max dit
 * jusqu'où remonter le stock quand on fait les courses.
 *
 * Nullable à dessein, et avec un sens contraire à celui du seuil : un max absent
 * veut dire « hors réassort » — produit saisonnier, retiré, acheté à l'occasion —
 * et non « prends un défaut ». Personne ne doit rapporter un casier d'un produit
 * que le comité a décidé de ne plus racheter.
 */
return new class extends Migration
{
    public function down(): void
    {
        Schema::table('bar_products', function (Blueprint $table): void {
            $table->dropColumn('max_stock');
        });
    }

    public function up(): void
    {
        Schema::table('bar_products', function (Blueprint $table): void {
            $table->unsignedSmallInteger('max_stock')
                ->nullable()
                ->after('low_stock_threshold');
        });
    }
};
