<?php

declare(strict_types=1);

use App\Models\ClubPosts\NewsPost;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropForeignIdFor(NewsPost::class);
            $table->dropColumn('news_post_id');
        });
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->foreignId('news_post_id')->nullable()->after('match_type')
                ->constrained('news_posts')->nullOnDelete();
        });
    }
};
