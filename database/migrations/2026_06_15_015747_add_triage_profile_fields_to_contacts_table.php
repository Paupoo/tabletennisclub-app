<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
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
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropConstrainedForeignIdFor(User::class);
            $table->dropColumn([
                'age_category',
                'experience',
                'wants_competition',
                'preferred_days',
                'family_can_drive',
            ]);
        });
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('age_category')->nullable();
            $table->string('experience')->nullable();
            $table->boolean('wants_competition')->nullable();
            $table->json('preferred_days')->nullable();
            $table->boolean('family_can_drive')->nullable();
            $table->foreignIdFor(User::class)->nullable()->constrained()->nullOnDelete();
        });
    }
};
