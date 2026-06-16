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
        Schema::table('seasons', function (Blueprint $table) {
            $table->dropIndex('seasons_is_active_index');
            $table->dropColumn('is_active');
        });
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            $table->boolean('is_active')->default(false);
            $table->index('is_active');
        });
    }
};
