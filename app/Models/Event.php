<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventStatusEnum;
use App\Enums\EventTypeEnum;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;

    // Constantes pour éviter la duplication
    public const CATEGORIES = [
        'club-life' => 'Vie du club',
        'tournament' => 'Tournoi',
        'training' => 'Entraînement',
    ];

    public const ICONS = [
        // EventTypeEnum::COMMUNITY_EVENT => '🎉',
        EventTypeEnum::INTERCLUB->value => '🏓',
        EventTypeEnum::TOURNAMENT->value => '🏆',
        EventTypeEnum::TRAINING->value => '🎯',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'featured' => 'boolean',
        'status' => EventStatusEnum::class,
        'address' => 'string',
    ];

    protected $fillable = [
        'title',
        'description',
        'category',
        'status',
        'start_at',
        'start_at',
        'end_time',
        'address',
        'price',
        'icon',
        'max_participants',
        'notes',
        'featured',
    ];

    // Méthodes utilitaires
    public function canBeDeleted(): bool
    {
        // Un événement peut être supprimé s'il est en brouillon ou archivé
        return in_array($this->status, ['draft', 'archived']);
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
        return $this->start_at->format('d/m/Y');
    }

    public function getFormattedTimeAttribute(): string
    {
        $start = $this->start_at->format('H:i');
        $end = $this->end_time ? $this->end_time->format('H:i') : null;

        return $end ? "{$start} - {$end}" : $start;
    }

    public function getIsPastAttribute(): bool
    {
        return $this->start_at < now()->startOfDay();
    }

    public function getIsUpcomingAttribute(): bool
    {
        return $this->start_at >= now()->startOfDay();
    }

    public function getStatusBadgeClasses(): string
    {
        return match ($this->status) {
            EventStatusEnum::DRAFT => 'bg-gray-100 text-gray-800',
            EventStatusEnum::PUBLISHED => 'bg-green-100 text-green-800',
            EventStatusEnum::ARCHIVED => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800'
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->status->getLabel();
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
        return $query->where('start_at', '<', now()->startOfDay());
    }

    // Scopes pour les requêtes courantes
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('start_at', '>=', now()->startOfDay());
    }
}
