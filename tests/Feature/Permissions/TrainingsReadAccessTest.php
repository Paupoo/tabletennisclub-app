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
        ->assertSee('openPack(' . $this->pack->id . ')')
        ->assertDontSee('openCreate')
        ->assertDontSee('openEdit(' . $this->pack->id . ')')
        ->assertDontSee('toggleEnrollments(' . $this->pack->id . ')')
        ->assertDontSee('openWithdrawPack(' . $this->pack->id . ')');
});

it('opens a pack for a reader without a way to change it', function (): void {
    Livewire::actingAs($this->reader)
        ->test('pages::club-events.trainings.index')
        ->call('openPack', $this->pack->id)
        ->assertOk()
        ->assertDontSee('openAddMember')
        ->assertDontSee('openCancel(' . $this->session->id . ')')
        ->assertDontSee('openEdit(' . $this->pack->id . ')');
});

it('still offers the writes to the trainings delegate', function (): void {
    Livewire::actingAs($this->delegate)
        ->test('pages::club-events.trainings.index')
        ->assertSee('openCreate');
});

it('refuses every write to a reader', function (string $method, array $arguments): void {
    Livewire::actingAs($this->reader)
        ->test('pages::club-events.trainings.index')
        ->set('cancelTrainingId', $this->session->id)
        ->set('discontinuingPackId', $this->pack->id)
        ->set('withdrawingPackId', $this->pack->id)
        ->call($method, ...$arguments)
        ->assertForbidden();

    expect(TrainingPack::find($this->pack->id))
        ->is_active->toBeTrue()
        ->enrollments_open->toBe($this->pack->enrollments_open);
})->with([
    'openCreate' => ['openCreate', []],
    'openEdit' => ['openEdit', [1]],
    'save' => ['save', []],
    'confirmRegeneration' => ['confirmRegeneration', []],
    'toggleEnrollments' => ['toggleEnrollments', [1]],
    'openWithdrawPack' => ['openWithdrawPack', [1]],
    'confirmWithdrawPack' => ['confirmWithdrawPack', []],
    'withdrawPack' => ['withdrawPack', [1]],
    'restorePack' => ['restorePack', [1]],
    'openDiscontinuePack' => ['openDiscontinuePack', [1]],
    'confirmDiscontinuePack' => ['confirmDiscontinuePack', []],
    'openCancel' => ['openCancel', [1]],
    'confirmCancel' => ['confirmCancel', []],
    'openAddMember' => ['openAddMember', []],
    'newLevel' => ['newLevel', []],
    'editLevel' => ['editLevel', [1]],
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
            'modelClass' => TrainingPack::class,
            'modelId' => $this->pack->id,
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
