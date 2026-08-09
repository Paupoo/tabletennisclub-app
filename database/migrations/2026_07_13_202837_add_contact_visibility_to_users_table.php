<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('contact_visibility');
        });
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Opt-in per-field contact sharing: null / missing key = hidden.
            // Keys: 'phone', 'email', 'address'.
            $table->json('contact_visibility')->nullable()->after('notification_preferences');
        });
    }
};
