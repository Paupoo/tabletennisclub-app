<?php

declare(strict_types=1);

namespace App\Models\ClubPosts;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventPost extends Model
{
    use HasFactory;

    // Constantes pour éviter la duplication
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
        'event_date' => 'date',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'featured' => 'boolean',
    ];

    protected $fillable = [
        'title',
        'description',
        'category',
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
        return $this->event_date->format('d/m/Y');
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

    public function getStatusBadgeClasses(): string
    {
        return match ($this->status) {
            'draft' => 'bg-gray-100 text-gray-800',
            'published' => 'bg-green-100 text-green-800',
            'archived' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800'
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
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

    // Scopes pour les requêtes courantes
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('event_date', '>=', now()->startOfDay());
    }
}
