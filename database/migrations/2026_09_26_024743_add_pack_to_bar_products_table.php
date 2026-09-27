<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Comment un produit s'achète.
 *
 * Le stock se compte à l'unité vendue — une bouteille, une canette — mais au
 * magasin on prend un casier de 24 ou un pack de 6. Sans cette colonne, la liste
 * de courses dirait « 17 Jupiler » et laisserait la conversion à celui qui pousse
 * le caddie. Un seul conditionnement par produit : celui qu'on achète d'habitude.
 *
 * Le libellé est libre et facultatif (« casier », « pack », « carton ») ; la
 * taille vaut 1 par défaut, ce qui revient à acheter à l'unité.
 */
return new class extends Migration
{
    public function down(): void
    {
        Schema::table('bar_products', function (Blueprint $table): void {
            $table->dropColumn(['pack_size', 'pack_label']);
        });
    }

    public function up(): void
    {
        Schema::table('bar_products', function (Blueprint $table): void {
            $table->unsignedSmallInteger('pack_size')->default(1)->after('max_stock');
            $table->string('pack_label', 30)->nullable()->after('pack_size');
        });
    }
};
