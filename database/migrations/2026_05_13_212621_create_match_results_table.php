<?php

declare(strict_types=1);

use App\Enums\InterclubResult;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::dropIfExists('match_results');
    }

    public function up(): void
    {
        Schema::create('match_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->date('match_date')->nullable();
            $table->unsignedSmallInteger('week_number')->nullable();
            $table->boolean('is_home')->default(true);
            $table->string('opponent_name', 100)->nullable();
            $table->string('score', 10)->nullable();
            $table->enum('result', array_column(InterclubResult::cases(), 'value'))->nullable();
            $table->boolean('is_bye')->default(false);
            $table->timestamps();
        });
    }
};
