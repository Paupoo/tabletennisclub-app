<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * D'où vient une entrée de stock : de quelle tournée de courses.
 *
 * Sans ce lien, un casier entré au retour du magasin ne se distingue pas d'une
 * correction d'inventaire, et le valideur d'une note de frais ne peut pas
 * rapprocher le ticket de ce qui est réellement arrivé au bar.
 */
return new class extends Migration
{
    public function down(): void
    {
        Schema::table('bar_stock_movements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('restocking_id');
        });
    }

    public function up(): void
    {
        Schema::table('bar_stock_movements', function (Blueprint $table): void {
            $table->foreignId('restocking_id')
                ->nullable()
                ->after('order_item_id')
                ->constrained('bar_restockings')
                ->nullOnDelete();
        });
    }
};
