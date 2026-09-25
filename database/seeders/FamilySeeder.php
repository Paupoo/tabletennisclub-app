<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\ClubAdmin\Payments\GeneratePaymentReference;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use Illuminate\Database\Seeder;

/**
 * Des familles dans le club de démonstration.
 *
 * La base n'en avait aucune — `guardians = 0`. Deux branches de
 * `TransactionMatcher` en dépendent pourtant : l'IBAN de chaque tuteur, et son
 * nom sur le tiers d'un virement. Ajoutées pour reconnaître le parent qui paie
 * pour son enfant, elles n'avaient jamais tourné sur une base peuplée.
 *
 * La plupart des tuteurs ne jouent pas : ils n'existent que comme contact et
 * comme payeur. Un seul est aussi membre affilié — c'est la proportion d'un
 * vrai club, et c'est aussi le cas limite où le même IBAN paie deux choses.
 *
 * Chaque pupille porte une affiliation confirmée **et** sa créance ouverte :
 * sans elle, le virement du parent n'aurait rien à solder.
 */
class FamilySeeder extends Seeder
{
    public function run(): void
    {
        if (Guardian::query()->exists()) {
            $this->command?->info('Families already seeded — nothing to do.');

            return;
        }

        $season = Season::where('is_active', true)->first()
            ?? Season::orderByDesc('id')->first()
            ?? Season::factory()->create(['is_active' => true]);

        // Le tuteur des cas 9 et 11 : un seul virement pour deux enfants, un
        // seul remboursement sortant pour les deux.
        $this->family($season, 'Sophie', 'Martin', 'BE68539007547034', wards: 2);

        // Un seul pupille : le virement ne porte que le nom du parent, sans
        // référence — la branche « nom du tuteur sur le tiers ».
        $this->family($season, 'Karim', 'Benali', 'BE62510007547061', wards: 1);

        // Le parent qui joue aussi. Son `user_id` est renseigné, et il porte sa
        // propre affiliation en plus de celle de son enfant.
        $this->family($season, 'Élodie', 'Vanderheyden', 'BE43068999999501', wards: 1, plays: true);
    }

    /**
     * Une affiliation confirmée et la créance qui va avec.
     */
    private function affiliate(User $user, Season $season, bool $competitive): Subscription
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'season_id' => $season->id,
            'status' => 'confirmed',
            'is_competitive' => $competitive,
            'amount_due' => $competitive ? 125 : 60,
            'amount_paid' => 0,
        ]);

        $subscription->payments()->create([
            'reference' => (new GeneratePaymentReference)(),
            'amount_due' => $subscription->amount_due,
            'amount_paid' => 0,
            'status' => 'pending',
        ]);

        return $subscription;
    }

    private function family(
        Season $season,
        string $firstName,
        string $lastName,
        string $iban,
        int $wards,
        bool $plays = false,
    ): void {
        $account = null;

        if ($plays) {
            $account = User::factory()->create([
                'first_name' => $firstName,
                'last_name' => $lastName,
            ]);

            $this->affiliate($account, $season, competitive: true);
        }

        $guardian = Guardian::create([
            'user_id' => $account?->id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => '0475' . random_int(100000, 999999),
            'email' => mb_strtolower($firstName . '.' . $lastName) . '@example.test',
            'iban' => $iban,
        ]);

        for ($i = 0; $i < $wards; $i++) {
            // Compte géré : l'e-mail est nul, le contact passe par le tuteur.
            // C'est la forme réelle d'un enfant inscrit par ses parents.
            $ward = User::factory()->create([
                'last_name' => $lastName,
                'email' => null,
            ]);

            $guardian->users()->attach($ward->id);
            $this->affiliate($ward, $season, competitive: false);
        }
    }
}
