<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournament_user', function (Blueprint $table) {
            $table->dropColumn(['payment_reminder_sent_at', 'payment_overdue_reminder_sent_at']);
        });
    }

    /**
     * Run the migrations.
     *
     * One trace per reminder, so the hourly scheduler sends each of them once.
     */
    public function up(): void
    {
        Schema::table('tournament_user', function (Blueprint $table) {
            $table->dateTime('payment_reminder_sent_at')->nullable()->after('payment_deadline');
            $table->dateTime('payment_overdue_reminder_sent_at')->nullable()->after('payment_reminder_sent_at');
        });
    }
};
