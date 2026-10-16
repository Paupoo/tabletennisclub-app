<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Trainings\Models\Training;
use Livewire\Livewire;

pest()->group('training');

/*
 * La fiche d'un pack, l'assistant et la liste vivaient dans un seul composant
 * de 3 700 lignes : choisir la raison d'une annulation refaisait le rendu de
 * la liste, des trois onglets et de la grille de présences. Chacun a désormais
 * son adresse, et seul l'onglet ouvert est monté.
 */
beforeEach(function (): void {
    $this->season = makeActiveSeason();
    $this->pack = makeTrainingPack($this->season, ['name' => 'Pack du mardi']);
    $this->admin = User::factory()->isAdmin()->create();
    $this->reader = User::factory()->isCommitteeMember()->create();
});

it('gives each pack its own page', function (): void {
    $this->actingAs($this->reader)
        ->get(route('admin.trainings.show', $this->pack))
        ->assertOk()
        ->assertSee('Pack du mardi');
});

it('sends the old ?pack= link to the pack page', function (): void {
    $this->actingAs($this->reader)
        ->get(route('admin.trainings.index', ['pack' => $this->pack->id]))
        ->assertRedirect(route('admin.trainings.show', $this->pack));
});

it('mounts only the open tab', function (): void {
    $this->actingAs($this->reader)
        ->get(route('admin.trainings.show', ['pack' => $this->pack, 'tab' => 'attendance']))
        ->assertOk()
        ->assertSeeLivewire('pages::club-events.trainings.pack-attendance')
        ->assertDontSeeLivewire('pages::club-events.trainings.pack-roster')
        ->assertDontSeeLivewire('pages::club-events.trainings.pack-sessions');
});

it('opens on the members tab', function (): void {
    $this->actingAs($this->reader)
        ->get(route('admin.trainings.show', $this->pack))
        ->assertSeeLivewire('pages::club-events.trainings.pack-roster')
        ->assertDontSeeLivewire('pages::club-events.trainings.pack-attendance');
});

it('gives the wizard its own pages to whoever manages the offer', function (): void {
    $this->actingAs($this->admin)->get(route('admin.trainings.create'))->assertOk();
    $this->actingAs($this->admin)->get(route('admin.trainings.edit', $this->pack))
        ->assertOk()
        ->assertSee('Pack du mardi');
});

it('keeps the wizard closed to a reader', function (): void {
    $this->actingAs($this->reader)->get(route('admin.trainings.create'))->assertForbidden();
    $this->actingAs($this->reader)->get(route('admin.trainings.edit', $this->pack))->assertForbidden();
});

it('tells the pack page when a session is cancelled, so its numbers follow', function (): void {
    $session = Training::factory()->for($this->pack, 'trainingPack')->create();

    Livewire::actingAs($this->admin)
        ->test('pages::club-events.trainings.pack-sessions', ['packId' => $this->pack->id])
        ->call('openCancel', $session->id)
        ->call('confirmCancel')
        ->assertDispatched('training-pack-changed');

    expect($session->fresh()->isCancelled())->toBeTrue();
});
