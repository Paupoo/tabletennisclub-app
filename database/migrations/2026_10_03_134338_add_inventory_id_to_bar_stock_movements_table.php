<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * D'où vient une correction de stock : de quel inventaire.
 *
 * Comme `restocking_id` pour les courses : sans ce lien, une sortie d'inventaire
 * ne se distingue d'aucune autre, et les pertes ne se retrouvent pas.
 */
return new class extends Migration
{
    public function down(): void
    {
        Schema::table('bar_stock_movements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('inventory_id');
        });
    }

    public function up(): void
    {
        Schema::table('bar_stock_movements', function (Blueprint $table): void {
            $table->foreignId('inventory_id')
                ->nullable()
                ->after('restocking_id')
                ->constrained('bar_inventories')
                ->nullOnDelete();
        });
    }
};
