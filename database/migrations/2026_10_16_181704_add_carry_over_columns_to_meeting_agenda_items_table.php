<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('meeting_agenda_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('carried_from_id');
            $table->dropColumn('carry_over_dismissed_at');
        });
    }

    public function up(): void
    {
        Schema::table('meeting_agenda_items', function (Blueprint $table): void {
            // The point a meeting did not reach, taken up again here.
            $table->foreignId('carried_from_id')->nullable()->after('meeting_id')
                ->constrained('meeting_agenda_items')->nullOnDelete();
            // Left unticked when the next meeting was prepared: it never comes back.
            $table->dateTime('carry_over_dismissed_at')->nullable()->after('discussed_at');
        });
    }
};
