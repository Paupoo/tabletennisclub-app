<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the committee validated the affiliation.
 *
 * The club certifies that date to the mutual insurers, so it cannot keep living
 * in the activity log alone: a purged log, or an affiliation created straight
 * in `confirmed` by an import, would leave a stamped document with no date the
 * club can stand behind.
 */
return new class extends Migration
{
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropColumn('confirmed_at');
        });
    }

    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->timestamp('confirmed_at')->nullable()->after('status');
        });
    }
};
