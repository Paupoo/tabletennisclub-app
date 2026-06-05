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
        DB::table('event_posts')->where('status', 'DRAFT')->update(['status' => 'draft']);
        DB::table('event_posts')->where('status', 'PUBLISHED')->update(['status' => 'published']);
        DB::table('event_posts')->where('status', 'ARCHIVED')->update(['status' => 'archived']);

        Schema::table('event_posts', function (Blueprint $table): void {
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft')->change();
        });
    }

    public function up(): void
    {
        // Widen to varchar so MySQL accepts the new uppercase values during backfill
        Schema::table('event_posts', function (Blueprint $table): void {
            $table->string('status')->default('DRAFT')->change();
        });

        DB::table('event_posts')->where('status', 'draft')->update(['status' => 'DRAFT']);
        DB::table('event_posts')->where('status', 'published')->update(['status' => 'PUBLISHED']);
        DB::table('event_posts')->where('status', 'archived')->update(['status' => 'ARCHIVED']);
    }
};
