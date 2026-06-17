<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function down(): void
    {
        DB::table('news_posts')->update(['reading_time' => null]);
    }

    public function up(): void
    {
        DB::table('news_posts')
            ->whereNull('reading_time')
            ->orderBy('id')
            ->each(function (object $post) {
                $words = str_word_count(strip_tags($post->content ?? ''));
                DB::table('news_posts')
                    ->where('id', $post->id)
                    ->update(['reading_time' => (int) ceil($words / 225)]);
            });
    }
};
