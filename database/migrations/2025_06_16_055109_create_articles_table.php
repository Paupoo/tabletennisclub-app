<?php

declare(strict_types=1);

use App\Enums\ArticlesCategoryEnum;
use App\Enums\ArticlesStatusEnum;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('articles');
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->slug('slug')->unique();
            $table->text('content');
            $table->enum('category', ArticlesCategoryEnum::values());
            $table->string('image')->nullable();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->string('tags')->nullable();
            $table->enum('status', ArticlesStatusEnum::values())->default(ArticlesStatusEnum::DRAFT->value);
            $table->boolean('is_public')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });
    }
};
