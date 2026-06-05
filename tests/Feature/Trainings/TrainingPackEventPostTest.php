<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\ClubPosts\Models\EventPost;
use App\Domains\Shared\Enums\ClubEventTypeEnum;
use App\Domains\Shared\Enums\EventPostStatusEnum;
use App\Domains\Trainings\Models\TrainingPack;
use Livewire\Livewire;

// ── Helpers ───────────────────────────────────────────────────────────────────

function eventPostPack(array $overrides = []): TrainingPack
{
    return TrainingPack::factory()->create(array_merge([
        'name' => "Stage d'été 2026",
        'pack_start_date' => '2026-07-06',
        'pack_end_date' => '2026-07-17',
        'start_time' => '09:00:00',
        'duration_minutes' => 420,
        'price' => 350,
    ], $overrides));
}

function mountTrainings(User $admin)
{
    return Livewire::actingAs($admin)
        ->test('pages::club-events.trainings.index');
}

// ── openEventPost — chargement du formulaire ──────────────────────────────────

describe('openEventPost', function (): void {
    it('opens the modal and pre-fills title from pack name', function (): void {
        $admin = User::factory()->create();
        $pack = eventPostPack(['name' => 'Stage Olympique']);

        mountTrainings($admin)
            ->call('openEventPost', $pack->id)
            ->assertSet('showEventPostModal', true)
            ->assertSet('eventTitle', 'Stage Olympique')
            ->assertSet('eventPostId', null);
    });

    it('pre-fills fields from an existing EventPost', function (): void {
        $admin = User::factory()->create();
        $pack = eventPostPack();

        EventPost::create([
            'eventable_type' => TrainingPack::class,
            'eventable_id' => $pack->id,
            'type' => ClubEventTypeEnum::TRAINING,
            'title' => 'Stage déjà publié',
            'description' => 'Description existante',
            'location' => 'Salle Blocry',
            'status' => EventPostStatusEnum::PUBLISHED->value,
            'event_date' => '2026-07-06',
            'start_time' => '09:00:00',
            'icon' => '🎯',
        ]);

        mountTrainings($admin)
            ->call('openEventPost', $pack->id)
            ->assertSet('eventTitle', 'Stage déjà publié')
            ->assertSet('eventDescription', 'Description existante')
            ->assertSet('eventLocation', 'Salle Blocry')
            ->assertSet('eventStatus', 'PUBLISHED');
    });

    it('sets eventPostPackHasDate to false when pack has no start date', function (): void {
        $admin = User::factory()->create();
        $pack = eventPostPack(['pack_start_date' => null]);

        mountTrainings($admin)
            ->call('openEventPost', $pack->id)
            ->assertSet('eventPostPackHasDate', false);
    });

    it('sets eventPostPackHasDate to true when pack has a start date', function (): void {
        $admin = User::factory()->create();
        $pack = eventPostPack(['pack_start_date' => '2026-07-06']);

        mountTrainings($admin)
            ->call('openEventPost', $pack->id)
            ->assertSet('eventPostPackHasDate', true);
    });
});

// ── saveEventPost — création ───────────────────────────────────────────────────

describe('saveEventPost — create', function (): void {
    it('creates an EventPost in draft status for a training pack', function (): void {
        $admin = User::factory()->create();
        $pack = eventPostPack();

        mountTrainings($admin)
            ->call('openEventPost', $pack->id)
            ->set('eventTitle', "Stage d'été 2026")
            ->set('eventDescription', 'Deux semaines de ping-pong intensif.')
            ->call('saveEventPost', 'draft');

        $ep = EventPost::where('eventable_id', $pack->id)
            ->where('eventable_type', TrainingPack::class)
            ->first();

        expect($ep)->not->toBeNull()
            ->and($ep->title)->toBe("Stage d'été 2026")
            ->and($ep->status)->toBe(EventPostStatusEnum::DRAFT)
            ->and($ep->type)->toBe(ClubEventTypeEnum::TRAINING)
            ->and($ep->icon)->toBe('🎯');
    });

    it('creates an EventPost in published status', function (): void {
        $admin = User::factory()->create();
        $pack = eventPostPack();

        mountTrainings($admin)
            ->call('openEventPost', $pack->id)
            ->set('eventTitle', "Stage d'été 2026")
            ->set('eventDescription', 'Deux semaines de ping-pong intensif pour tous.')
            ->call('saveEventPost', 'published');

        $ep = EventPost::where('eventable_id', $pack->id)
            ->where('eventable_type', TrainingPack::class)
            ->first();

        expect($ep->status)->toBe(EventPostStatusEnum::PUBLISHED);
    });

    it('syncs start_time and computes end_time from duration_minutes', function (): void {
        $admin = User::factory()->create();
        $pack = eventPostPack(['start_time' => '09:00:00', 'duration_minutes' => 420]);

        mountTrainings($admin)
            ->call('openEventPost', $pack->id)
            ->set('eventTitle', 'Stage matin')
            ->call('saveEventPost', 'draft');

        $ep = EventPost::where('eventable_id', $pack->id)
            ->where('eventable_type', TrainingPack::class)
            ->first();

        expect($ep->start_time->format('H:i'))->toBe('09:00')
            ->and($ep->end_time->format('H:i'))->toBe('16:00');
    });

    it('uses pack_start_date as event_date', function (): void {
        $admin = User::factory()->create();
        $pack = eventPostPack(['pack_start_date' => '2026-07-06']);

        mountTrainings($admin)
            ->call('openEventPost', $pack->id)
            ->set('eventTitle', 'Stage juillet')
            ->call('saveEventPost', 'draft');

        $ep = EventPost::where('eventable_id', $pack->id)
            ->where('eventable_type', TrainingPack::class)
            ->first();

        expect($ep->event_date->toDateString())->toBe('2026-07-06');
    });

    it('sets the polymorphic relation to the training pack', function (): void {
        $admin = User::factory()->create();
        $pack = eventPostPack();

        mountTrainings($admin)
            ->call('openEventPost', $pack->id)
            ->set('eventTitle', 'Stage test')
            ->call('saveEventPost', 'draft');

        $ep = EventPost::where('eventable_id', $pack->id)->first();

        expect($ep->eventable_type)->toBe(TrainingPack::class)
            ->and($ep->eventable_id)->toEqual($pack->id);
    });

    it('fails validation when title is missing', function (): void {
        $admin = User::factory()->create();
        $pack = eventPostPack();

        mountTrainings($admin)
            ->call('openEventPost', $pack->id)
            ->set('eventTitle', '')
            ->call('saveEventPost', 'draft')
            ->assertHasErrors(['eventTitle']);

        expect(EventPost::where('eventable_id', $pack->id)->exists())->toBeFalse();
    });

    it('closes the modal after a successful save', function (): void {
        $admin = User::factory()->create();
        $pack = eventPostPack();

        mountTrainings($admin)
            ->call('openEventPost', $pack->id)
            ->set('eventTitle', 'Stage été')
            ->call('saveEventPost', 'draft')
            ->assertSet('showEventPostModal', false);
    });
});

// ── saveEventPost — mise à jour ───────────────────────────────────────────────

describe('saveEventPost — update', function (): void {
    it('updates an existing EventPost instead of creating a new one', function (): void {
        $admin = User::factory()->create();
        $pack = eventPostPack();

        $component = mountTrainings($admin)
            ->call('openEventPost', $pack->id)
            ->set('eventTitle', 'Titre initial')
            ->call('saveEventPost', 'draft');

        expect(EventPost::count())->toBe(1);

        $component
            ->call('openEventPost', $pack->id)
            ->set('eventTitle', 'Titre mis à jour')
            ->set('eventDescription', 'Description suffisamment longue pour être publiée.')
            ->call('saveEventPost', 'published');

        expect(EventPost::count())->toBe(1)
            ->and(EventPost::first()->title)->toBe('Titre mis à jour')
            ->and(EventPost::first()->status)->toBe(EventPostStatusEnum::PUBLISHED);
    });
});

// ── Relation morphOne ─────────────────────────────────────────────────────────

describe('TrainingPack::eventPost relation', function (): void {
    it('returns null when no event post exists', function (): void {
        $pack = eventPostPack();

        expect($pack->eventPost)->toBeNull();
    });

    it('returns the event post via morphOne', function (): void {
        $pack = eventPostPack();

        EventPost::create([
            'eventable_type' => TrainingPack::class,
            'eventable_id' => $pack->id,
            'type' => ClubEventTypeEnum::TRAINING,
            'title' => 'Mon stage',
            'description' => '',
            'location' => '',
            'status' => EventPostStatusEnum::DRAFT->value,
            'event_date' => '2026-07-06',
            'start_time' => '09:00:00',
            'icon' => '🎯',
        ]);

        expect($pack->fresh()->eventPost)->not->toBeNull()
            ->and($pack->fresh()->eventPost->title)->toBe('Mon stage');
    });
});

// ── Page publique ─────────────────────────────────────────────────────────────

describe('public events page — training pack', function (): void {
    it('shows a published training EventPost', function (): void {
        $pack = eventPostPack();

        EventPost::create([
            'eventable_type' => TrainingPack::class,
            'eventable_id' => $pack->id,
            'type' => ClubEventTypeEnum::TRAINING,
            'title' => 'Stage public 2026',
            'description' => 'Stage ouvert à tous.',
            'location' => 'Salle Blocry',
            'status' => EventPostStatusEnum::PUBLISHED->value,
            'event_date' => now()->addMonth()->toDateString(),
            'start_time' => '09:00:00',
            'icon' => '🎯',
            'price' => '350',
        ]);

        $this->get(route('eventPosts'))
            ->assertOk()
            ->assertSee('Stage public 2026')
            ->assertSee('350,00 €');
    });

    it('does not show a draft training EventPost', function (): void {
        $pack = eventPostPack();

        EventPost::create([
            'eventable_type' => TrainingPack::class,
            'eventable_id' => $pack->id,
            'type' => ClubEventTypeEnum::TRAINING,
            'title' => 'Stage caché brouillon',
            'description' => '',
            'location' => '',
            'status' => EventPostStatusEnum::DRAFT->value,
            'event_date' => now()->addMonth()->toDateString(),
            'start_time' => '09:00:00',
            'icon' => '🎯',
        ]);

        $this->get(route('eventPosts'))
            ->assertOk()
            ->assertDontSee('Stage caché brouillon');
    });
});
