<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('event_posts', function (Blueprint $table): void {
            $table->dropColumn('type');
        });
    }

    public function up(): void
    {
        if (Schema::hasColumn('event_posts', 'type')) {
            return;
        }

        Schema::table('event_posts', function (Blueprint $table): void {
            $table->string('type')->nullable()->after('category');
        });

        DB::table('event_posts')->where('category', 'tournament')->update(['type' => 'TOURNAMENT']);
        DB::table('event_posts')->where('category', 'training')->update(['type' => 'TRAINING']);
        DB::table('event_posts')->where('category', 'club-life')->update(['type' => 'INTERCLUB']);
    }
};
