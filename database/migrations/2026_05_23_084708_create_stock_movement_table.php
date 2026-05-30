<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bar_stock_movements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('bar_products')
                ->cascadeOnDelete();

            $table->unsignedInteger('quantity');

            $table->enum('movement_type', ['IN', 'OUT']);

            $table->string('reason')->nullable();

            $table->unsignedInteger('remaining_quantity')->nullable();

            $table->foreignId('source_movement_id')
                ->nullable()
                ->constrained('bar_stock_movements')
                ->nullOnDelete();

            $table->foreignId('order_id')
                ->nullable()
                ->constrained('bar_orders')
                ->nullOnDelete();

            $table->foreignId('order_item_id')
                ->nullable()
                ->constrained('bar_order_items')
                ->nullOnDelete();

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

            $table->index(['product_id', 'movement_type']);
            $table->index(['product_id', 'remaining_quantity']);
            $table->index('source_movement_id');
            $table->index('order_item_id');
        });

        DB::statement('ALTER TABLE bar_stock_movements ADD CONSTRAINT chk_qty CHECK (quantity > 0)');
        DB::statement('ALTER TABLE bar_stock_movements ADD CONSTRAINT chk_remaining CHECK (remaining_quantity >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('bar_stock_movements');
    }
};