<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The poste a till movement is filed under when nothing else says it: the
 * difference a count found, already spread over the categories it is blamed
 * on. An income or an expense category's value — they never collide.
 */
return new class extends Migration
{
    public function down(): void
    {
        Schema::table('cash_register_entries', function (Blueprint $table): void {
            $table->dropColumn('category');
        });
    }

    public function up(): void
    {
        Schema::table('cash_register_entries', function (Blueprint $table): void {
            $table->string('category', 32)->nullable()->after('reason');
        });
    }
};
