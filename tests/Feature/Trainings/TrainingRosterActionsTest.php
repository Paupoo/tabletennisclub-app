<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Subscriptions\AddMemberToTrainingPackAction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

const TRAINING_ROSTER_COMPONENT = 'pages::club-events.trainings.index';

/*
 * La liste des participants nommait quatre groupes et ne laissait agir sur
 * aucun : on pouvait ajouter quelqu'un, jamais le retirer ni le déplacer. Il
 * fallait passer par la fiche d'affiliation du membre, sur un autre écran.
 *
 * Les quatre gestes ajoutés ici sont gardés par `subscriptions.manage`, et non
 * par le `trainings.manage` qui ouvre la page : sortir quelqu'un d'un pack
 * touche à l'argent de son affiliation.
 */

beforeEach(function (): void {
    $this->season = makeActiveSeason();

    // Deux délégations : l'écran s'ouvre avec TRAININGS, les actions demandent MEMBERS.
    $this->manager = User::factory()->isCommitteeMember()
        ->withRole(Role::TRAININGS, Role::MEMBERS)
        ->create();

    // La délégation entraînements seule : elle ouvre la page, elle n'agit pas.
    $this->coach = User::factory()->isCommitteeMember()
        ->withRole(Role::TRAININGS)
        ->create();
});

it('keeps the roster read-only for a delegation that cannot touch an affiliation', function (string $method, array $args): void {
    $pack = makeTrainingPack($this->season);

    Livewire::actingAs($this->coach)
        ->test(TRAINING_ROSTER_COMPONENT)
        ->call('openPack', $pack->id)
        ->call($method, ...$args)
        ->assertForbidden();
})->with([
    ['openRemoveFromRoster', [1]],
    ['confirmRemoveFromRoster', []],
    ['openLeaveMember', [1]],
    ['openMoveMember', [1]],
    ['confirmLeaveMember', []],
    ['confirmMoveMember', []],
])->group('training', 'enrollment');

it('dismisses a request without touching a euro', function (): void {
    Notification::fake();

    $pack = makeTrainingPack($this->season);
    $member = activeMember($this->season);
    $subscription = Subscription::where('user_id', $member->id)->where('season_id', $this->season->id)->firstOrFail();
    $subscription->trainingPacks()->attach($pack->id, ['status' => 'pending']);

    Livewire::actingAs($this->manager)
        ->test(TRAINING_ROSTER_COMPONENT)
        ->call('openPack', $pack->id)
        ->call('openRemoveFromRoster', $member->id)
        ->assertSet('removeFromRosterModal', true)
        ->call('confirmRemoveFromRoster')
        ->assertSet('removeFromRosterModal', false);

    // Rien n'a été validé ni facturé : la ligne n'a aucune histoire à garder.
    expect($subscription->trainingPacks()->where('training_pack_id', $pack->id)->exists())->toBeFalse();
})->group('training', 'enrollment');

it('refuses to drop a confirmed spot through the queue path', function (): void {
    Notification::fake();

    $pack = makeTrainingPack($this->season);
    $member = activeMember($this->season);
    $subscription = Subscription::where('user_id', $member->id)->where('season_id', $this->season->id)->firstOrFail();
    (new AddMemberToTrainingPackAction)($subscription, $pack);

    Livewire::actingAs($this->manager)
        ->test(TRAINING_ROSTER_COMPONENT)
        ->call('openPack', $pack->id)
        ->call('openRemoveFromRoster', $member->id)
        // La confirmation ne s'ouvre même pas : ce chemin ne traite pas les
        // places validées.
        ->assertSet('removeFromRosterModal', false);

    // Une place validée peut rendre de l'argent : elle passe par sa propre
    // modale, celle qui ouvre un remboursement.
    expect($subscription->trainingPacks()->where('training_pack_id', $pack->id)->first()->pivot->status)
        ->toBe('enrolled');
})->group('training', 'enrollment');

it('takes a confirmed spot out once the removal is confirmed', function (): void {
    Notification::fake();

    $pack = makeTrainingPack($this->season);
    $member = activeMember($this->season);
    $subscription = Subscription::where('user_id', $member->id)->where('season_id', $this->season->id)->firstOrFail();
    (new AddMemberToTrainingPackAction)($subscription, $pack);

    Livewire::actingAs($this->manager)
        ->test(TRAINING_ROSTER_COMPONENT)
        ->call('openPack', $pack->id)
        ->call('openLeaveMember', $member->id)
        ->assertSet('leaveMemberModal', true)
        ->call('confirmLeaveMember')
        ->assertSet('leaveMemberModal', false);

    expect($subscription->trainingPacks()->where('training_pack_id', $pack->id)->first()->pivot->status)
        ->toBe('left');
})->group('training', 'enrollment', 'money');

it('moves a confirmed spot to the pack that was picked', function (): void {
    Notification::fake();

    $from = makeTrainingPack($this->season);
    $to = makeTrainingPack($this->season, ['name' => 'Groupe du mardi']);
    $member = activeMember($this->season);
    $subscription = Subscription::where('user_id', $member->id)->where('season_id', $this->season->id)->firstOrFail();
    (new AddMemberToTrainingPackAction)($subscription, $from);

    Livewire::actingAs($this->manager)
        ->test(TRAINING_ROSTER_COMPONENT)
        ->call('openPack', $from->id)
        ->call('openMoveMember', $member->id)
        ->assertSet('moveMemberModal', true)
        ->set('moveTargetPackId', $to->id)
        ->call('confirmMoveMember')
        ->assertSet('moveMemberModal', false);

    expect($subscription->trainingPacks()->where('training_pack_id', $from->id)->first()->pivot->status)->toBe('left')
        ->and($subscription->trainingPacks()->where('training_pack_id', $to->id)->first()->pivot->status)->toBe('enrolled');
})->group('training', 'enrollment', 'money');

it('builds the destination list without going back for each pack', function (): void {
    Notification::fake();

    $from = makeTrainingPack($this->season);

    // La forme réelle : plafond non renseigné, donc `effectiveMaxParticipants()`
    // retombe sur la capacité de la salle — et `hasAvailableSpot()`, qui compose
    // le libellé de chaque option, va la chercher. Deux packs, pas un : un seul
    // candidat rendrait ce test complaisant.
    makeTrainingPack($this->season, ['name' => 'Mardi', 'max_participants' => null]);
    makeTrainingPack($this->season, ['name' => 'Jeudi', 'max_participants' => null]);

    $member = activeMember($this->season);
    $subscription = Subscription::where('user_id', $member->id)->where('season_id', $this->season->id)->firstOrFail();
    (new AddMemberToTrainingPackAction)($subscription, $from);

    $component = Livewire::actingAs($this->manager)
        ->test(TRAINING_ROSTER_COMPONENT)
        ->call('openPack', $from->id)
        ->call('openMoveMember', $member->id);

    expect($component->instance()->moveTargetOptions())->toHaveCount(2);
})->group('training', 'enrollment');

it('never offers the pack the member is standing in as a destination', function (): void {
    Notification::fake();

    $from = makeTrainingPack($this->season);
    $held = makeTrainingPack($this->season, ['name' => 'Déjà inscrit']);
    $withdrawn = makeTrainingPack($this->season, ['name' => 'Retiré de l\'offre', 'is_active' => false]);
    $open = makeTrainingPack($this->season, ['name' => 'Ouvert']);

    $member = activeMember($this->season);
    $subscription = Subscription::where('user_id', $member->id)->where('season_id', $this->season->id)->firstOrFail();
    (new AddMemberToTrainingPackAction)($subscription, $from);
    (new AddMemberToTrainingPackAction)($subscription, $held);

    $component = Livewire::actingAs($this->manager)
        ->test(TRAINING_ROSTER_COMPONENT)
        ->call('openPack', $from->id)
        ->call('openMoveMember', $member->id);

    $offered = array_column($component->instance()->moveTargetOptions(), 'packName');

    // Le pack courant, ceux déjà détenus et ceux retirés de l'offre sortent.
    // Un pack complet, lui, resterait — signalé, pas interdit.
    expect($offered)->toBe([$open->name]);
})->group('training', 'enrollment');
