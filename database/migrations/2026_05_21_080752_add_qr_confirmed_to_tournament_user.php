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
        Schema::table('tournament_user', function (Blueprint $table) {
            $table->dropColumn('qr_confirmed');
        });
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tournament_user', function (Blueprint $table) {
            $table->boolean('qr_confirmed')->default(false)->after('has_paid');
        });
    }
};
