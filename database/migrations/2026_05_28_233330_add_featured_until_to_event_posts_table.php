<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('event_posts', function (Blueprint $table): void {
            $table->dropColumn('featured_until');
        });
    }

    public function up(): void
    {
        Schema::table('event_posts', function (Blueprint $table): void {
            $table->date('featured_until')->nullable()->after('featured');
        });
    }
};
