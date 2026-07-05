<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::dropIfExists('family_group_user');
    }

    public function up(): void
    {
        Schema::create('family_group_user', function (Blueprint $table): void {
            $table->foreignId('family_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['family_group_id', 'user_id']);
            $table->timestamps();
        });
    }
};
