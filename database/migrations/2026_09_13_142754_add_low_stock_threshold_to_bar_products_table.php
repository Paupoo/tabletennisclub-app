<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Le seuil d'alerte de stock, par produit.
 *
 * Il était écrit en dur à deux endroits — `$realStock <= 3` dans l'écran de vente
 * et `$stock <= 3` dans l'écran produits — et la même valeur pour tout le bar :
 * or un fût à 3 est en rupture, un paquet de chips à 3 va très bien.
 *
 * Nullable à dessein : une valeur absente signifie « prends le défaut du bar »
 * (BarProduct::LOW_STOCK_THRESHOLD), et non « pas d'alerte ». Un produit créé
 * sans y penser reste donc surveillé.
 */
return new class extends Migration
{
    public function down(): void
    {
        Schema::table('bar_products', function (Blueprint $table): void {
            $table->dropColumn('low_stock_threshold');
        });
    }

    public function up(): void
    {
        Schema::table('bar_products', function (Blueprint $table): void {
            $table->unsignedSmallInteger('low_stock_threshold')
                ->nullable()
                ->after('is_available');
        });
    }
};
