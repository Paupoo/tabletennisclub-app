<?php
// app/Models/Event.php

namespace App\Models;

use App\Enums\EventStatusEnum;
use App\Enums\EventTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Event extends Model
{
    use HasFactory;

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

    protected $casts = [
        'type' => EventTypeEnum::class,
        'status' => EventStatusEnum::class,
        'event_date' => 'date',
        'featured' => 'boolean',
    ];

    /**
     * Relation polymorphique vers Training, Interclub ou Tournament
     */
    public function eventable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope pour filtrer par statut
     */
    public function scopePublished($query)
    {
        return $query->where('status', EventStatusEnum::PUBLISHED);
    }

    /**
     * Scope pour filtrer par type
     */
    public function scopeOfType($query, EventTypeEnum $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope pour les événements à venir
     */
    public function scopeUpcoming($query)
    {
        return $query->where('event_date', '>=', today())
            ->orderBy('event_date')
            ->orderBy('start_time');
    }

    /**
     * Scope pour les événements mis en avant
     */
    public function scopeFeatured($query)
    {
        return $query->where('featured', true);
    }

    /**
     * Vérifie si l'événement est un Training
     */
    public function isTraining(): bool
    {
        return $this->type === EventTypeEnum::TRAINING;
    }

    /**
     * Vérifie si l'événement est un Interclub
     */
    public function isInterclub(): bool
    {
        return $this->type === EventTypeEnum::INTERCLUB;
    }

    /**
     * Vérifie si l'événement est un Tournament
     */
    public function isTournament(): bool
    {
        return $this->type === EventTypeEnum::TOURNAMENT;
    }

    /**
     * Retourne l'URL de la vue publique
     */
    public function getPublicUrlAttribute(): string
    {
        return route('events.show', $this);
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
}
