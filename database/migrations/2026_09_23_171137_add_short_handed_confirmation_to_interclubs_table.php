<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The captain's word that the team will play one player short.
 *
 * The rules let a men's team start with three of its four players (C.25.6),
 * and three of four in the other categories' two of three (C.25.7). Nothing in
 * the lineup itself tells "we will play at three" from "someone dropped out and
 * nobody has dealt with it yet" — both leave the same confirmation stamps on the
 * remaining players. The declaration, and who made it, is what tells them apart.
 */
return new class extends Migration
{
    public function down(): void
    {
        Schema::table('interclubs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('short_handed_confirmed_by');
            $table->dropColumn('short_handed_confirmed_at');
        });
    }

    public function up(): void
    {
        Schema::table('interclubs', function (Blueprint $table): void {
            $table->timestamp('short_handed_confirmed_at')->nullable()->after('captain_message');
            $table->foreignId('short_handed_confirmed_by')->nullable()->after('short_handed_confirmed_at')
                ->constrained('users')->nullOnDelete();
        });
    }
};
