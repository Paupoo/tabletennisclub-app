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
            $table->dropColumn('poll_sent_at');
        });
    }

    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table): void {
            // When the date poll was last sent to the committee (anti-spam window).
            $table->timestamp('poll_sent_at')->nullable()->after('minutes_editor_at');
        });
    }
};
