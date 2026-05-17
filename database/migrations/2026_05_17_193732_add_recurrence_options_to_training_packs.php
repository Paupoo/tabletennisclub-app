<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('training_packs', function (Blueprint $table) {
            $table->dropColumn(['days_of_week', 'pack_start_date', 'pack_end_date', 'excluded_dates']);
        });
    }

    public function up(): void
    {
        Schema::table('training_packs', function (Blueprint $table) {
            $table->json('days_of_week')->nullable()->after('day_of_week')
                ->comment('Multiple ISO weekdays (1=Mon…7=Sun) for multi-day recurrence');
            $table->date('pack_start_date')->nullable()->after('days_of_week')
                ->comment('Custom start date; overrides season start_at when set');
            $table->date('pack_end_date')->nullable()->after('pack_start_date')
                ->comment('Custom end date; overrides season end_at when set');
            $table->json('excluded_dates')->nullable()->after('pack_end_date')
                ->comment('ISO date strings (Y-m-d) excluded from session generation');
        });
    }
};
