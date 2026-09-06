<?php

declare(strict_types=1);

namespace App\Domains\Trainings\Models;

use App\Domains\Shared\Traits\HasAuditLog;
use Database\Factories\Domains\Trainings\Models\TrainingLevelFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Un niveau d'entraînement, éditable par la délégation.
 *
 * C'était un enum PHP doublé de deux colonnes SQL `enum` — l'une sur les noms
 * des cases, l'autre sur leurs valeurs — que seule une migration pouvait faire
 * évoluer. La couleur de pastille vivait dans un `match` de vue, si bien
 * qu'ajouter un niveau demandait de toucher au code à trois endroits.
 *
 * @property int $id
 * @property string $label
 * @property string $color classe daisyUI de la pastille (success, warning, error, info, primary)
 * @property int $position
 * @property bool $is_active
 * @property string|null $legacy_name conservé pour la reprise et le rollback
 * @property string|null $legacy_value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Eloquent
 */
class TrainingLevel extends Model
{
    use HasAuditLog;

    /** @use HasFactory<TrainingLevelFactory> */
    use HasFactory;

    protected $casts = [
        'position' => 'integer',
        'is_active' => 'boolean',
    ];

    protected $fillable = [
        'label',
        'color',
        'position',
        'is_active',
    ];

    /**
     * Un niveau porté par un pack ou une séance ne se supprime pas.
     *
     * Le désactiver le retire des listes déroulantes sans toucher à ce qui
     * existe déjà : les packs des saisons passées gardent leur libellé.
     */
    public function isInUse(): bool
    {
        return $this->packs()->exists() || $this->sessions()->exists();
    }

    public function packs(): HasMany
    {
        return $this->hasMany(TrainingPack::class);
    }

    /** @param  Builder<static>  $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('position');
    }

    /** @param  Builder<static>  $query */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(Training::class);
    }
}
