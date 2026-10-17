<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ce qui a été acheté se compte en unités : le magasin n'a pas toujours le
 * conditionnement prévu (un pack de 6 au lieu d'un casier de 8). Les tournées
 * closes se convertissent sans perte, au conditionnement figé sur leur ligne.
 */
return new class extends Migration
{
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bar_restocking_lines', function (Blueprint $table): void {
            $table->unsignedSmallInteger('bought_packs')->nullable()->after('in_cart');
        });

        // Arrondi au conditionnement inférieur : une quantité libre ne se décrit pas en paquets.
        DB::table('bar_restocking_lines')
            ->whereNotNull('bought_units')
            ->update(['bought_packs' => DB::raw('bought_units / pack_size')]);

        Schema::table('bar_restocking_lines', function (Blueprint $table): void {
            $table->dropColumn('bought_units');
        });
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bar_restocking_lines', function (Blueprint $table): void {
            $table->unsignedSmallInteger('bought_units')->nullable()->after('in_cart');
        });

        DB::table('bar_restocking_lines')
            ->whereNotNull('bought_packs')
            ->update(['bought_units' => DB::raw('bought_packs * pack_size')]);

        Schema::table('bar_restocking_lines', function (Blueprint $table): void {
            $table->dropColumn('bought_packs');
        });
    }
};
