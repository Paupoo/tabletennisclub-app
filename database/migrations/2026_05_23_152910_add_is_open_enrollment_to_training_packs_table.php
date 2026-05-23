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
        Schema::table('training_packs', function (Blueprint $table) {
            $table->dropColumn('is_open_enrollment');
        });
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('training_packs', function (Blueprint $table) {
            $table->boolean('is_open_enrollment')->default(false)->after('is_active');
        });
    }
};
