<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keep the captain's meet-up instructions.
 *
 * "RDV 19h au club, maillot rouge" was the most concrete thing a captain ever
 * told their players, and it lived nowhere: the Livewire property was handed to
 * the notification jobs and then reset, so the sentence existed only inside a
 * sent email. A player looking it up on the day had to go back to their inbox —
 * which is the trip the match page exists to remove.
 */
return new class extends Migration
{
    public function down(): void
    {
        Schema::table('interclubs', function (Blueprint $table): void {
            $table->dropColumn('captain_message');
        });
    }

    public function up(): void
    {
        Schema::table('interclubs', function (Blueprint $table): void {
            $table->text('captain_message')->nullable()->after('address');
        });
    }
};
