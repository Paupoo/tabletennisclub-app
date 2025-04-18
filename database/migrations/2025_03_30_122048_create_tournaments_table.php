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
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->datetime('start_date')->nullable();
            $table->datetime('end_date')->nullable();
            $table->integer('total_users')->default(0);
            $table->integer('max_users')->default(0);
            $table->integer('price')->default(0);
            $table->string('status')->default('draft'); // draft, open, pending, closed
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tournaments');
    }
};
