<?php

declare(strict_types=1);

namespace App\Domains\ClubPosts\Models;

use App\Domains\Shared\Enums\ClubEventTypeEnum;
use App\Domains\Shared\Enums\EventPostStatusEnum;
use App\Domains\Shared\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property EventPostStatusEnum $status
 * @property ClubEventTypeEnum $type
 * @property int $id
 * @property string|null $eventable_type
 * @property int|null $eventable_id
 * @property string $title
 * @property string $description
 * @property Carbon $event_date
 * @property Carbon $start_time
 * @property Carbon|null $end_time
 * @property string $location
 * @property string|null $price
 * @property string $icon
 * @property int|null $max_participants
 * @property string|null $notes
 * @property bool $featured
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $featured_until
 * @property-read Model|\Eloquent $eventable
 * @property-read string $category_label
 * @property-read string $formatted_date
 * @property-read string $formatted_date_time
 * @property-read string $formatted_time
 * @property-read bool $is_past
 * @property-read bool $is_upcoming
 * @property-read string $public_url
 * @property-read string $status_label
 *
 * @method static Builder<static>|EventPost byCategory(string $category)
 * @method static \Database\Factories\Domains\ClubPosts\Models\EventPostFactory factory($count = null, $state = [])
 * @method static Builder<static>|EventPost featured()
 * @method static Builder<static>|EventPost newModelQuery()
 * @method static Builder<static>|EventPost newQuery()
 * @method static Builder<static>|EventPost past()
 * @method static Builder<static>|EventPost published()
 * @method static Builder<static>|EventPost query()
 * @method static Builder<static>|EventPost upcoming()
 * @method static Builder<static>|EventPost whereCreatedAt($value)
 * @method static Builder<static>|EventPost whereDescription($value)
 * @method static Builder<static>|EventPost whereEndTime($value)
 * @method static Builder<static>|EventPost whereEventDate($value)
 * @method static Builder<static>|EventPost whereEventableId($value)
 * @method static Builder<static>|EventPost whereEventableType($value)
 * @method static Builder<static>|EventPost whereFeatured($value)
 * @method static Builder<static>|EventPost whereFeaturedUntil($value)
 * @method static Builder<static>|EventPost whereIcon($value)
 * @method static Builder<static>|EventPost whereId($value)
 * @method static Builder<static>|EventPost whereLocation($value)
 * @method static Builder<static>|EventPost whereMaxParticipants($value)
 * @method static Builder<static>|EventPost whereNotes($value)
 * @method static Builder<static>|EventPost wherePrice($value)
 * @method static Builder<static>|EventPost whereStartTime($value)
 * @method static Builder<static>|EventPost whereStatus($value)
 * @method static Builder<static>|EventPost whereTitle($value)
 * @method static Builder<static>|EventPost whereType($value)
 * @method static Builder<static>|EventPost whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class EventPost extends Model
{
    use HasAuditLog;
    use HasFactory;

    public const array CATEGORIES = [
        'club-life' => 'Vie du club',
        'tournament' => 'Tournoi',
        'training' => 'Entraînement',
    ];

    public const array ICONS = [
        'club-life' => '🎉',
        'tournament' => '🏆',
        'training' => '🎯',
    ];

    public const array STATUSES = [
        'draft' => 'Brouillon',
        'published' => 'Publié',
        'archived' => 'Archivé',
    ];

    protected $casts = [
        'type' => ClubEventTypeEnum::class,
        'status' => EventPostStatusEnum::class,
        'event_date' => 'date',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'featured' => 'boolean',
        'featured_until' => 'date',
    ];

    protected $fillable = [
        'eventable_type',
        'eventable_id',
        'type',
        'title',
        'description',
        'status',
        'event_date',
        'start_time',
        'end_time',
        'location',
        'price',
        'icon',
        'max_participants',
        'notes',
        'featured',
        'featured_until',
    ];

    // Méthodes utilitaires
    public function canBeDeleted(): bool
    {
        return in_array($this->status, [EventPostStatusEnum::DRAFT, EventPostStatusEnum::ARCHIVED]);
    }

    /**
     * Relation polymorphique vers Training, Interclub ou Tournament
     */
    public function eventable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getCategoryBadgeClasses(): string
    {
        return match ($this->category) {
            'club-life' => 'bg-blue-100 text-blue-800',
            'tournament' => 'bg-orange-100 text-orange-800',
            'training' => 'bg-purple-100 text-purple-800',
            default => 'bg-gray-100 text-gray-800'
        };
    }

    // Accesseurs pour améliorer l'affichage
    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    public function getFormattedDateAttribute(): string
    {
        return $this->event_date->format('d/m/Y');
    }

    /**
     * Formatte la date et l'heure
     */
    public function getFormattedDateTimeAttribute(): string
    {
        $date = $this->event_date->isoFormat('dddd D MMMM YYYY');
        $time = $this->start_time;

        if ($this->end_time) {
            $time .= ' - ' . $this->end_time;
        }

        return $date . ' à ' . $time;
    }

    public function getFormattedTimeAttribute(): string
    {
        $start = $this->start_time->format('H:i');
        $end = $this->end_time ? $this->end_time->format('H:i') : null;

        return $end ? "{$start} - {$end}" : $start;
    }

    public function getIsPastAttribute(): bool
    {
        return $this->event_date < now()->startOfDay();
    }

    public function getIsUpcomingAttribute(): bool
    {
        return $this->event_date >= now()->startOfDay();
    }

    /**
     * Retourne l'URL de la vue publique
     */
    public function getPublicUrlAttribute(): string
    {
        return route('clubPosts.eventPosts.show', $this);
    }

    public function getStatusBadgeClasses(): string
    {
        return match ($this->status) {
            EventPostStatusEnum::PUBLISHED => 'bg-green-100 text-green-800',
            EventPostStatusEnum::ARCHIVED => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->status->getLabel();
    }

    /**
     * Vérifie si l'événement est un Interclub
     */
    public function isInterclub(): bool
    {
        return $this->type === ClubEventTypeEnum::INTERCLUB;
    }

    /**
     * Vérifie si l'événement est passé
     */
    public function isPast(): bool
    {
        return $this->event_date->isPast();
    }

    /**
     * Vérifie si l'événement est aujourd'hui
     */
    public function isToday(): bool
    {
        return $this->event_date->isToday();
    }

    /**
     * Vérifie si l'événement est un Tournament
     */
    public function isTournament(): bool
    {
        return $this->type === ClubEventTypeEnum::TOURNAMENT;
    }

    /**
     * Vérifie si l'événement est un Training
     */
    public function isTraining(): bool
    {
        return $this->type === ClubEventTypeEnum::TRAINING;
    }

    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('featured', true)
            ->where(fn (Builder $q) => $q
                ->whereNull('featured_until')
                ->orWhereDate('featured_until', '>=', today())
            );
    }

    public function scopePast(Builder $query): Builder
    {
        return $query->where('event_date', '<', now()->startOfDay());
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', EventPostStatusEnum::PUBLISHED);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('event_date', '>=', now()->startOfDay());
    }
}
