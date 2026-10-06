<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<int, string>
     */
    private const array HELP_TASKS = [
        'Tenir le bar',
        'Conduire et coacher des jeunes en interclub',
        'Encadrer un entraînement jeunes',
        'Arbitrer',
        'Encoder les résultats',
        'Organiser ou aider à organiser un tournoi',
        'Organiser ou aider à organiser une activité festive',
        'Site web et communication',
        'Entretien et montage de la salle',
    ];

    /**
     * The lists the club starts with. They are the club's words, typed by the
     * committee afterwards, so they are stored as written rather than as
     * translation keys. The permanent entry closes each list.
     *
     * @var array<int, string>
     */
    private const array THEMES = ['Entraînements', 'Stages', 'Tournois', 'Réunions', 'Interclubs', 'Bar'];

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('help_offer_task');
        Schema::dropIfExists('help_offers');
        Schema::dropIfExists('feedback_entries');
        Schema::dropIfExists('help_tasks');
        Schema::dropIfExists('feedback_themes');
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('feedback_themes', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 80);
            $table->unsignedSmallInteger('position');
            // « Autre » : always offered, so that no feedback is ever left
            // without a theme to file it under.
            $table->boolean('is_permanent')->default(false);
            $table->timestamp('hidden_at')->nullable();
            $table->timestamps();
        });

        Schema::create('help_tasks', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->unsignedSmallInteger('position');
            // « Rejoindre le comité » : the one offer the club always asks for.
            $table->boolean('is_permanent')->default(false);
            $table->timestamp('hidden_at')->nullable();
            $table->timestamps();
        });

        Schema::create('feedback_entries', function (Blueprint $table): void {
            $table->id();
            // Null when the member chose to stay anonymous. Nothing else on the
            // row points back at them: see SubmitFeedback.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('feedback_theme_id')->constrained()->restrictOnDelete();
            $table->text('body');
            $table->string('status', 20)->default('new');
            $table->timestamp('read_at')->nullable();
            $table->text('internal_note')->nullable();
            $table->timestamp('hidden_at')->nullable();
            $table->foreignId('hidden_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('hidden_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('help_offers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('rhythm', 20);
            $table->text('message')->nullable();
            $table->string('status', 20)->default('to_contact');
            $table->foreignId('handled_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('help_offer_task', function (Blueprint $table): void {
            $table->foreignId('help_offer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('help_task_id')->constrained()->restrictOnDelete();
            $table->primary(['help_offer_id', 'help_task_id']);
        });

        $now = now();

        DB::table('feedback_themes')->insert([
            ...array_map(static fn (string $name, int $index): array => [
                'name' => $name, 'position' => $index + 1, 'is_permanent' => false, 'created_at' => $now, 'updated_at' => $now,
            ], self::THEMES, array_keys(self::THEMES)),
            ['name' => 'Autre', 'position' => count(self::THEMES) + 1, 'is_permanent' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('help_tasks')->insert([
            ...array_map(static fn (string $name, int $index): array => [
                'name' => $name, 'position' => $index + 1, 'is_permanent' => false, 'created_at' => $now, 'updated_at' => $now,
            ], self::HELP_TASKS, array_keys(self::HELP_TASKS)),
            ['name' => 'Rejoindre le comité', 'position' => count(self::HELP_TASKS) + 1, 'is_permanent' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
};
