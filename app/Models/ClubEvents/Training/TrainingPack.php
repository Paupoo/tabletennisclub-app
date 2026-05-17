<?php

declare(strict_types=1);

namespace App\Models\ClubEvents\Training;

use App\Enums\Recurrence;
use App\Enums\TrainingLevel;
use App\Enums\TrainingType;
use App\Models\ClubAdmin\Club\Room;
use App\Models\ClubAdmin\Subscription\Subscription;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Interclub\Season;
use App\Services\TrainingBuilder;
use App\Services\TrainingDateGenerator;
use Carbon\Carbon;
use Database\Factories\TrainingPackFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingPack extends Model
{
    /** @use HasFactory<TrainingPackFactory> */
    use HasFactory;

    protected $casts = [
        'season_id' => 'integer',
        'price' => 'integer',
        'level' => TrainingLevel::class,
        'type' => TrainingType::class,
        'trainer_id' => 'integer',
        'room_id' => 'integer',
        'day_of_week' => 'integer',
        'duration_minutes' => 'integer',
        'max_participants' => 'integer',
        'is_active' => 'boolean',
    ];

    protected $fillable = [
        'season_id',
        'name',
        'price',
        'level',
        'type',
        'trainer_id',
        'room_id',
        'day_of_week',
        'start_time',
        'duration_minutes',
        'description',
        'max_participants',
        'is_active',
    ];

    public function effectiveMaxParticipants(): int
    {
        return $this->max_participants ?? $this->room?->capacity_for_trainings ?? 0;
    }

    public function enrolledCount(): int
    {
        return $this->trainees()->count();
    }

    /**
     * Generate all weekly sessions for this pack within the given season.
     * Skips dates where a session for this pack already exists.
     */
    public function generateSessions(Season $season): void
    {
        if ($this->day_of_week === null || $this->start_time === null || $this->duration_minutes === null) {
            return;
        }

        // Find first occurrence of day_of_week on or after the season start
        $firstDate = $season->start_at->copy()->startOfDay();
        $diff = ($this->day_of_week - $firstDate->isoWeekday() + 7) % 7;
        $firstDate->addDays($diff);

        if ($firstDate->gt($season->end_at)) {
            return;
        }

        $endTime = Carbon::parse($this->start_time)->addMinutes($this->duration_minutes)->format('H:i:s');

        $dates = app(TrainingDateGenerator::class)->generateDates(
            $firstDate->toDateString(),
            $season->end_at->toDateString(),
            Recurrence::WEEKLY->name,
        );

        $builder = app(TrainingBuilder::class);

        foreach ($dates as $date) {
            // Skip if a session already exists for this pack on this date
            $dateString = $date->toDateString();
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
     * Users enrolled in this pack via their subscription.
     *
     * @return Builder<User>
     */
    public function trainees(): Builder
    {
        return User::query()->whereHas('subscriptions', function (Builder $q): void {
            $q->whereHas('trainingPacks', fn (Builder $q2) => $q2->where('training_packs.id', $this->id));
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
}
