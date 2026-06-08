<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\ClubPosts\Services\EventPostService;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\ClubEventTypeEnum;
use App\Domains\Shared\Enums\EventPostStatusEnum;
use App\Domains\Shared\Enums\Gender;
use App\Domains\Shared\Enums\Ranking;
use App\Domains\Shared\Enums\TrainingLevel;
use App\Domains\Shared\Enums\TrainingType;
use App\Domains\Trainings\Models\TrainingPack;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TrainingPackSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('password');
        $ourClub = Club::own();
        $season = Season::where('is_active', true)->first();

        if (! $season) {
            $this->command->warn('No active season found — skipping TrainingPackSeeder.');

            return;
        }

        // ── Rooms ─────────────────────────────────────────────────────────────
        $demMoins1 = Room::where('name', 'Demeester -1')->first();
        $dem0 = Room::where('name', 'Demeester 0')->first();
        $blocry = Room::where('name', 'Blocry G3')->first();

        // ── Coaches ───────────────────────────────────────────────────────────
        $aloise = User::firstOrCreate(
            ['email' => 'aloise.lejeune@coach.test'],
            [
                'first_name' => 'Aloïse',
                'last_name' => 'Lejeune',
                'password' => $password,
                'is_active' => false,
                'is_competitor' => false,
                'gender' => Gender::WOMEN->name,
                'ranking' => Ranking::D4->name,
                'phone_number' => '047' . fake()->randomNumber(7, true),
                'birthdate' => fake()->dateTimeBetween('-50 years', '-20 years'),
                'street' => fake()->streetAddress(),
                'city_code' => fake()->postcode(),
                'city_name' => fake()->city(),
            ]
        );
        if ($ourClub && ! $aloise->club_id) {
            $aloise->club()->associate($ourClub)->save();
        }

        $eric = User::firstOrCreate(
            ['email' => 'eric.filee@test.com'],
            [
                'first_name' => 'Éric',
                'last_name' => 'Filée',
                'password' => $password,
                'is_active' => true,
                'is_competitor' => true,
                'is_coach' => true,
                'gender' => Gender::MEN->name,
                'ranking' => Ranking::C6->name,
                'phone_number' => '047' . fake()->randomNumber(7, true),
                'birthdate' => fake()->dateTimeBetween('-50 years', '-20 years'),
                'street' => fake()->streetAddress(),
                'city_code' => fake()->postcode(),
                'city_name' => fake()->city(),
            ]
        );
        if ($ourClub && ! $eric->club_id) {
            $eric->club()->associate($ourClub)->save();
        }

        // Paid competitive subscription for Éric
        if (! Subscription::where('user_id', $eric->id)->where('season_id', $season->id)->exists()) {
            Subscription::create([
                'user_id' => $eric->id,
                'season_id' => $season->id,
                'is_competitive' => true,
                'amount_due' => 125,
                'amount_paid' => 125,
                'status' => 'paid',
            ]);
        }

        $jeanPierre = User::firstOrCreate(
            ['email' => 'jeanpierre.fikani@coach.test'],
            [
                'first_name' => 'Jean-Pierre',
                'last_name' => 'Fikani',
                'password' => $password,
                'is_active' => false,
                'is_competitor' => false,
                'gender' => Gender::MEN->name,
                'ranking' => Ranking::B6->name,
                'phone_number' => '047' . fake()->randomNumber(7, true),
                'birthdate' => fake()->dateTimeBetween('-50 years', '-20 years'),
                'street' => fake()->streetAddress(),
                'city_code' => fake()->postcode(),
                'city_name' => fake()->city(),
            ]
        );
        if ($ourClub && ! $jeanPierre->club_id) {
            $jeanPierre->club()->associate($ourClub)->save();
        }

        $aurelien = User::where('email', 'aurelien.paulus@gmail.com')->first();

        // Random coach for supervised lundi
        $randomCoach = User::whereNot('id', $aurelien?->id)
            ->inRandomOrder()
            ->first();

        // ── Stage d'été date range ────────────────────────────────────────────
        // Two first weeks of July (Mon–Fri × 2), based on season end year
        $julyYear = $season->end_at->year;
        $firstJuly = Carbon::create($julyYear, 7, 1);
        $stageStart = $firstJuly->isMonday() ? $firstJuly : $firstJuly->next('Monday');
        $stageEnd = $stageStart->copy()->addDays(11); // 2nd Friday of the 2-week stage

        // ── Training packs ────────────────────────────────────────────────────
        $packs = [
            [
                'name' => 'Lundi — Entraînement supervisé',
                'day_of_week' => 1,
                'start_time' => '18:00:00',
                'duration_minutes' => 120,
                'room_id' => $blocry?->id,
                'level' => TrainingLevel::OPEN,
                'type' => TrainingType::SUPERVISED,
                'trainer_id' => $randomCoach?->id,
                'max_participants' => 10,
                'price' => 90,
                'allow_discount' => true,
                'is_open_enrollment' => false,
            ],
            [
                'name' => 'Lundi — Entrée libre (Dem. 0)',
                'day_of_week' => 1,
                'start_time' => '20:00:00',
                'duration_minutes' => 150,
                'room_id' => $dem0?->id,
                'level' => TrainingLevel::OPEN,
                'type' => TrainingType::FREE,
                'trainer_id' => null,
                'max_participants' => null,
                'price' => 0,
                'allow_discount' => false,
                'is_open_enrollment' => true,
            ],
            [
                'name' => 'Lundi — Entrée libre (Dem. -1)',
                'day_of_week' => 1,
                'start_time' => '20:30:00',
                'duration_minutes' => 90,
                'room_id' => $demMoins1?->id,
                'level' => TrainingLevel::OPEN,
                'type' => TrainingType::FREE,
                'trainer_id' => null,
                'max_participants' => null,
                'price' => 0,
                'allow_discount' => false,
                'is_open_enrollment' => true,
            ],
            [
                'name' => 'Mardi — Perfectionnement',
                'day_of_week' => 2,
                'start_time' => '20:30:00',
                'duration_minutes' => 90,
                'room_id' => $demMoins1?->id,
                'level' => TrainingLevel::INTERMEDIATE,
                'type' => TrainingType::DIRECTED,
                'trainer_id' => $aloise->id,
                'max_participants' => 10,
                'price' => 90,
                'allow_discount' => true,
                'is_open_enrollment' => false,
            ],
            [
                'name' => 'Mercredi — Initiation jeunes',
                'day_of_week' => 3,
                'start_time' => '15:00:00',
                'duration_minutes' => 90,
                'room_id' => $demMoins1?->id,
                'level' => TrainingLevel::BEGINNERS,
                'type' => TrainingType::DIRECTED,
                'trainer_id' => $eric->id,
                'max_participants' => 8,
                'price' => 90,
                'allow_discount' => true,
                'is_open_enrollment' => false,
            ],
            [
                'name' => 'Mercredi — Perfectionnement jeunes',
                'day_of_week' => 3,
                'start_time' => '16:30:00',
                'duration_minutes' => 90,
                'room_id' => $demMoins1?->id,
                'level' => TrainingLevel::INTERMEDIATE,
                'type' => TrainingType::DIRECTED,
                'trainer_id' => $eric->id,
                'max_participants' => 8,
                'price' => 90,
                'allow_discount' => true,
                'is_open_enrollment' => false,
            ],
            [
                'name' => 'Samedi — Initiation jeunes',
                'day_of_week' => 6,
                'start_time' => '09:00:00',
                'duration_minutes' => 90,
                'room_id' => $demMoins1?->id,
                'level' => TrainingLevel::BEGINNERS,
                'type' => TrainingType::DIRECTED,
                'trainer_id' => $jeanPierre->id,
                'max_participants' => 8,
                'price' => 90,
                'allow_discount' => true,
                'is_open_enrollment' => false,
            ],
            [
                'name' => 'Samedi — Perfectionnement jeunes',
                'day_of_week' => 6,
                'start_time' => '10:30:00',
                'duration_minutes' => 90,
                'room_id' => $demMoins1?->id,
                'level' => TrainingLevel::INTERMEDIATE,
                'type' => TrainingType::DIRECTED,
                'trainer_id' => $jeanPierre->id,
                'max_participants' => 8,
                'price' => 90,
                'allow_discount' => true,
                'is_open_enrollment' => false,
            ],
            [
                'name' => "Stage d'été",
                'days_of_week' => [1, 2, 3, 4, 5],
                'pack_start_date' => $stageStart->toDateString(),
                'pack_end_date' => $stageEnd->toDateString(),
                'start_time' => '09:00:00',
                'duration_minutes' => 420,
                'room_id' => $blocry?->id,
                'level' => TrainingLevel::OPEN,
                'type' => TrainingType::DIRECTED,
                'trainer_id' => $aurelien?->id,
                'max_participants' => null,
                'price' => 350,
                'allow_discount' => false,
                'is_open_enrollment' => false,
            ],
        ];

        foreach ($packs as $data) {
            $pack = TrainingPack::create(array_merge($data, [
                'season_id' => $season->id,
                'is_active' => true,
            ]));

            $pack->generateSessions($season);

            // Publish the Stage d'été as a web event
            if ($data['name'] === "Stage d'été" && $pack->pack_start_date) {
                $stageStartTime = Carbon::parse($pack->start_time ?? '09:00:00');
                $stageEndTime = $stageStartTime->copy()->addMinutes($pack->duration_minutes ?? 420);

                app(EventPostService::class)->upsert(
                    model: $pack,
                    type: ClubEventTypeEnum::TRAINING,
                    title: "Stage d'été " . $julyYear,
                    description: 'Stage intensif de ping-pong sur deux semaines. Ouvert à tous les niveaux, du lundi au vendredi. Encadré par nos entraîneurs diplômés.',
                    location: $blocry ? implode(', ', array_filter([$blocry->street, $blocry->city_name])) : 'Blocry',
                    featured: true,
                    status: EventPostStatusEnum::PUBLISHED,
                    syncData: [
                        'event_date' => $pack->pack_start_date->toDateString(),
                        'start_time' => $stageStartTime->format('H:i:s'),
                        'end_time' => $stageEndTime->format('H:i:s'),
                        'price' => (string) $pack->price,
                        'max_participants' => $pack->max_participants,
                        'icon' => '🎯',
                    ],
                    featuredUntil: $stageEnd->toDateString(),
                );
            }
        }
    }
}
