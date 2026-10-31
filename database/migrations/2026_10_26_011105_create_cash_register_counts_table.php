<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every time the treasurer counted the till: what the books said, what the
 * drawer held. A count bounds the period its difference is blamed on, so a
 * count that matched is kept too.
 */
return new class extends Migration
{
    public function down(): void
    {
        Schema::dropIfExists('cash_register_counts');
    }

    public function up(): void
    {
        Schema::create('cash_register_counts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cash_register_id')->constrained()->cascadeOnDelete();
            $table->integer('expected');
            $table->integer('counted');
            $table->foreignId('counted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });
    }
};
