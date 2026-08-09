<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\ClubAdmin\Payments\GeneratePaymentReference;
use App\Domains\ClubAdmin\Fines\Actions\IssueFine;
use App\Domains\ClubAdmin\Fines\Models\Fine;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\FineReason;
use Illuminate\Database\Seeder;

class FineSeeder extends Seeder
{
    /**
     * One demo fine for user #1, with its pending payment, so it shows up both in
     * the member's payments hub and on the treasury fines page.
     *
     * The educational e-mail is deliberately NOT sent here: seeding must stay
     * side-effect free. Issuing a fine from the UI goes through
     * {@see IssueFine}, which notifies.
     */
    public function run(): void
    {
        $member = User::find(1);

        if (! $member) {
            $this->command?->info('Aucun utilisateur #1 — FineSeeder ignoré.');

            return;
        }

        // The treasurer issues it when present, otherwise fall back to the member.
        $issuer = User::where('email', 'gilles.herpigny@test.com')->first() ?? $member;

        $fine = Fine::create([
            'user_id' => $member->id,
            'issued_by' => $issuer->id,
            'amount' => 15,
            'reason' => FineReason::UNJUSTIFIED_ABSENCE,
            'federation_reference' => 'AFTTB-2026-0042',
            'description' => 'Absence non annoncée lors de la rencontre du 12/04 contre CTT Limal-Wavre.',
            'pedagogical_message' => implode("\n\n", [
                "Bonjour {$member->first_name},",
                'La fédération a émis une amende vous concernant (absence injustifiée). Le club doit vous la répercuter, mais nous voulons surtout vous aider à l\'éviter la prochaine fois.',
                'Un petit message à votre capitaine dès que vous savez que vous ne saurez pas jouer suffit généralement à éviter ce genre de situation.',
                'Vous trouverez les détails de paiement dans votre espace paiements. Le comité reste disponible si vous souhaitez en parler.',
            ]),
        ]);

        $fine->payment()->create([
            'reference' => (new GeneratePaymentReference)(),
            'amount_due' => 15,
            'amount_paid' => 0,
            'status' => 'pending',
        ]);
    }
}
