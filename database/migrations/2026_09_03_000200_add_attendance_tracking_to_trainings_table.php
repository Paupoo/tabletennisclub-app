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
        Schema::table('trainings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('attendance_taken_by');
            $table->dropColumn('attendance_taken_at');
        });
    }

    /**
     * Distinguer « personne n'est venu » de « personne n'a pointé ».
     *
     * Sans ces colonnes, un membre sans ligne de présence est indiscernable
     * entre absent et non renseigné : une seule séance oubliée mettait alors
     * tout un pack à 0 % et le comité lisait l'inverse de la réalité.
     *
     * La signature vit sur la séance et non sur le pivot : c'est la séance
     * qu'on valide, et `attendance_taken_by` dit aussi qui a corrigé après coup.
     */
    public function up(): void
    {
        Schema::table('trainings', function (Blueprint $table): void {
            $table->timestamp('attendance_taken_at')->nullable()->after('cancelled_at');
            $table->foreignIdFor(User::class, 'attendance_taken_by')
                ->nullable()
                ->after('attendance_taken_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }
};
