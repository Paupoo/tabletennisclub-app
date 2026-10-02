<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('last_login_at');
        });
    }

    /**
     * Quand le membre s'est connecté pour la dernière fois, lui et non un tuteur
     * qui prend son siège.
     *
     * Une colonne et pas un journal : la liste des membres ne montre que la
     * dernière, et une connexion n'est pas une activité au club.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('last_login_at')->nullable()->after('renewal_reminded_at');
        });
    }
};
