<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Tournament\Models;

use App\Models\ClubAdmin\Users\User;
use Database\Factories\Domains\Competitions\Tournament\Models\TournamentPairFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentPair extends Model
{
    /** @use HasFactory<TournamentPairFactory> */
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'player1_id',
        'player2_id',
        'registered_by',
    ];

    public function averageRanking(): string
    {
        $rankings = collect([$this->player1?->ranking, $this->player2?->ranking])
            ->filter()
            ->map(fn ($r) => (int) $r);

        if ($rankings->isEmpty()) {
            return 'NC';
        }

        return (string) (int) $rankings->average();
    }

    public function displayName(): string
    {
        $p1 = $this->player1?->last_name ?? '?';
        $p2 = $this->player2?->last_name ?? '?';

        return "{$p1}/{$p2}";
    }

    public function player1(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player1_id');
    }

    public function player2(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player2_id');
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }
}
