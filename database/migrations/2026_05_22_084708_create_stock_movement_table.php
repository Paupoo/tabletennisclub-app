<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bar_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                  ->constrained('bar_products')
                  ->onDelete('cascade');
            $table->integer('batch_id')->nullable();
            $table->integer('quantity');
            $table->enum('movement_type', ['IN', 'OUT']);
            $table->string('reason')->nullable();
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bar_stock_movements');
    }
};
