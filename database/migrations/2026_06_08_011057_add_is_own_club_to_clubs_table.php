<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->dropColumn('is_own_club');
        });
    }

    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->boolean('is_own_club')->default(false)->after('is_active');
        });

        // The "only one own club" invariant is enforced at the application layer
        // (Club::booted() demotes any other own club on save). A partial unique
        // index would be SQLite-only and crash on MySQL/MariaDB.
    }
};
