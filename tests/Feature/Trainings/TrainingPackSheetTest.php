<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Trainings\Models\Training;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Services\TrainingAttendanceService;
use Livewire\Livewire;

/**
 * La fiche d'un pack : qui est dedans, et est-ce que ça tourne ?
 *
 * Le comité reconstruisait la liste des inscrits membre par membre depuis
 * l'écran des affiliations — le pack, lui, la connaît.
 */
beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->create();
});

/**
 * Attache un membre au pack avec le statut de pivot voulu.
 *
 * `for(User::factory())` est obligatoire : SubscriptionFactory tire un membre
 * existant au hasard, et deux inscriptions peuvent porter le même `user_id`.
 */
function enrol(TrainingPack $pack, string $status, array $pivot = [], array $userAttributes = []): Subscription
{
    $subscription = Subscription::factory()
        ->for($pack->season, 'season')
        ->for(User::factory()->create($userAttributes), 'user')
        ->create();

    $subscription->trainingPacks()->attach($pack->id, array_merge(['status' => $status], $pivot));

    return $subscription;
}

function sheetPack(array $attributes = []): TrainingPack
{
    return TrainingPack::factory()->create(array_merge(['max_participants' => 5], $attributes));
}

it('sorts each list of the roster by surname', function (): void {
    $pack = sheetPack();

    enrol($pack, 'enrolled', userAttributes: ['last_name' => 'Zorro', 'first_name' => 'Zoé']);
    enrol($pack, 'enrolled', userAttributes: ['last_name' => 'Étienne', 'first_name' => 'Anne']);
    enrol($pack, 'enrolled', userAttributes: ['last_name' => 'Albert', 'first_name' => 'Bob']);

    $roster = Livewire::actingAs($this->admin)
        ->test('pages::club-events.trainings.index')
        ->call('openPack', $pack->id)
        ->get('packRoster');

    // Ordre du français, pas celui des octets : « Étienne » se range entre
    // « Albert » et « Zorro », jamais après « Zorro ».
    expect(array_column($roster['enrolled'], 'name'))
        ->toBe(['Albert Bob', 'Étienne Anne', 'Zorro Zoé']);
})->group('training', 'pack');

it('separates the enrolled, the requests, the queue and those who left', function (): void {
    $pack = sheetPack();

    enrol($pack, 'enrolled', userAttributes: ['last_name' => 'Inscrit']);
    enrol($pack, 'pending', userAttributes: ['last_name' => 'Demande']);
    enrol($pack, 'waiting', ['waitlist_position' => 2], ['last_name' => 'Second']);
    enrol($pack, 'offered', ['waitlist_position' => 1], ['last_name' => 'Appele']);
    enrol($pack, 'left', ['ends_on' => '2026-01-15'], ['last_name' => 'Parti']);

    $roster = Livewire::actingAs($this->admin)
        ->test('pages::club-events.trainings.index')
        ->call('openPack', $pack->id)
        ->get('packRoster');

    expect($roster['enrolled'])->toHaveCount(1)
        ->and($roster['pending'])->toHaveCount(1)
        ->and($roster['past'])->toHaveCount(1)
        ->and($roster['waiting'])->toHaveCount(2)
        // Une offre en cours passe devant la file : elle a une échéance, la file non.
        ->and($roster['waiting'][0]['status'])->toBe('offered')
        ->and($roster['waiting'][0]['name'])->toStartWith('Appele')
        ->and($roster['waiting'][1]['name'])->toStartWith('Second');
})->group('training', 'pack');

it('leaves a cancelled membership out of the roster', function (): void {
    $pack = sheetPack();

    $gone = enrol($pack, 'enrolled', userAttributes: ['last_name' => 'Annule']);
    $gone->update(['status' => 'cancelled']);
    enrol($pack, 'enrolled', userAttributes: ['last_name' => 'Present']);

    $roster = Livewire::actingAs($this->admin)
        ->test('pages::club-events.trainings.index')
        ->call('openPack', $pack->id)
        ->get('packRoster');

    // Même règle que committedCount() : une affiliation annulée ne tient plus
    // de place, elle ne doit donc plus tenir de ligne.
    expect($roster['enrolled'])->toHaveCount(1)
        ->and($roster['enrolled'][0]['name'])->toContain('Present');
})->group('training', 'pack');

it('counts the attendance of each member over the recorded sessions only', function (): void {
    $pack = sheetPack();
    $coach = User::factory()->create();
    $regular = enrol($pack, 'enrolled', userAttributes: ['last_name' => 'Assidu']);
    $ghost = enrol($pack, 'enrolled', userAttributes: ['last_name' => 'Fantome']);

    $recorded = Training::factory()->past(7)->for($pack, 'trainingPack')->create();
    $service = app(TrainingAttendanceService::class);
    $service->record($recorded, $regular->user, 'present');
    $service->record($recorded, $ghost->user, 'absent');
    $service->validate($recorded, $coach);

    // Séance jamais pointée : elle ne doit peser sur aucun dénominateur, sinon
    // un oubli de pointage se lirait comme une désaffection.
    Training::factory()->past(14)->for($pack, 'trainingPack')->create();

    $component = Livewire::actingAs($this->admin)
        ->test('pages::club-events.trainings.index')
        ->call('openPack', $pack->id);

    $rates = collect($component->get('packRoster')['enrolled'])->pluck('rate', 'name');

    expect($rates->first())->toBe(100)
        ->and($rates->last())->toBe(0)
        ->and($component->get('packSummary')['turnout'])->toBe(50);
})->group('training', 'attendance');

it('counts the spots left against what is actually committed', function (): void {
    $pack = sheetPack(['max_participants' => 3]);

    enrol($pack, 'enrolled');
    // Une place offerte est retenue 48 h : elle ne doit pas être comptée libre.
    enrol($pack, 'offered', ['waitlist_position' => 1]);

    $summary = Livewire::actingAs($this->admin)
        ->test('pages::club-events.trainings.index')
        ->call('openPack', $pack->id)
        ->get('packSummary');

    expect($summary['enrolled'])->toBe(1)
        ->and($summary['capped'])->toBeTrue()
        ->and($summary['spotsLeft'])->toBe(1);
})->group('training', 'pack');

it('gives no spot count for a self-service pack', function (): void {
    $pack = sheetPack(['is_open_enrollment' => true]);

    $summary = Livewire::actingAs($this->admin)
        ->test('pages::club-events.trainings.index')
        ->call('openPack', $pack->id)
        ->get('packSummary');

    expect($summary['capped'])->toBeFalse()
        ->and($summary['spotsLeft'])->toBeNull();
})->group('training', 'pack');

it('splits the sessions into held, to come and cancelled', function (): void {
    $pack = sheetPack();

    Training::factory()->past(7)->for($pack, 'trainingPack')->create();
    Training::factory()->past(14)->for($pack, 'trainingPack')->create(['status' => 'cancelled_free']);
    Training::factory()->for($pack, 'trainingPack')->create(['start' => now()->addWeek(), 'end' => now()->addWeek()->addHours(2)]);

    $summary = Livewire::actingAs($this->admin)
        ->test('pages::club-events.trainings.index')
        ->call('openPack', $pack->id)
        ->get('packSummary');

    expect($summary['sessions'])->toBe(3)
        ->and($summary['held'])->toBe(1)
        ->and($summary['cancelled'])->toBe(1)
        ->and($summary['upcoming'])->toBe(1);
})->group('training', 'pack');

it('renders the sheet with its roster and its facts', function (): void {
    $pack = sheetPack(['name' => 'Jeudi Élite']);
    $member = enrol($pack, 'enrolled', userAttributes: ['last_name' => 'Vandenberghe']);

    // Le rendu est la seule vraie vérification : une erreur Blade ne se voit
    // pas sur les données du composant.
    Livewire::actingAs($this->admin)
        ->test('pages::club-events.trainings.index')
        ->call('openPack', $pack->id)
        ->assertOk()
        ->assertSee('Jeudi Élite')
        ->assertSee($member->user->last_name)
        ->assertSee(__('Coach'))
        ->assertSee(__('Schedule'));
})->group('training', 'pack');
