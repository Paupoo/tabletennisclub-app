<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::dropIfExists('guardian_user');

        Schema::create('user_guardian', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guardian_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function up(): void
    {
        // Drop the old User→User guardian pivot (guardian_id pointed to users table)
        Schema::dropIfExists('user_guardian');

        Schema::create('guardian_user', function (Blueprint $table): void {
            $table->foreignId('guardian_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['guardian_id', 'user_id']);
            $table->timestamps();
        });
    }
};
