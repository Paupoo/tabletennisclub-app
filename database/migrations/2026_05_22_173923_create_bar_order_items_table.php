<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bar_order_items');
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bar_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')
                ->constrained('bar_orders')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('product_id')
                ->constrained('bar_products')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->integer('quantity')->unsigned()
                ->default(1);
            $table->integer('unit_price')->unsigned();
            $table->integer('total_price')->unsigned();
            // Audit fields
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('modified_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->timestamps();
        });
    }
};
