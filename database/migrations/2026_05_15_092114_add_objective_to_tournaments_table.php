<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('objective');
        });
    }

    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->string('objective')->nullable()->after('match_type');
        });
    }
};
