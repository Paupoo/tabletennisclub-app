<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('minutes_editor_id');
            $table->dropColumn('minutes_editor_at');
        });

        Schema::table('meeting_agenda_items', function (Blueprint $table): void {
            $table->dropColumn('discussed_at');
        });
    }

    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table): void {
            // Soft lock: who is currently taking notes, refreshed on every autosave.
            $table->foreignId('minutes_editor_id')->nullable()->after('archived_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('minutes_editor_at')->nullable()->after('minutes_editor_id');
        });

        Schema::table('meeting_agenda_items', function (Blueprint $table): void {
            $table->timestamp('discussed_at')->nullable()->after('description');
        });
    }
};
