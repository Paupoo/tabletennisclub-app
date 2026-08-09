<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `tables.is_available` was cast and fillable but never written by any form,
 * never read by any query, and never displayed. Whether a table can be used
 * is answered by TableStateEnum::isPlayable(); this column only offered a
 * second, always-null answer to the same question.
 *
 * Not to be confused with `bar_products.is_available`, which is live.
 */
return new class extends Migration
{
    public function down(): void
    {
        Schema::table('tables', function (Blueprint $table): void {
            $table->boolean('is_available')->nullable();
        });
    }

    public function up(): void
    {
        Schema::table('tables', function (Blueprint $table): void {
            $table->dropColumn('is_available');
        });
    }
};
