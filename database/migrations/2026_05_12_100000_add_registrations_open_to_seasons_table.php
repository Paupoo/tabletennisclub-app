<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            $table->dropColumn('registrations_open');
        });
    }

    public function up(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            $table->boolean('registrations_open')->default(false)->after('is_active');
        });
    }
};
