<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The column was once added inside the create migration, after production had
 * already run it: databases built since then have it, production does not.
 */
return new class extends Migration
{
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('tournaments', 'description')) {
            return;
        }

        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('tournaments', 'description')) {
            return;
        }

        Schema::table('tournaments', function (Blueprint $table) {
            $table->string('description')->nullable()->after('name');
        });
    }
};
