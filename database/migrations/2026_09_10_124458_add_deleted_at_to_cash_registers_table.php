<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A cash register is retired, never erased.
 *
 * `cash_register_entries.cash_register_id` cascades on delete, so a real DELETE
 * would take the whole ledger with it. Soft deleting keeps the books and lets a
 * register come back, the same way a key ring does.
 */
return new class extends Migration
{
    public function down(): void
    {
        Schema::table('cash_registers', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }

    public function up(): void
    {
        Schema::table('cash_registers', function (Blueprint $table): void {
            $table->softDeletes();
        });
    }
};
