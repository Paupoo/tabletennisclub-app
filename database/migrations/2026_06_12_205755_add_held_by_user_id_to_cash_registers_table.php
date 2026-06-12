<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('cash_registers', function (Blueprint $table): void {
            $table->dropForeignIdFor(User::class, 'held_by_user_id');
            $table->dropColumn('held_by_user_id');
        });
    }

    public function up(): void
    {
        Schema::table('cash_registers', function (Blueprint $table): void {
            $table->foreignId('held_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }
};
