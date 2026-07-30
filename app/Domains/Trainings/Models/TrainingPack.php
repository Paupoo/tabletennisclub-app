<?php

declare(strict_types=1);

namespace App\Domains\Trainings\Models;

use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\ClubPosts\Models\EventPost;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\Recurrence;
use App\Domains\Shared\Enums\TrainingLevel;
use App\Domains\Shared\Enums\TrainingType;
use App\Domains\Shared\Traits\HasAuditLog;
use App\Domains\Trainings\Services\TrainingBuilder;
use App\Domains\Trainings\Services\TrainingDateGenerator;
use Carbon\Carbon;
use Database\Factories\Domains\Trainings\Models\TrainingPackFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * @property int $id
 * @property string $name
 * @property int $season_id
 * @property float $price
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property TrainingLevel $level
 * @property TrainingType $type
 * @property int $room_id
 * @property int|null $trainer_id
 * @property int|null $day_of_week
 * @property string|null $start_time
 * @property int|null $duration_minutes
 * @property string|null $description
 * @property int|null $max_participants
 * @property bool $is_active
 * @property array<array-key, mixed>|null $days_of_week
 * @property \Illuminate\Support\Carbon|null $pack_start_date
 * @property \Illuminate\Support\Carbon|null $pack_end_date
 * @property array<array-key, mixed>|null $excluded_dates
 * @property bool $allow_discount
 * @property bool $is_open_enrollment
 * @property-read EventPost|null $eventPost
 * @property-read Room $room
 * @property-read Season $season
 * @property-read Collection<int, Subscription> $subscriptions
 * @property-read int|null $subscriptions_count
 * @property-read User|null $trainer
 * @property-read Collection<int, Training> $trainings
 * @property-read int|null $trainings_count
 *
 * @method static \Database\Factories\Domains\Trainings\Models\TrainingPackFactory factory($count = null, $state = [])
 * @method static Builder<static>|TrainingPack newModelQuery()
 * @method static Builder<static>|TrainingPack newQuery()
 * @method static Builder<static>|TrainingPack query()
 * @method static Builder<static>|TrainingPack whereAllowDiscount($value)
 * @method static Builder<static>|TrainingPack whereCreatedAt($value)
 * @method static Builder<static>|TrainingPack whereDayOfWeek($value)
 * @method static Builder<static>|TrainingPack whereDaysOfWeek($value)
 * @method static Builder<static>|TrainingPack whereDescription($value)
 * @method static Builder<static>|TrainingPack whereDurationMinutes($value)
 * @method static Builder<static>|TrainingPack whereExcludedDates($value)
 * @method static Builder<static>|TrainingPack whereId($value)
 * @method static Builder<static>|TrainingPack whereIsActive($value)
 * @method static Builder<static>|TrainingPack whereIsOpenEnrollment($value)
 * @method static Builder<static>|TrainingPack whereLevel($value)
 * @method static Builder<static>|TrainingPack whereMaxParticipants($value)
 * @method static Builder<static>|TrainingPack whereName($value)
 * @method static Builder<static>|TrainingPack wherePackEndDate($value)
 * @method static Builder<static>|TrainingPack wherePackStartDate($value)
 * @method static Builder<static>|TrainingPack wherePrice($value)
 * @method static Builder<static>|TrainingPack whereRoomId($value)
 * @method static Builder<static>|TrainingPack whereSeasonId($value)
 * @method static Builder<static>|TrainingPack whereStartTime($value)
 * @method static Builder<static>|TrainingPack whereTrainerId($value)
 * @method static Builder<static>|TrainingPack whereType($value)
 * @method static Builder<static>|TrainingPack whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class TrainingPack extends Model
{
    use HasAuditLog;

    /** @use HasFactory<TrainingPackFactory> */
    use HasFactory;

    protected $casts = [
        'season_id' => 'integer',
        'price' => 'integer',
        'allow_discount' => 'boolean',
        'level' => TrainingLevel::class,
        'type' => TrainingType::class,
        'trainer_id' => 'integer',
        'room_id' => 'integer',
        'day_of_week' => 'integer',
        'days_of_week' => 'array',
        'pack_start_date' => 'date',
        'pack_end_date' => 'date',
        'excluded_dates' => 'array',
        'duration_minutes' => 'integer',
        'max_participants' => 'integer',
        'is_active' => 'boolean',
        'is_open_enrollment' => 'boolean',
    ];

    protected $fillable = [
        'season_id',
        'name',
        'price',
        'allow_discount',
        'level',
        'type',
        'trainer_id',
        'room_id',
        'day_of_week',
        'days_of_week',
        'start_time',
        'duration_minutes',
        'pack_start_date',
        'pack_end_date',
        'excluded_dates',
        'description',
        'max_participants',
        'is_active',
        'is_open_enrollment',
    ];

    /**
     * ISO weekday number => translated day name.
     *
     * @return array<int, string>
     */
    public static function dayNames(): array
    {
        return [
            1 => __('Monday'),
            2 => __('Tuesday'),
            3 => __('Wednesday'),
            4 => __('Thursday'),
            5 => __('Friday'),
            6 => __('Saturday'),
            7 => __('Sunday'),
        ];
    }

    /**
     * Places engagées dans le pack.
     *
     * Le statut du pivot ne suffit pas : une inscription club annulée gardait
     * sa place et pouvait faire afficher « complet » à un pack qui ne l'était
     * pas, bloquant de vrais membres (issue #29).
     *
     * On filtre sur affiliated() et non active() : un membre en attente de
     * validation qui a réservé une place la conserve, sinon la place serait
     * attribuée deux fois. Seuls les états terminaux libèrent la place.
     */
    public function committedCount(): int
    {
        return $this->subscriptions()
            ->affiliated()
            ->wherePivotIn('status', ['enrolled', 'pending'])
            ->count();
    }

    public function effectiveMaxParticipants(): int
    {
        return $this->max_participants ?? $this->room?->capacity_for_trainings ?? 0;
    }

    public function enrolledCount(): int
    {
        return $this->subscriptions()
            ->affiliated()
            ->wherePivot('status', 'enrolled')
            ->count();
    }

    public function eventPost(): MorphOne
    {
        return $this->morphOne(EventPost::class, 'eventable');
    }

    /**
     * Generate sessions for this pack within the given season (or custom date range).
     * Supports multi-day recurrence and excluded dates.
     * Skips dates where a session for this pack already exists.
     */
    public function generateSessions(Season $season): void
    {
        if ($this->start_time === null || $this->duration_minutes === null) {
            return;
        }

        // Determine which weekdays to generate for
        $daysToGenerate = $this->days_of_week
            ?? ($this->day_of_week !== null ? [$this->day_of_week] : []);

        if (empty($daysToGenerate)) {
            return;
        }

        // Determine date bounds (custom range overrides season)
        $startBound = $this->pack_start_date
            ? $this->pack_start_date->copy()->startOfDay()
            : $season->start_at->copy()->startOfDay();

        $endBound = $this->pack_end_date
            ? $this->pack_end_date->copy()->endOfDay()
            : $season->end_at->copy();

        $excludedDates = collect($this->excluded_dates ?? []);
        $endTime = Carbon::parse($this->start_time)->addMinutes($this->duration_minutes)->format('H:i:s');
        $builder = app(TrainingBuilder::class);
        $generator = app(TrainingDateGenerator::class);

        foreach ($daysToGenerate as $dayOfWeek) {
            $dayOfWeek = (int) $dayOfWeek;
            $firstDate = $startBound->copy();
            $diff = ($dayOfWeek - $firstDate->isoWeekday() + 7) % 7;
            $firstDate->addDays($diff);

            if ($firstDate->gt($endBound)) {
                continue;
            }

            try {
                $dates = $generator->generateDates(
                    $firstDate->toDateString(),
                    $endBound->toDateString(),
                    Recurrence::WEEKLY->name,
                );
            } catch (\Exception) {
                continue;
            }

            foreach ($dates as $date) {
                $dateString = $date->toDateString();

                if ($excludedDates->contains($dateString)) {
                    continue;
                }

                if ($this->trainings()->whereDate('start', $dateString)->exists()) {
                    continue;
                }

                $builder
                    ->setAttributes(['level' => $this->level->value, 'type' => $this->type->value])
                    ->mergeDateAndTime($date, $this->start_time, $endTime)
                    ->setRoom($this->room_id)
                    ->setSeason($season->id)
                    ->setTrainer($this->trainer_id)
                    ->setTrainingPack($this->id)
                    ->buildAndSave();
            }
        }
    }

    public function hasAvailableSpot(): bool
    {
        if ($this->is_open_enrollment) {
            return true;
        }

        $max = $this->effectiveMaxParticipants();

        return $max === 0 || $this->committedCount() < $max;
    }

    public function price(): Attribute
    {
        return Attribute::make(
            get: fn (int $value): float => round($value / 100, 2),
            set: fn (float|int $value): int => (int) round($value * 100),
        );
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Human-readable schedule for display, e.g. "Mardi · 20h30 – 22h00" or
     * "Du lundi au vendredi · 9h00 – 16h00 · du 05/07 au 16/07".
     *
     * Adapts to the pack recurrence: single weekly day, multiple days
     * (contiguous ranges are collapsed), optional time range derived from
     * start_time + duration_minutes, and optional custom date bounds.
     * Returns null when the pack has no day nor time information.
     */
    public function scheduleLabel(): ?string
    {
        $days = $this->days_of_week
            ?? ($this->day_of_week !== null ? [$this->day_of_week] : []);
        $days = array_values(array_unique(array_map(intval(...), $days)));
        sort($days);

        if ($this->start_time === null && $days === []) {
            return null;
        }

        $parts = [];

        if (($daysLabel = $this->daysLabel($days)) !== null) {
            $parts[] = $daysLabel;
        }

        if ($this->start_time !== null) {
            $start = Carbon::parse($this->start_time);
            $time = $start->format('G\hi');

            if ($this->duration_minutes !== null) {
                $time .= ' – ' . $start->addMinutes($this->duration_minutes)->format('G\hi');
            }

            $parts[] = $time;
        }

        if ($this->pack_start_date !== null && $this->pack_end_date !== null) {
            $parts[] = __('from :start to :end', [
                'start' => $this->pack_start_date->format('d/m'),
                'end' => $this->pack_end_date->format('d/m'),
            ]);
        }

        return implode(' · ', $parts);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function subscriptions(): BelongsToMany
    {
        return $this->belongsToMany(Subscription::class);
    }

    /**
     * Users enrolled (not waitlisted) in this pack via their subscription.
     *
     * @return Builder<User>
     */
    public function trainees(): Builder
    {
        return User::query()->whereHas('subscriptions', function (Builder $q): void {
            $q->affiliated()->whereHas('trainingPacks', fn (Builder $q2) => $q2
                ->where('training_packs.id', $this->id)
                ->where('subscription_training_pack.status', 'enrolled')
            );
        });
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function trainings(): HasMany
    {
        return $this->hasMany(Training::class);
    }

    public function waitlistCount(): int
    {
        return $this->subscriptions()
            ->affiliated()
            ->wherePivot('status', 'waiting')
            ->count();
    }

    /**
     * Label for a sorted list of ISO weekday numbers: "Mardi",
     * "Lundi & mercredi", or "Du lundi au vendredi" for contiguous ranges.
     *
     * @param  int[]  $days
     */
    private function daysLabel(array $days): ?string
    {
        if ($days === []) {
            return null;
        }

        $names = self::dayNames();

        if (count($days) === 1) {
            return $names[$days[0]] ?? null;
        }

        $first = $days[0];
        $last = $days[count($days) - 1];

        if (count($days) > 2 && $days === range($first, $last)) {
            return __('From :first to :last', [
                'first' => mb_strtolower($names[$first]),
                'last' => mb_strtolower($names[$last]),
            ]);
        }

        $labels = array_map(fn (int $day): string => $names[$day] ?? (string) $day, $days);

        return implode(' & ', array_map(
            fn (string $label, int $index): string => $index === 0 ? $label : mb_strtolower($label),
            $labels,
            array_keys($labels),
        ));
    }
}
