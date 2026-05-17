<?php

declare(strict_types=1);

namespace App\Models\ClubPosts;

use App\Enums\ClubEventTypeEnum;
use App\Enums\EventPostStatusEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property EventPostStatusEnum $status
 * @property ClubEventTypeEnum $type
 */
class EventPost extends Model
{
    use HasFactory;

    public const CATEGORIES = [
        'club-life' => 'Vie du club',
        'tournament' => 'Tournoi',
        'training' => 'Entraînement',
    ];

    public const ICONS = [
        'club-life' => '🎉',
        'tournament' => '🏆',
        'training' => '🎯',
    ];

    public const STATUSES = [
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
        return $query->where('featured', true);
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
