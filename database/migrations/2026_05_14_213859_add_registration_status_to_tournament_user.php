<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('tournament_user', function (Blueprint $table) {
            $table->dropColumn('registration_status');
        });
    }

    public function up(): void
    {
        Schema::table('tournament_user', function (Blueprint $table) {
            $table->enum('registration_status', ['registered', 'confirmed', 'no_show', 'cancelled'])
                ->default('registered')
                ->after('has_paid');
        });
    }
};
