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
        Schema::table('tournament_user', function (Blueprint $table) {
            $table->dropForeign(['payment_id']);
            $table->dropColumn(['waitlist_position', 'confirmation_deadline', 'payment_deadline', 'payment_id']);
        });
    }

    public function up(): void
    {
        Schema::table('tournament_user', function (Blueprint $table) {
            $table->unsignedSmallInteger('waitlist_position')->nullable()->after('registration_status');
            $table->dateTime('confirmation_deadline')->nullable()->after('waitlist_position');
            $table->dateTime('payment_deadline')->nullable()->after('confirmation_deadline');
            $table->foreignId('payment_id')->nullable()->after('payment_deadline')
                ->constrained('payments')->nullOnDelete();
        });

        // Fix stale total_users counters: recalculate from actual pivot rows.
        DB::statement("
            UPDATE tournaments
            SET total_users = (
                SELECT COUNT(*) FROM tournament_user
                WHERE tournament_user.tournament_id = tournaments.id
                  AND tournament_user.registration_status IN ('registered', 'confirmed')
            )
        ");
    }
};
