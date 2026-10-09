<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('training_packs', function (Blueprint $table): void {
            $table->dropColumn(['external_price', 'externals_open_on']);
        });
    }

    /**
     * Un stage peut s'ouvrir aux non-membres.
     *
     * `externals_open_on` est l'opt-in : vide, le stage reste aux membres. Avant
     * la date, les membres ont la priorité ; après, la jauge est commune.
     * `external_price` est le prix proposé à un externe, `price` à défaut — en
     * euros, comme `price`.
     */
    public function up(): void
    {
        Schema::table('training_packs', function (Blueprint $table): void {
            $table->unsignedInteger('external_price')->nullable()->after('price');
            $table->date('externals_open_on')->nullable()->after('requires_approval');
        });
    }
};
