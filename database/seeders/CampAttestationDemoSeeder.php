<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\ClubAdmin\Payments\AllocateTransactionAction;
use App\Actions\ClubAdmin\Subscriptions\EnrollInTrainingCampAction;
use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Subscriptions\Models\SubscriptionTrainingPack;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Trainings\Models\Training;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Services\TrainingCampBilling;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Notification;

/**
 * Four training camps on the admin's own account, one per state the member
 * screen « Attestations mutuelle » can show.
 *
 * - a summer camp, paid and over: available, with one cancelled session that
 *   must not be counted;
 * - a camp mixing a full day and two half days, paid and over: what makes
 *   Solidaris strike « jours » and count in half days;
 * - a camp over but unpaid: refused, with the balance due;
 * - a camp still to come, paid: « Disponible à partir du … ».
 *
 * Dates are relative to today, so the four states hold whenever it runs.
 * Rerunnable on its own — `php artisan db:seed --class=CampAttestationDemoSeeder`
 * removes its camps, their invoices and their attestations, then sows them
 * again. Development only, like {@see AttestationDemoSeeder}.
 */
class CampAttestationDemoSeeder extends Seeder
{
    private const string SUFFIX = ' (démo attestation)';

    public function run(): void
    {
        $member = User::where('email', 'aurelien.paulus@gmail.com')->first();

        if (! $member instanceof User) {
            $this->command?->warn('Compte admin introuvable : aucun stage de démo semé.');

            return;
        }

        $this->forget();

        $today = CarbonImmutable::today();
        $full = ['09:00', '16:00'];
        $half = ['09:00', '12:00'];

        $camps = [
            ["Stage d'été", -55, [$full, $full, $full, $full, $full], ['cancelled' => 5], 120.0, true],
            ['Stage du mercredi', -20, [$full, $half, $half], [], 45.0, true],
            ["Stage d'octobre", -6, [$full, $full, $full], [], 60.0, false],
            ['Stage de Toussaint', 9, [$full, $full, $full, $full, $full], [], 100.0, true],
        ];

        // The enrolment mail would land in Mailpit four times for nothing.
        $notifications = Notification::getFacadeRoot();
        Notification::fake();

        foreach ($camps as [$name, $offset, $days, $extra, $price, $paid]) {
            $start = $today->addDays($offset);
            $season = Season::where('start_at', '<=', $start)->where('end_at', '>=', $start)->first();
            $affiliation = $season instanceof Season
                ? Subscription::where('user_id', $member->id)->where('season_id', $season->id)
                    ->whereNotIn('status', ['cancelled', 'refunded'])->first()
                : null;

            if (! $affiliation instanceof Subscription) {
                $this->command?->warn(sprintf('%s : pas d\'affiliation pour le %s, stage ignoré.', $name, $start->format('d/m/Y')));

                continue;
            }

            $this->sow($affiliation, $name, $start, $days, $extra['cancelled'] ?? null, $price, $paid);
        }

        Notification::swap($notifications);
    }

    /**
     * Removes what an earlier run sowed. The attestations go with their line
     * (cascade), the payment credits with their payment.
     */
    private function forget(): void
    {
        $camps = TrainingPack::where('name', 'like', '%' . self::SUFFIX)->pluck('id');

        if ($camps->isEmpty()) {
            return;
        }

        $lines = SubscriptionTrainingPack::whereIn('training_pack_id', $camps)->pluck('id');

        Payment::where('payable_type', SubscriptionTrainingPack::class)->whereIn('payable_id', $lines)->delete();
        SubscriptionTrainingPack::whereIn('id', $lines)->delete();
        Training::whereIn('training_pack_id', $camps)->delete();
        TrainingPack::whereIn('id', $camps)->delete();
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $days  One [start, end] per consecutive date.
     * @param  int|null  $cancelledDay  A further date, after the last, whose session is cancelled.
     */
    private function sow(Subscription $affiliation, string $name, CarbonImmutable $start, array $days, ?int $cancelledDay, float $price, bool $paid): void
    {
        $room = Room::query()->orderBy('id')->first();
        $trainer = User::where('email', 'aloise.lejeune@coach.test')->first() ?? User::query()->orderBy('id')->first();
        $lastDay = $start->addDays(($cancelledDay ?? count($days) - 1));

        $camp = TrainingPack::factory()->camp()->create([
            'season_id' => $affiliation->season_id,
            'name' => $name . self::SUFFIX,
            'price' => $price,
            'pack_start_date' => $start->toDateString(),
            'pack_end_date' => $lastDay->toDateString(),
            'room_id' => $room?->id,
            'trainer_id' => $trainer?->id,
        ]);

        $sessions = $days;

        if ($cancelledDay !== null) {
            $sessions[$cancelledDay] = [$days[0][0], $days[0][1], 'cancelled_closed'];
        }

        foreach ($sessions as $day => $session) {
            $date = $start->addDays($day)->toDateString();

            Training::factory()->create([
                'training_pack_id' => $camp->id,
                'season_id' => $camp->season_id,
                'training_level_id' => $camp->training_level_id,
                'type' => $camp->type,
                'room_id' => $camp->room_id,
                'trainer_id' => $camp->trainer_id,
                'start' => $date . ' ' . $session[0],
                'end' => $date . ' ' . $session[1],
                'status' => $session[2] ?? 'scheduled',
                'cancelled_at' => isset($session[2]) ? now() : null,
            ]);
        }

        (new EnrollInTrainingCampAction)($affiliation, $camp, byClub: true);

        if ($paid) {
            $line = (new TrainingCampBilling)->line($affiliation, $camp);
            $claim = $line?->payments()->where('status', 'pending')->latest('id')->first();

            if ($claim instanceof Payment) {
                (new AllocateTransactionAction)->credit($claim, (float) $claim->amount_due, 'cash');
            }
        }
    }
}
