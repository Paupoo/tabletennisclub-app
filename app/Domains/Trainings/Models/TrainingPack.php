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
use App\Domains\Trainings\Services\TrainingBuilder;
use App\Domains\Trainings\Services\TrainingDateGenerator;
use Carbon\Carbon;
use Database\Factories\Domains\Trainings\Models\TrainingPackFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class TrainingPack extends Model
{
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

    public function committedCount(): int
    {
        return $this->subscriptions()
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
            set: fn (float|int $value): int => (int) $value * 100,
        );
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
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
            $q->whereHas('trainingPacks', fn (Builder $q2) => $q2
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
            ->wherePivot('status', 'waiting')
            ->count();
    }
}
