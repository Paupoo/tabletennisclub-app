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
        Schema::table('interclub_user', function (Blueprint $table) {
            $table->dropColumn(['availability', 'availability_note', 'selection_confirmed_at']);
        });
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('interclub_user', function (Blueprint $table) {
            $table->string('availability')->nullable()->after('is_subscribed');
            $table->string('availability_note')->nullable()->after('availability');
            $table->timestamp('selection_confirmed_at')->nullable()->after('has_played');
        });
    }
};
