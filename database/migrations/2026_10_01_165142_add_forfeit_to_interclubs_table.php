<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('interclubs', function (Blueprint $table): void {
            $table->dropColumn('forfeit');
        });
    }

    /**
     * Which side the federation has declared forfeit, if either.
     *
     * Read again from TabT on every sync rather than accumulated: a forfeit the
     * federation retracts must give the fixture back, and the members' answers
     * and selections it carried are kept dormant meanwhile, never deleted.
     */
    public function up(): void
    {
        Schema::table('interclubs', function (Blueprint $table): void {
            $table->string('forfeit', 32)->nullable()->after('is_bye');
        });
    }
};
