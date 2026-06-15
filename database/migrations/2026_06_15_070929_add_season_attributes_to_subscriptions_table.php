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
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropColumn([
                'can_drive',
                'seats_available',
                'wants_to_be_captain',
                'volunteer_help',
            ]);
        });
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->boolean('can_drive')->default(false)->after('is_competitive');
            $table->unsignedTinyInteger('seats_available')->nullable()->after('can_drive');
            $table->boolean('wants_to_be_captain')->default(false)->after('seats_available');
            $table->boolean('volunteer_help')->default(false)->after('wants_to_be_captain');
        });
    }
};
