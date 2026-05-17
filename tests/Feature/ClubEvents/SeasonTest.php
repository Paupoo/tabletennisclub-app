<?php

declare(strict_types=1);

use App\Models\ClubEvents\Interclub\Season;
use Illuminate\Support\Facades\Cache;

describe('Season Model', function () {
    // ============================================
    // VALIDATION & BUSINESS RULES
    // ============================================
    
    test('validates start_at must be before end_at', function () {
        $season = Season::factory()->make([
            'start_at' => now()->addYear(),
            'end_at' => now(),
        ]);
        
        expect(fn() => $season->save())
            ->toThrow(\DomainException::class, 'start_at must be before end_at');
    });
    
    test('allows valid date range', function () {
        $season = Season::factory()->create([
            'start_at' => now(),
            'end_at' => now()->addMonths(9),
        ]);
        
        expect($season->exists)->toBeTrue();
    });
    
    // ============================================
    // ACTIVATION LOGIC
    // ============================================
    
    test('activates only one season at a time', function () {
        $season1 = Season::factory()->create(['is_active' => true]);
        $season2 = Season::factory()->create(['is_active' => false]);
        $season3 = Season::factory()->create(['is_active' => false]);
        
        $season2->activate();
        
        expect($season1->fresh()->is_active)->toBeFalse()
            ->and($season2->fresh()->is_active)->toBeTrue()
            ->and($season3->fresh()->is_active)->toBeFalse();
    });
    
    test('deactivates all seasons before activating new one', function () {
        Season::factory()->count(3)->create(['is_active' => true]);
        
        $newSeason = Season::factory()->create(['is_active' => false]);
        $newSeason->activate();
        
        expect(Season::where('is_active', true)->count())->toBe(1)
            ->and($newSeason->fresh()->is_active)->toBeTrue();
    });
    
    test('activate method is transactional', function () {
        $season1 = Season::factory()->create(['is_active' => true]);
        $season2 = Season::factory()->create(['is_active' => false]);
        
        // Force un échec dans la transaction
        Season::saving(function () {
            throw new \Exception('Force rollback');
        });
        
        try {
            $season2->activate();
        } catch (\Exception $e) {
            // Expected
        }
        
        // Vérifie que rien n'a changé
        expect($season1->fresh()->is_active)->toBeTrue()
            ->and($season2->fresh()->is_active)->toBeFalse();
    })->skip('Requires event mocking setup');
    
    // ============================================
    // SCOPE & RETRIEVAL
    // ============================================
    
    test('scope active returns only active season', function () {
        Season::factory()->count(3)->create(['is_active' => false]);
        $activeSeason = Season::factory()->create(['is_active' => true]);
        
        $result = Season::active()->get();
        
        expect($result)->toHaveCount(1)
            ->and($result->first()->id)->toBe($activeSeason->id);
    });
    
    test('current method returns cached active season', function () {
        Cache::flush();
        
        $activeSeason = Season::factory()->create(['is_active' => true]);
        Season::factory()->count(2)->create(['is_active' => false]);
        
        $current = Season::current();
        
        expect($current->id)->toBe($activeSeason->id)
            ->and(Cache::has('season.current'))->toBeTrue();
    });
    
    test('current method returns null when no active season', function () {
        Cache::flush();
        Season::factory()->count(3)->create(['is_active' => false]);
        
        expect(Season::current())->toBeNull();
    });
    
    test('current method uses cache', function () {
        Cache::flush();
        
        $season = Season::factory()->create(['is_active' => true]);
        
        // Premier appel
        Season::current();
        
        // Change en DB sans clear le cache
        $season->update(['name' => 'Modified']);
        
        // Doit retourner la version cachée
        $cached = Season::current();
        
        expect($cached->name)->not->toBe('Modified');
    });
    
    // ============================================
    // STATUS HELPERS
    // ============================================
    
    test('isCurrent returns true only for active season', function () {
        $active = Season::factory()->create(['is_active' => true]);
        $inactive = Season::factory()->create(['is_active' => false]);
        
        expect($active->isCurrent())->toBeTrue()
            ->and($inactive->isCurrent())->toBeFalse();
    });
    
    test('isPast returns true for ended inactive seasons', function () {
        $past = Season::factory()->create([
            'is_active' => false,
            'start_at' => now()->subMonths(6),
            'end_at' => now()->subMonth(),
        ]);
        
        $current = Season::factory()->create([
            'is_active' => true,
            'start_at' => now()->addMonth(5),
            'end_at' => now()->addMonth(10),
        ]);
        
        expect($past->isPast())->toBeTrue()
            ->and($current->isPast())->toBeFalse();
    });
    
    test('isFuture returns true for upcoming inactive seasons', function () {
        $future = Season::factory()->create([
            'is_active' => false,
            'start_at' => now()->addYear(),
            'end_at' => now()->addYears(2),
        ]);

        $current = Season::factory()->create([
            'is_active' => true,
            'start_at' => now()->subMonths(6),
            'end_at' => now()->addMonths(6),
        ]);

        expect($future->isFuture())->toBeTrue()
            ->and($current->isFuture())->toBeFalse();
    });
    
    // ============================================
    // RELATIONSHIPS
    // ============================================
    
    test('has interclubs relationship', function () {
        $season = Season::factory()->create();
        
        expect($season->interclubs())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    });
    
    test('has leagues relationship', function () {
        $season = Season::factory()->create();
        
        expect($season->leagues())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    });
    
    test('has subscriptions relationship', function () {
        $season = Season::factory()->create();
        
        expect($season->subscriptions())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    });
    
    test('has teams relationship', function () {
        $season = Season::factory()->create();
        
        expect($season->teams())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    });
    
    test('has training packs relationship', function () {
        $season = Season::factory()->create();
        
        expect($season->trainingPacks())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    });
    
    test('has trainings relationship', function () {
        $season = Season::factory()->create();
        
        expect($season->trainings())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    });
    
    test('has users many-to-many relationship', function () {
        $season = Season::factory()->create();
        
        expect($season->users())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsToMany::class);
    });
    
    // ============================================
    // CASTS & ATTRIBUTES
    // ============================================
    
    test('casts dates correctly', function () {
        $season = Season::factory()->create([
            'start_at' => '2024-09-01',
            'end_at' => '2025-06-30',
        ]);
        
        expect($season->start_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
            ->and($season->end_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    });
    
    test('casts is_active as boolean', function () {
        $season = Season::factory()->create(['is_active' => 1]);
        
        expect($season->is_active)->toBeTrue()
            ->and($season->is_active)->toBeBool();
    });

    test('factory creates valid season', function () {
        $season = Season::factory()->create();
        
        expect($season->exists)->toBeTrue()
            ->and($season->name)->toBeString()
            ->and($season->start_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
            ->and($season->end_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
            ->and($season->is_active)->toBeFalse();
    });
    
    test('factory generates unique years', function () {
        $seasons = Season::factory()->count(3)->create();
        
        $names = $seasons->pluck('name')->unique();
        
        expect($names)->toHaveCount(3);
    });
    
    test('factory respects september to june pattern', function () {
        $season = Season::factory()->create();
        
        expect($season->start_at->month)->toBe(9) // Septembre
            ->and($season->end_at->month)->toBe(6); // Juin
    });

})->group('models', 'season');