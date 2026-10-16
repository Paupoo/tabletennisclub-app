<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;
use App\Domains\Trainings\Models\Training;
use App\Domains\Trainings\Models\TrainingPack;
use Livewire\Livewire;

pest()->group('training', 'permissions');

/*
| The training offer, read by the committee.
|
| The packs screen and the season planning were both behind a management right,
| and the packs screen trusted its route: withdrawing a pack, closing its
| enrolments or cancelling a session checked nothing once inside. Opened to
| `trainings.view`, each of those writes now answers for itself.
*/

beforeEach(function (): void {
    $this->season = makeActiveSeason();
    $this->pack = makeTrainingPack($this->season, ['name' => 'Pack du mardi']);
    $this->session = Training::factory()->create([
        'training_pack_id' => $this->pack->id,
        'season_id' => $this->season->id,
    ]);

    $this->reader = User::factory()->isCommitteeMember()->create();
    $this->delegate = User::factory()->withRole(Role::TRAININGS)->create();
});

it('opens the training screens to the committee', function (string $routeName): void {
    $this->actingAs($this->reader)->get(route($routeName))->assertOk();
})->with([
    'admin.trainings.index',
    'admin.planning.board',
]);

it('keeps a plain member out of them', function (string $routeName): void {
    $this->actingAs(User::factory()->create())->get(route($routeName))->assertForbidden();
})->with([
    'admin.trainings.index',
    'admin.planning.board',
]);

it('lists the packs for a reader without a way to change them', function (): void {
    Livewire::actingAs($this->reader)
        ->test('pages::club-events.trainings.index')
        ->assertSee('Pack du mardi')
        ->assertSee(route('admin.trainings.show', $this->pack))
        ->assertDontSee(route('admin.trainings.create'))
        ->assertDontSee(route('admin.trainings.edit', $this->pack))
        ->assertDontSee('toggleEnrollments(' . $this->pack->id . ')')
        ->assertDontSee('openWithdrawPack(' . $this->pack->id . ')');
});

it('opens a pack for a reader without a way to change it', function (): void {
    Livewire::actingAs($this->reader)
        ->test('pages::club-events.trainings.show', ['pack' => $this->pack->id])
        ->assertOk()
        ->assertDontSee('openAddMember')
        ->assertDontSee(route('admin.trainings.edit', $this->pack));

    Livewire::actingAs($this->reader)
        ->test('pages::club-events.trainings.pack-sessions', ['packId' => $this->pack->id])
        ->assertOk()
        ->assertDontSee('openCancel(' . $this->session->id . ')');
});

it('still offers the writes to the trainings delegate', function (): void {
    Livewire::actingAs($this->delegate)
        ->test('pages::club-events.trainings.index')
        ->assertSee(route('admin.trainings.create'));
});

it('keeps the wizard closed to a reader', function (): void {
    Livewire::actingAs($this->reader)
        ->test('pages::club-events.trainings.form')
        ->assertForbidden();

    Livewire::actingAs($this->reader)
        ->test('pages::club-events.trainings.form', ['pack' => $this->pack->id])
        ->assertForbidden();
});

it('refuses every write to a reader', function (string $component, array $state, string $method, array $arguments): void {
    $test = Livewire::actingAs($this->reader)->test(
        'pages::club-events.trainings.' . $component,
        match ($component) {
            'show' => ['pack' => $this->pack->id],
            'pack-sessions' => ['packId' => $this->pack->id],
            default => [],
        },
    );

    foreach ($state as $property => $value) {
        $test->set($property, $value === 'session' ? $this->session->id : $this->pack->id);
    }

    $test->call($method, ...$arguments)->assertForbidden();

    expect(TrainingPack::find($this->pack->id))
        ->is_active->toBeTrue()
        ->enrollments_open->toBe($this->pack->enrollments_open);
})->with([
    'toggleEnrollments' => ['index', [], 'toggleEnrollments', [1]],
    'openWithdrawPack' => ['index', [], 'openWithdrawPack', [1]],
    'confirmWithdrawPack' => ['index', ['withdrawingPackId' => 'pack'], 'confirmWithdrawPack', []],
    'withdrawPack' => ['index', [], 'withdrawPack', [1]],
    'restorePack' => ['index', [], 'restorePack', [1]],
    'openDiscontinuePack' => ['index', [], 'openDiscontinuePack', [1]],
    'confirmDiscontinuePack' => ['index', ['discontinuingPackId' => 'pack'], 'confirmDiscontinuePack', []],
    'newLevel' => ['index', [], 'newLevel', []],
    'editLevel' => ['index', [], 'editLevel', [1]],
    'openAddMember' => ['show', [], 'openAddMember', []],
    'openCancel' => ['pack-sessions', [], 'openCancel', [1]],
    'confirmCancel' => ['pack-sessions', ['cancelTrainingId' => 'session'], 'confirmCancel', []],
]);

it('lets a reader see the planning board without managing it', function (): void {
    Livewire::actingAs($this->reader)
        ->test('pages::club-admin.planning.board')
        ->assertSet('canManage', false);
});

it('lets the trainings delegate manage the planning board', function (): void {
    Livewire::actingAs($this->delegate)
        ->test('pages::club-admin.planning.board')
        ->assertSet('canManage', true);
});

it('offers a reader no way to publish a pack on the website', function (): void {
    Livewire::actingAs($this->reader)
        ->test('pages::club-events.trainings.index')
        ->assertDontSeeLivewire('admin.shared.event-post-button');
});

// The button used to save whatever it was handed: `can-publish` only greyed it.
it('refuses to publish from a button its host did not allow', function (): void {
    Livewire::actingAs($this->reader)
        ->test('admin.shared.event-post-button', [
            'model' => $this->pack,
            'eventType' => 'TRAINING',
            'icon' => '🎯',
            'canPublish' => false,
        ])
        ->set('eventTitle', 'Stage de printemps')
        ->set('eventDescription', 'Une description assez longue.')
        ->call('saveEventPost', 'published')
        ->assertForbidden();

    expect(TrainingPack::find($this->pack->id)->eventPost)->toBeNull();
});
