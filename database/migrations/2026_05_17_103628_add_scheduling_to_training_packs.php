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
            $table->dropColumn(['day_of_week', 'start_time', 'duration_minutes', 'description', 'max_participants', 'is_active']);
        });
    }

    public function up(): void
    {
        Schema::table('training_packs', function (Blueprint $table) {
            $table->tinyInteger('day_of_week')->nullable()->comment('1=Mon … 7=Sun (ISO 8601)');
            $table->time('start_time')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('max_participants')->nullable();
            $table->boolean('is_active')->default(true);
        });
    }
};
