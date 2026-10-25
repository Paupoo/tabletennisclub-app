<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\ClubAdmin\Payments\AllocateTransactionAction;
use App\Actions\ClubAdmin\Payments\GeneratePaymentReference;
use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\ClubAdmin\Club\Models\Table;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentRegistration;
use App\Domains\Competitions\Tournament\Services\TournamentService;
use App\Domains\Shared\Enums\Ranking;
use App\Domains\Shared\Enums\TournamentObjectiveEnum;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A club doubles tournament to rehearse the day on, shaped like the real club.
 *
 * The field copies the distribution of the club's members in October 2026 —
 * the rankings as they stand, the birth years, the share of children whose
 * account a parent holds, the families entered together — under invented
 * names and addresses. No real person is in it.
 *
 * Thirty-two players are registered, sixteen pairs' worth: twenty-two have
 * paid, ten have not, and two members stay outside to be entered on the
 * morning.
 * The tournament is still open, so the rehearsal can run from the closing
 * of the registrations to the closure.
 *
 * `php artisan db:seed --class=DoublesTournamentRehearsalSeeder`
 */
class DoublesTournamentRehearsalSeeder extends Seeder
{
    public const string NAME = 'Tournoi de double du club — répétition';

    /**
     * Ranking, birth year, and the family a player belongs to (null: alone).
     * A child of a family is a managed account: no address, a guardian.
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string, 4: int, 5: string|null}>
     */
    private const array FIELD = [
        ['Benoît', 'Lambert', 'MEN', 'C0', 1978, null],
        ['Olivier', 'Dubois', 'MEN', 'C0', 1998, null],
        ['Mathieu', 'Renard', 'MEN', 'C0', 2002, null],
        ['Samuel', 'Leroy', 'MEN', 'C2', 2002, null],
        ['Pierre', 'Lemaire', 'MEN', 'C4', 1995, null],
        ['Jean', 'Collard', 'MEN', 'C6', 1995, null],
        ['Éric', 'Fontaine', 'MEN', 'C6', 1962, null],
        ['Simon', 'Wauters', 'MEN', 'D0', 1963, null],
        ['Paul', 'Simonet', 'MEN', 'D0', 1954, null],
        ['Gilles', 'Hardy', 'MEN', 'D2', 1976, null],
        ['Manon', 'Charlier', 'WOMEN', 'D2', 1988, null],
        ['Philippe', 'Gérard', 'MEN', 'D2', 1969, null],
        ['Louis', 'Delvaux', 'MEN', 'D4', 1957, null],
        ['Mario', 'Pirard', 'MEN', 'D4', 2003, null],
        ['Arnaud', 'Gillet', 'MEN', 'D6', 1989, null],
        ['Daniel', 'Masson', 'MEN', 'D6', 1961, null],
        ['Thierry', 'Hubert', 'MEN', 'E2', 1966, 'Hubert'],
        ['Sébastien', 'Mercier', 'MEN', 'E2', 1995, null],
        ['Xavier', 'Bastin', 'MEN', 'E4', 1977, null],
        ['Audrey', 'Lejeune', 'WOMEN', 'E4', 1993, null],
        ['Marc', 'Noël', 'MEN', 'E4', 1960, null],
        ['Jean-Pierre', 'Thiry', 'MEN', 'E0', 1977, 'Thiry'],
        ['Vincent', 'Joris', 'MEN', 'E6', 1971, null],
        ['Julien', 'Laurent', 'MEN', 'E6', 2004, null],
        ['Hugo', 'Thiry', 'MEN', 'E6', 2015, 'Thiry'],
        ['Siddharth', 'Kumar', 'MEN', 'E6', 2012, null],
        ['Emilien', 'Hubert', 'MEN', 'NC', 2016, 'Hubert'],
        ['Hadrien', 'Maes', 'MEN', 'NC', 2016, 'Maes'],
        ['Isabelle', 'Maes', 'WOMEN', 'NC', 1983, 'Maes'],
        ['Chloé', 'Dewez', 'WOMEN', 'NC', 2015, 'Dewez'],
        ['Luc', 'Dewez', 'MEN', 'NC', 2017, 'Dewez'],
        ['Quentin', 'Gobert', 'MEN', 'NC', 2016, null],
        // Outside the field: entered by the committee on the morning.
        ['Arthur', 'Pauly', 'MEN', 'NC', 2015, null],
        ['Elise', 'Ronveaux', 'WOMEN', 'NC', 2014, null],
    ];

    private const int PAID = 22;

    private const int REGISTERED = 32;

    public function run(): void
    {
        if (Tournament::where('name', self::NAME)->exists()) {
            $this->command?->info('The rehearsal tournament already exists — nothing to do.');

            return;
        }

        $season = Season::current() ?? Season::orderByDesc('id')->first();

        if (! $season instanceof Season) {
            $this->command?->warn('No season: run the SeasonSeeder first.');

            return;
        }

        $players = $this->createField($season);
        $tournament = $this->createTournament();

        foreach ($players->take(self::REGISTERED)->values() as $index => $player) {
            $this->register($tournament, $player, paid: $index < self::PAID);
        }

        app(TournamentService::class)->countRegisteredUsers($tournament);

        $this->command?->info(sprintf('Rehearsal tournament #%d: %d registered, %d paid.', $tournament->id, self::REGISTERED, self::PAID));
    }

    /**
     * @return Collection<int, User>
     */
    private function createField(Season $season): Collection
    {
        $guardians = [];

        return collect(self::FIELD)->map(function (array $row, int $index) use ($season, &$guardians): User {
            [$firstName, $lastName, $gender, $ranking, $birthYear, $family] = $row;
            $isChild = $birthYear >= 2009;
            $managed = $isChild && $family !== null;
            $slug = mb_strtolower(str_replace([' ', 'é', 'è', 'ë', 'ï', 'î', 'ô'], ['-', 'e', 'e', 'e', 'i', 'i', 'o'], $firstName . '.' . $lastName));

            $player = User::factory()->create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'gender' => $gender,
                'ranking' => Ranking::from($ranking),
                'birthdate' => Carbon::create($birthYear, 1 + ($index % 12), 1 + ($index % 27))->toDateString(),
                'email' => $managed ? null : $slug . '.rehearsal@example.test',
            ]);

            if ($managed) {
                $guardians[$family] ??= Guardian::create([
                    'first_name' => 'Parent',
                    'last_name' => $family,
                    'phone' => '0470' . str_pad((string) ($index * 7919 % 1000000), 6, '0', STR_PAD_LEFT),
                    'email' => 'famille.' . mb_strtolower($family) . '.rehearsal@example.test',
                ]);
                $guardians[$family]->users()->attach($player->id);
            }

            Subscription::factory()->create([
                'user_id' => $player->id,
                'season_id' => $season->id,
                'status' => 'confirmed',
                'is_competitive' => $ranking !== 'NC',
            ]);

            return $player;
        });
    }

    private function createTournament(): Tournament
    {
        $day = today()->addDays(12);

        $tournament = Tournament::create([
            'name' => self::NAME,
            'description' => 'Paires composées par le comité, en mélangeant les niveaux et les âges.',
            'location' => 'Centre sportif Jean Demeester, Ottignies',
            'start_date' => $day,
            'start_time' => '13:00:00',
            'end_date' => $day->copy()->setTime(18, 0),
            'registration_deadline' => $day->copy()->subDays(9),
            'status' => TournamentStatusEnum::PUBLISHED,
            'match_type' => 'double',
            'doubles_registration_mode' => 'club',
            'price' => 15,
            'reduced_price' => 10,
            'reduced_price_under_age' => 12,
            'max_users' => 32,
            'nb_pools' => 4,
            'pool_size' => 4,
            'nb_qualifiers_per_pool' => 2,
            'bracket_size' => 8,
            'sets_to_win' => 3,
            'deuce_enabled' => true,
            'has_handicap_points' => false,
            'duration_minutes' => 300,
            'logistics_buffer_minutes' => 3,
            'objective' => TournamentObjectiveEnum::Leisure,
        ]);

        $tournament->rooms()->sync($this->rooms());

        return $tournament;
    }

    /**
     * A registration as the member's own click makes it, without the mails.
     */
    private function register(Tournament $tournament, User $player, bool $paid): void
    {
        $tournament->users()->attach($player->id, ['registration_status' => 'registered']);
        $registration = TournamentRegistration::where('tournament_id', $tournament->id)->where('user_id', $player->id)->firstOrFail();

        $payment = $registration->payment()->create([
            'reference' => (new GeneratePaymentReference)(),
            'amount_due' => $tournament->priceFor($player),
            'amount_paid' => 0,
            'status' => 'pending',
        ]);

        $registration->update([
            'payment_id' => $payment->id,
            'payment_deadline' => $tournament->registration_deadline?->copy()->endOfDay(),
        ]);

        if ($paid) {
            (new AllocateTransactionAction)->credit($payment, (float) $payment->amount_due, 'cash');
        }
    }

    /**
     * The two halls of the club: six tables downstairs, three upstairs.
     *
     * @return list<int>
     */
    private function rooms(): array
    {
        $existing = Room::where('name', 'like', 'Demeester%')->pluck('id')->all();

        if ($existing !== []) {
            return $existing;
        }

        return collect(['Demeester -1' => 6, 'Demeester 0' => 3])
            ->map(function (int $tables, string $name): int {
                $room = Room::factory()->create(['name' => $name]);
                foreach (range(1, $tables) as $number) {
                    Table::factory()->create(['name' => (string) $number, 'room_id' => $room->id]);
                }

                return $room->id;
            })
            ->values()
            ->all();
    }
}
