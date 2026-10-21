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
            $table->dropColumn('bracket_size');
        });
    }

    /**
     * Run the migrations.
     *
     * 4, 8 or 16 places. Null on a tournament saved before it existed: the
     * live centre then derives it from the pools, as it always did.
     */
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->unsignedTinyInteger('bracket_size')->nullable()->after('nb_qualifiers_per_pool');
        });
    }
};
