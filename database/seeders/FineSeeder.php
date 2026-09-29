<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\ClubAdmin\Fines\Actions\IssueFine;
use App\Domains\ClubAdmin\Fines\Models\Fine;
use App\Domains\ClubAdmin\Fines\Services\FineCreditor;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\FineReason;
use Illuminate\Database\Seeder;

class FineSeeder extends Seeder
{
    /**
     * The provincial committee's account, and one fine per case the screens
     * tell apart: still payable, past its deadline, on a minor with guardians,
     * and cancelled.
     *
     * The e-mails are deliberately NOT sent here: seeding must stay side-effect
     * free. Issuing a fine from the UI goes through {@see IssueFine}, which
     * notifies.
     */
    public function run(): void
    {
        // The CPBBW's own details, as its « Amendes & pertes de qualification »
        // mail gives them.
        app(FineCreditor::class)->update(
            'CPBBW',
            'BE50 2100 3624 5518',
            'Didier Tourneur (trésorier)',
            'didier.tourneur@skynet.be',
            '+32 477 89 54 30',
        );

        $member = User::find(1);

        if (! $member) {
            $this->command?->info('Aucun utilisateur #1 — FineSeeder ignoré.');

            return;
        }

        // The treasurer issues them when present, otherwise fall back to the member.
        $issuer = User::where('email', 'gilles.herpigny@test.com')->first() ?? $member;

        $this->fine($member, $issuer, [
            'reason' => FineReason::UNANNOUNCED_ABSENCE,
            'amount' => 35,
            'event_date' => today()->subDays(10),
            'event_label' => 'LA HULPE RIXENSART',
            'payment_deadline' => today()->addDays(14),
        ]);

        $this->fine($member, $issuer, [
            'reason' => FineReason::REFEREEING,
            'amount' => 15,
            'event_date' => today()->subMonths(3),
            'event_label' => 'CHAMP. SEN.',
            'payment_deadline' => today()->subMonths(2),
        ]);

        $minor = User::query()
            ->whereHas('guardians')
            ->whereDate('birthdate', '>', today()->subYears(18))
            ->orderBy('id')
            ->first();

        if ($minor) {
            $this->fine($minor, $issuer, [
                'reason' => FineReason::ANNOUNCED_ABSENCE,
                'amount' => 14,
                'event_date' => today()->subWeek(),
                'event_label' => 'CHAMP. JEUNES',
                'payment_deadline' => today()->addWeeks(3),
                'description' => 'Amende 10 € + droit d\'inscription 4 €.',
            ]);
        }

        $this->fine($member, $issuer, [
            'reason' => FineReason::INTERCLUB_MATCH_NOT_PLAYED,
            'amount' => 10,
            'event_date' => today()->subWeeks(5),
            'event_label' => 'IC PBBWH15/027',
            'payment_deadline' => today()->subWeek(),
        ])->delete();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function fine(User $member, User $issuer, array $attributes): Fine
    {
        /** @var FineReason $reason */
        $reason = $attributes['reason'];

        return Fine::create([
            'user_id' => $member->id,
            'issued_by' => $issuer->id,
            'provincial_code' => $reason->provincialCode(),
            'pedagogical_message' => implode("\n\n", [
                "Bonjour {$member->first_name},",
                "Le comité provincial a émis une amende vous concernant ({$reason->label()}). Le club vous la transmet, mais veut surtout vous aider à l'éviter la prochaine fois.",
                'Un petit message à votre capitaine dès que vous savez que vous ne saurez pas jouer suffit généralement à éviter ce genre de situation.',
            ]),
            ...$attributes,
        ]);
    }
}
