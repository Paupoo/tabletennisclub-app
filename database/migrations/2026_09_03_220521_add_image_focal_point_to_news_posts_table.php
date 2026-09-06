<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where the featured image must stay visible once `object-cover` crops it.
 *
 * The article image is rendered in four container ratios (2.83:1, 2.56:1 and
 * 1.24:1 for the hero, 16:9 for the cards), so a single baked crop cannot
 * serve them all. Storing a focal point instead keeps the original intact and
 * lets every surface crop around the subject.
 *
 * The 50/50 default is the centre — exactly what `object-cover` does today,
 * so existing articles render unchanged.
 */
return new class extends Migration
{
    public function down(): void
    {
        Schema::table('news_posts', function (Blueprint $table): void {
            $table->dropColumn(['image_focal_x', 'image_focal_y']);
        });
    }

    public function up(): void
    {
        Schema::table('news_posts', function (Blueprint $table): void {
            $table->unsignedTinyInteger('image_focal_x')->default(50)->after('image');
            $table->unsignedTinyInteger('image_focal_y')->default(50)->after('image_focal_x');
        });
    }
};
