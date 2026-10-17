<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('meeting_action_items', function (Blueprint $table): void {
            $table->dropColumn(['completed_at', 'abandoned_at', 'abandon_reason', 'progress_note', 'progress_noted_at']);
        });
    }

    public function up(): void
    {
        Schema::table('meeting_action_items', function (Blueprint $table): void {
            // When it was ticked done: an action closed since the last committee
            // comes back once, to be confirmed. Null on those done before.
            $table->dateTime('completed_at')->nullable()->after('is_completed');
            // Dropped by the committee, with the reason the minutes will read.
            $table->dateTime('abandoned_at')->nullable()->after('completed_at');
            $table->string('abandon_reason', 255)->nullable()->after('abandoned_at');
            // Where it stands, in the owner's words, between two meetings.
            $table->string('progress_note', 500)->nullable()->after('abandon_reason');
            $table->dateTime('progress_noted_at')->nullable()->after('progress_note');
        });
    }
};
