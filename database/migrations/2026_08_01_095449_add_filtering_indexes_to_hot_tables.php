<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the columns the treasury and roster screens filter on.
 *
 * InnoDB indexes foreign keys on its own, which covers the joins; nothing
 * covered the status and date columns those screens narrow by. Seven of the
 * seventy-one create migrations declare an index, and none of these four.
 *
 * Purely additive and replayable: no column is touched, so `down()` is a clean
 * reversal and a rollback loses nothing.
 */
return new class extends Migration
{
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropIndex('subscriptions_season_id_status_index');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex('payments_status_index');
        });

        Schema::table('contacts', function (Blueprint $table): void {
            $table->dropIndex('contacts_status_index');
        });

        Schema::table('seasons', function (Blueprint $table): void {
            $table->dropIndex('seasons_start_at_end_at_index');
        });
    }

    public function up(): void
    {
        // The roster reads one season at a time and splits it by status. The
        // existing FK index on season_id alone stops at the first column.
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->index(['season_id', 'status'], 'subscriptions_season_id_status_index');
        });

        // The treasury screens list payments by status (pending, paid, refunded).
        Schema::table('payments', function (Blueprint $table): void {
            $table->index('status', 'payments_status_index');
        });

        // The contacts inbox opens on the unprocessed ones.
        Schema::table('contacts', function (Blueprint $table): void {
            $table->index('status', 'contacts_status_index');
        });

        // "Which season covers this date" is asked on nearly every screen.
        Schema::table('seasons', function (Blueprint $table): void {
            $table->index(['start_at', 'end_at'], 'seasons_start_at_end_at_index');
        });
    }
};
