<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('meeting_user', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('guest_agenda_item_id');
            $table->dropColumn('is_guest');
        });
    }

    public function up(): void
    {
        Schema::table('meeting_user', function (Blueprint $table): void {
            // Someone outside the meeting's audience, asked to come in person —
            // the coach for the training point, say. Sees only the point named.
            $table->boolean('is_guest')->default(false)->after('status');
            $table->foreignId('guest_agenda_item_id')->nullable()->after('is_guest')
                ->constrained('meeting_agenda_items')->nullOnDelete();
        });
    }
};
