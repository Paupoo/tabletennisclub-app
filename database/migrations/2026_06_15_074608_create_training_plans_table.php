<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::dropIfExists('training_plans');
    }

    public function up(): void
    {
        Schema::create('training_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Season::class)->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('status')->default('draft');
            $table->foreignIdFor(User::class, 'created_by')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }
};
