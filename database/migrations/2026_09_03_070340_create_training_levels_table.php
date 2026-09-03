<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Les six niveaux de l'enum supprimé, avec le libellé du site public — le
     * back-office en utilisait un autre pour les mêmes choses, ce que la table
     * ne permet plus.
     *
     * `value` et `name` sont là pour la reprise : `training_packs.level`
     * stockait la valeur, `trainings.level` le nom. Les deux colonnes sont des
     * enums SQL construits sur des choses différentes — incohérence historique
     * qui disparaît ici.
     *
     * @var list<array{name: string, value: string, label: string, color: string}>
     */
    private const array SEED = [
        ['name' => 'KIDS', 'value' => 'Kids', 'label' => 'Jeunes', 'color' => 'warning'],
        ['name' => 'BEGINNERS', 'value' => 'Beginners', 'label' => 'Débutant', 'color' => 'success'],
        ['name' => 'YOUNG_POTENTIAL', 'value' => 'Young potential', 'label' => 'Jeunes espoirs', 'color' => 'info'],
        ['name' => 'INTERMEDIATE', 'value' => 'Intermediate', 'label' => 'Confirmé', 'color' => 'error'],
        ['name' => 'ELITE', 'value' => 'Elite', 'label' => 'Compétition', 'color' => 'error'],
        ['name' => 'OPEN', 'value' => 'Open', 'label' => 'Tous niveaux', 'color' => 'primary'],
    ];

    public function down(): void
    {
        Schema::table('trainings', function (Blueprint $table): void {
            $table->string('level')->nullable();
        });

        Schema::table('training_packs', function (Blueprint $table): void {
            $table->string('level')->nullable();
        });

        // On restitue les chaînes telles qu'elles étaient : le nom côté séance,
        // la valeur côté pack.
        foreach (DB::table('training_levels')->get() as $level) {
            DB::table('trainings')->where('training_level_id', $level->id)
                ->update(['level' => $level->legacy_name]);
            DB::table('training_packs')->where('training_level_id', $level->id)
                ->update(['level' => $level->legacy_value]);
        }

        Schema::table('trainings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('training_level_id');
        });

        Schema::table('training_packs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('training_level_id');
        });

        Schema::dropIfExists('training_levels');
    }

    public function up(): void
    {
        Schema::create('training_levels', function (Blueprint $table): void {
            $table->id();
            $table->string('label');
            // Classe daisyUI de la pastille (success, warning, error, info…).
            // La couleur vivait dans un `match` du code : ajouter un niveau
            // obligeait à toucher une vue.
            $table->string('color')->default('primary');
            $table->unsignedSmallInteger('position')->default(0);
            // Désactiver plutôt que supprimer : les packs des saisons passées
            // gardent leur niveau, il sort seulement des listes déroulantes.
            $table->boolean('is_active')->default(true);
            // Gardés pour la reprise et pour un éventuel `down()`.
            $table->string('legacy_name')->nullable();
            $table->string('legacy_value')->nullable();
            $table->timestamps();
        });

        $now = now();

        DB::table('training_levels')->insert(array_map(
            fn (array $level, int $index): array => [
                'label' => $level['label'],
                'color' => $level['color'],
                'position' => $index + 1,
                'is_active' => true,
                'legacy_name' => $level['name'],
                'legacy_value' => $level['value'],
                'created_at' => $now,
                'updated_at' => $now,
            ],
            self::SEED,
            array_keys(self::SEED),
        ));

        Schema::table('training_packs', function (Blueprint $table): void {
            $table->foreignId('training_level_id')->nullable()->after('name')
                ->constrained('training_levels')->nullOnDelete();
        });

        Schema::table('trainings', function (Blueprint $table): void {
            $table->foreignId('training_level_id')->nullable()->after('id')
                ->constrained('training_levels')->nullOnDelete();
        });

        foreach (DB::table('training_levels')->get() as $level) {
            DB::table('training_packs')->where('level', $level->legacy_value)
                ->update(['training_level_id' => $level->id]);
            DB::table('trainings')->where('level', $level->legacy_name)
                ->update(['training_level_id' => $level->id]);
        }

        Schema::table('training_packs', function (Blueprint $table): void {
            $table->dropColumn('level');
        });

        Schema::table('trainings', function (Blueprint $table): void {
            $table->dropColumn('level');
        });
    }
};
