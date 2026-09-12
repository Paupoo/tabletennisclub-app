<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records when a guardian was last handed the link that creates their account.
 *
 * The counterpart of `users.last_invited_at`, and for the same reason: a ward
 * with no address of their own is invited through their guardian, and without a
 * timestamp on the guardian there is nowhere to say so — the ward's own row
 * would read "not invited" forever, whatever the office sent.
 */
return new class extends Migration
{
    public function down(): void
    {
        Schema::table('guardians', function (Blueprint $table): void {
            $table->dropColumn('last_invited_at');
        });
    }

    public function up(): void
    {
        Schema::table('guardians', function (Blueprint $table): void {
            $table->timestamp('last_invited_at')->nullable()->after('iban');
        });
    }
};
