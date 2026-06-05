<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interclubs', function (Blueprint $table): void {
            $table->boolean('is_bye')->default(false)->after('total_players');
        });
    }

    public function down(): void
    {
        Schema::table('interclubs', function (Blueprint $table): void {
            $table->dropColumn('is_bye');
        });
    }
};
