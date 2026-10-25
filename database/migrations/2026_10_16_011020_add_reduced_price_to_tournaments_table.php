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
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn(['reduced_price', 'reduced_price_under_age']);
        });
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->integer('reduced_price')->nullable()->after('price');
            $table->unsignedTinyInteger('reduced_price_under_age')->nullable()->after('reduced_price');
        });
    }
};
