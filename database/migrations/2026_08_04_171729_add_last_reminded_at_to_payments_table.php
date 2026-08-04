<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The reminder counter was stored, the date was not. A treasurer decides from the
 * date — "it has been three weeks" — so without it the screen cannot answer the
 * question that triggers the action.
 */
return new class extends Migration
{
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn('last_reminded_at');
        });
    }

    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->timestamp('last_reminded_at')->nullable()->after('invitation_counter');
        });
    }
};
