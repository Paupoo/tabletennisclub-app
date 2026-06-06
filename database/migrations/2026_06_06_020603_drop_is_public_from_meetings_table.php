<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->boolean('is_public')->default(false)->after('format');
        });
    }

    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn('is_public');
        });
    }
};
