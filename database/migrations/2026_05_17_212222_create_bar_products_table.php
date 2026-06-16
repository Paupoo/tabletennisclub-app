<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::dropIfExists('bar_products');
    }

    public function up(): void
    {
        Schema::create('bar_products', function (Blueprint $table) {
            $table->id();

            $table->foreignId('category_id')
                ->constrained('bar_categories')
                ->onDelete('cascade');

            $table->string('name', 150)->unique();
            $table->integer('sale_price');
            $table->tinyInteger('is_available')->default(1);

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
