<?php

declare(strict_types=1);

use App\Enums\Gender;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Rend le mot de passe optionnel pour permettre une invitation par mail, sans mot de passe pour la première connexion.
            $table->string('password', 255)->nullable()->change();
            // Met à jour la définition de l'enum (l'Enum PHP a changé, ajout d'un nouveau case, mise à jour de la DB)
            $table->enum('sex', array_column(Gender::cases(), 'name'))->default(Gender::MEN->name)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. On récupère tous les IDs des utilisateurs qui n'ont pas de mot de passe
        $usersWithoutPassword = DB::table('users')->whereNull('password')->pluck('id');

        // 2. Pour chaque utilisateur, on génère un mot de passe unique
        foreach ($usersWithoutPassword as $id) {
            DB::table('users')
                ->where('id', $id)
                ->update([
                    'password' => Hash::make(Str::random(12))
                ]);
        }

        // 3. On remet la contrainte NOT NULL et on remet à jour les Enums.
        Schema::table('users', function (Blueprint $table) {
            $table->string('password', 255)->nullable(false)->change();

            $table->enum('sex', array_column(Gender::cases(), 'name'))
                ->default(Gender::MEN->name)
                ->change();
        });
    }
};
