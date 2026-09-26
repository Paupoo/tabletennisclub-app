<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Le réassort automatique, produit par produit (décidé le 2026-09-27).
 *
 * - `restocking_mode` : `auto`, `manual`, ou null pour suivre le réglage du bar ;
 * - `restocking_weeks` : combien de semaines de ventes le max couvre pour ce
 *   produit, à la place du réglage du bar — un périssable en couvre moins ;
 * - `restocking_cap` : le max que l'automatique ne dépasse jamais, la place au
 *   frigo par exemple ;
 * - `restocking_adjusted_at` : le dernier ajustement automatique.
 */
return new class extends Migration
{
    public function down(): void
    {
        Schema::table('bar_products', function (Blueprint $table): void {
            $table->dropColumn(['restocking_mode', 'restocking_weeks', 'restocking_cap', 'restocking_adjusted_at']);
        });
    }

    public function up(): void
    {
        Schema::table('bar_products', function (Blueprint $table): void {
            $table->string('restocking_mode', 10)->nullable()->after('pack_label');
            $table->unsignedTinyInteger('restocking_weeks')->nullable()->after('restocking_mode');
            $table->unsignedSmallInteger('restocking_cap')->nullable()->after('restocking_weeks');
            $table->timestamp('restocking_adjusted_at')->nullable()->after('restocking_cap');
        });
    }
};
