<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table): void {
            $table->enum('status', ['new', 'pending', 'processed', 'rejected'])->default('new')->change();
        });
    }

    /**
     * Collapse the contact statuses from four to three.
     *
     * Rows sitting on `pending` go back to `new` so nothing slips through the
     * inbox, and the e-mail templates that used to apply `pending` follow the
     * same mapping. The backfill runs before the enum is narrowed, otherwise
     * MySQL would reject (or truncate) the leftover `pending` values.
     */
    public function up(): void
    {
        DB::table('contacts')->where('status', 'pending')->update(['status' => 'new']);
        DB::table('email_templates')->where('apply_status', 'pending')->update(['apply_status' => 'new']);

        Schema::table('contacts', function (Blueprint $table): void {
            $table->enum('status', ['new', 'processed', 'rejected'])->default('new')->change();
        });
    }
};
