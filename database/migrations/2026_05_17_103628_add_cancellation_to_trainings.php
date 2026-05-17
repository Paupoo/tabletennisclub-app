<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('trainings', function (Blueprint $table) {
            $table->dropColumn(['status', 'cancellation_note', 'cancelled_at']);
        });
    }

    public function up(): void
    {
        Schema::table('trainings', function (Blueprint $table) {
            $table->string('status')->default('scheduled');
            $table->text('cancellation_note')->nullable();
            $table->timestamp('cancelled_at')->nullable();
        });
    }
};
