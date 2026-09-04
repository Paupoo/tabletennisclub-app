<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tells apart the two silences of an import run.
 *
 * `skipped_count` used to carry both: the affiliates a secretary deliberately
 * set aside, and the ones the listing simply had nothing new to say about. Read
 * back six months later those are opposite facts — a club that was already up to
 * date, against a hundred and fifty people somebody chose to leave out — and one
 * number could not say which.
 *
 * Existing runs keep a zero: they were counted before the screen could tell the
 * difference, and inventing a split for them would be worse than leaving it
 * unsaid.
 */
return new class extends Migration
{
    public function down(): void
    {
        Schema::table('member_imports', function (Blueprint $table): void {
            $table->dropColumn('unchanged_count');
        });
    }

    public function up(): void
    {
        Schema::table('member_imports', function (Blueprint $table): void {
            $table->unsignedInteger('unchanged_count')->default(0)->after('updated_count');
        });
    }
};
