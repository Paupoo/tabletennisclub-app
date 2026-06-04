<?php

declare(strict_types=1);

use App\Domains\ClubPosts\Models\EventPost;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Shared\Enums\ClubEventTypeEnum;
use App\Domains\Shared\Enums\EventPostStatusEnum;

// ── Helper ────────────────────────────────────────────────────────────────────

function makeFeaturedEventPost(array $overrides = []): EventPost
{
    $tournament = Tournament::factory()->create();

    return EventPost::create(array_merge([
        'eventable_type' => Tournament::class,
        'eventable_id' => $tournament->id,
        'type' => ClubEventTypeEnum::TOURNAMENT,
        'title' => 'Test Event',
        'description' => '',
        'location' => '',
        'status' => EventPostStatusEnum::PUBLISHED->value,
        'event_date' => today()->toDateString(),
        'start_time' => '09:00:00',
        'icon' => '🏆',
        'featured' => true,
        'featured_until' => null,
    ], $overrides));
}

// ── scopeFeatured — featured_until null ───────────────────────────────────────

describe('scopeFeatured — sans date de fin', function (): void {
    it('inclut les événements featured sans date de fin', function (): void {
        $ep = makeFeaturedEventPost(['featured' => true, 'featured_until' => null]);

        expect(EventPost::featured()->pluck('id'))->toContain($ep->id);
    });

    it('exclut les événements non-featured', function (): void {
        $ep = makeFeaturedEventPost(['featured' => false, 'featured_until' => null]);

        expect(EventPost::featured()->pluck('id'))->not->toContain($ep->id);
    });
});

// ── scopeFeatured — date de fin future ────────────────────────────────────────

describe('scopeFeatured — date de fin dans le futur', function (): void {
    it('inclut un événement featured until demain', function (): void {
        $ep = makeFeaturedEventPost(['featured' => true, 'featured_until' => today()->addDay()->toDateString()]);

        expect(EventPost::featured()->pluck('id'))->toContain($ep->id);
    });

    it("inclut un événement featured until aujourd'hui", function (): void {
        $ep = makeFeaturedEventPost(['featured' => true, 'featured_until' => today()->toDateString()]);

        expect(EventPost::featured()->pluck('id'))->toContain($ep->id);
    });
});

// ── scopeFeatured — date de fin expirée ──────────────────────────────────────

describe('scopeFeatured — date de fin expirée', function (): void {
    it('exclut un événement dont featured_until était hier', function (): void {
        $ep = makeFeaturedEventPost(['featured' => true, 'featured_until' => today()->subDay()->toDateString()]);

        expect(EventPost::featured()->pluck('id'))->not->toContain($ep->id);
    });

    it('exclut un événement dont featured_until était il y a une semaine', function (): void {
        $ep = makeFeaturedEventPost(['featured' => true, 'featured_until' => today()->subWeek()->toDateString()]);

        expect(EventPost::featured()->pluck('id'))->not->toContain($ep->id);
    });
});

// ── canBeDeleted ──────────────────────────────────────────────────────────────

describe('canBeDeleted', function (): void {
    it('retourne true pour un brouillon', function (): void {
        $ep = makeFeaturedEventPost(['status' => EventPostStatusEnum::DRAFT->value]);

        expect($ep->canBeDeleted())->toBeTrue();
    });

    it('retourne true pour un archivé', function (): void {
        $ep = makeFeaturedEventPost(['status' => EventPostStatusEnum::ARCHIVED->value]);

        expect($ep->canBeDeleted())->toBeTrue();
    });

    it('retourne false pour un publié', function (): void {
        $ep = makeFeaturedEventPost(['status' => EventPostStatusEnum::PUBLISHED->value]);

        expect($ep->canBeDeleted())->toBeFalse();
    });
});
