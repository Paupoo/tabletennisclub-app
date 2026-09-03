<?php

declare(strict_types=1);

namespace Database\Factories\Domains\Trainings\Models;

use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\TrainingType;
use App\Domains\Trainings\Models\Training;
use App\Domains\Trainings\Models\TrainingLevel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Training>
 */
class TrainingFactory extends Factory
{
    protected $model = Training::class;

    /** Séance annulée, salle laissée ouverte en libre. */
    public function cancelledFree(): self
    {
        return $this->state(fn (): array => [
            'status' => 'cancelled_free',
            'cancelled_at' => CarbonImmutable::now(),
        ]);
    }

    /** Séance dont le pointage a déjà été validé. */
    public function counted(?User $by = null): self
    {
        return $this->state(fn (): array => [
            'attendance_taken_at' => CarbonImmutable::now(),
            'attendance_taken_by' => $by?->id ?? User::factory(),
        ]);
    }

    /**
     * Une séance à venir, non annulée, non pointée.
     *
     * Le niveau est celui du pack quand la séance en a un ; les tests qui
     * ciblent un libellé précis le passent explicitement.
     */
    public function definition(): array
    {
        $start = CarbonImmutable::tomorrow()->setTime(18, 0);

        return [
            'training_level_id' => TrainingLevel::ordered()->value('id') ?? TrainingLevel::factory(),
            'type' => $this->faker->randomElement(TrainingType::cases())->value,
            'start' => $start,
            'end' => $start->addMinutes(90),
            'room_id' => Room::factory(),
            'trainer_id' => User::factory(),
            // Réutilise la saison en place plutôt que d'en créer une par séance :
            // SeasonFactory refuse les chevauchements, et deux séances suffisent
            // à faire lever l'exception. Déterministe à dessein — surtout pas
            // `inRandomOrder()`, qui rendrait les tests flaky (cf. SubscriptionFactory).
            'season_id' => Season::query()->orderBy('id')->value('id') ?? Season::factory(),
            'status' => 'scheduled',
        ];
    }

    /** Séance déjà passée : c'est celle qu'un coach doit pouvoir pointer. */
    public function past(int $daysAgo = 7): self
    {
        return $this->state(function () use ($daysAgo): array {
            $start = CarbonImmutable::today()->subDays($daysAgo)->setTime(18, 0);

            return ['start' => $start, 'end' => $start->addMinutes(90)];
        });
    }
}
