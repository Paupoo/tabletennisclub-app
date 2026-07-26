<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The flag gates who may submit an affiliation for the season. It never had
 * anything to do with tournament registrations, which carry their own
 * open/close state, so the old name pointed readers at the wrong domain.
 */
return new class extends Migration
{
    public function down(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            $table->renameColumn('affiliations_open', 'registrations_open');
        });
    }

    public function up(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            $table->renameColumn('registrations_open', 'affiliations_open');
        });
    }
};
