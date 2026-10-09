<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\ExternalParticipants\EnrollExternalInCampAction;
use App\Actions\ClubAdmin\Subscriptions\EnrollInTrainingCampAction;
use App\Data\ExternalParticipant\ExternalIdentity;
use App\Domains\ClubAdmin\ExternalParticipants\Models\ExternalRegistration;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Shared\Enums\Role;
use App\Domains\Trainings\Models\Training;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Services\TrainingAttendanceService;
use App\Mail\ExternalCampEnrolmentEmail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function (): void {
    Mail::fake();
    Notification::fake();
    Club::factory()->ownClub()->create();

    $this->season = makeActiveSeason();
    $this->manager = User::factory()->isCommitteeMember()->withRole(Role::TRAININGS, Role::MEMBERS)->create();
});

function externalScreensCamp(array $overrides = []): TrainingPack
{
    return makeTrainingPack(test()->season, array_merge([
        'is_camp' => true,
        'allow_discount' => false,
        'price' => 80,
        'max_participants' => 10,
        'pack_start_date' => today()->addMonth()->startOfMonth()->toDateString(),
        'pack_end_date' => today()->addMonth()->startOfMonth()->addDays(4)->toDateString(),
        'externals_open_on' => today()->toDateString(),
    ], $overrides));
}

describe('opening a stage to non-members', function (): void {
    it('sets the price for non-members and the day they may be enrolled from', function (): void {
        $camp = externalScreensCamp(['externals_open_on' => null]);

        Livewire::actingAs($this->manager)
            ->test('pages::club-events.trainings.index')
            ->call('openEdit', $camp->id)
            ->assertSet('formExternalsOpenOn', '')
            ->set('formExternalPrice', '95')
            ->set('formExternalsOpenOn', '2026-12-01')
            ->call('save')
            ->assertHasNoErrors();

        expect($camp->refresh()->external_price)->toBe(95)
            ->and($camp->externals_open_on->toDateString())->toBe('2026-12-01');
    });

    it('opens a stage to non-members after its creation, even once members are on it', function (): void {
        $camp = externalScreensCamp(['externals_open_on' => null]);
        (new EnrollInTrainingCampAction)(Subscription::where('user_id', activeMember($this->season)->id)->firstOrFail(), $camp, byClub: true);

        Livewire::actingAs($this->manager)
            ->test('admin.trainings.camp-externals', ['packId' => $camp->id])
            ->assertDontSee(__('Add a non-member'));

        Livewire::actingAs($this->manager)
            ->test('pages::club-events.trainings.index')
            ->call('openEdit', $camp->id)
            ->set('formExternalsOpenOn', today()->toDateString())
            ->call('save')
            ->assertHasNoErrors();

        expect($camp->refresh()->externals_open_on?->toDateString())->toBe(today()->toDateString())
            ->and($camp->is_camp)->toBeTrue();

        Livewire::actingAs($this->manager)
            ->test('admin.trainings.camp-externals', ['packId' => $camp->id])
            ->assertSee(__('Add a non-member'));
    });

    it('never opens a season pack to non-members', function (): void {
        $pack = makeTrainingPack($this->season);

        Livewire::actingAs($this->manager)
            ->test('pages::club-events.trainings.index')
            ->call('openEdit', $pack->id)
            ->set('formExternalPrice', '95')
            ->set('formExternalsOpenOn', '2026-12-01')
            ->call('save');

        expect($pack->refresh()->external_price)->toBeNull()
            ->and($pack->externals_open_on)->toBeNull();
    });
});

describe('the non-members of a stage, on its sheet', function (): void {
    it('enrols a child with the adult who answers for them, and sends the confirmation', function (): void {
        $camp = externalScreensCamp(['external_price' => 95]);

        Livewire::actingAs($this->manager)
            ->test('admin.trainings.camp-externals', ['packId' => $camp->id])
            ->call('openAdd')
            ->set('firstName', 'Léa')
            ->set('lastName', 'Dupont')
            ->set('isMinor', true)
            ->set('guardianFirstName', 'Sophie')
            ->set('guardianLastName', 'Dupont')
            ->set('guardianPhone', '0470 12 34 56')
            ->set('email', 'parent@example.com')
            ->call('saveIdentity')
            ->assertHasNoErrors()
            ->assertSee('Dupont Léa')
            ->assertSee('0470 12 34 56');

        $registration = ExternalRegistration::sole();
        expect($registration->getAmountDue())->toBe(95.0)
            ->and($registration->created_by)->toBe($this->manager->id);
        Mail::assertQueued(ExternalCampEnrolmentEmail::class);
    });

    it('says when the stage opens to non-members instead of offering to add one', function (): void {
        $camp = externalScreensCamp(['externals_open_on' => today()->addDays(10)->toDateString()]);

        Livewire::actingAs($this->manager)
            ->test('admin.trainings.camp-externals', ['packId' => $camp->id])
            ->assertSee(__('Open to non-members on :date', ['date' => today()->addDays(10)->format('d/m/Y')]))
            ->call('openAdd')
            ->assertSet('identityModal', false);
    });

    it('shows nothing on a stage kept to members', function (): void {
        $camp = externalScreensCamp(['externals_open_on' => null]);

        Livewire::actingAs($this->manager)
            ->test('admin.trainings.camp-externals', ['packId' => $camp->id])
            ->assertDontSee(__('Non-members'));
    });

    it('corrects an address and sends the confirmation again', function (): void {
        $camp = externalScreensCamp();
        $registration = (new EnrollExternalInCampAction)($camp, new ExternalIdentity('Marc', 'Dupont', 'wrong@example.com'), sendConfirmation: false);

        Livewire::actingAs($this->manager)
            ->test('admin.trainings.camp-externals', ['packId' => $camp->id])
            ->call('openEdit', $registration->id)
            ->assertSet('email', 'wrong@example.com')
            ->set('email', 'right@example.com')
            ->call('saveIdentity')
            ->call('resendConfirmation', $registration->id);

        expect($registration->fresh()->email)->toBe('right@example.com');
        Mail::assertQueued(ExternalCampEnrolmentEmail::class, fn (ExternalCampEnrolmentEmail $mail): bool => $mail->hasTo('right@example.com'));
    });

    it('forces a price with its reason', function (): void {
        $camp = externalScreensCamp();
        $registration = (new EnrollExternalInCampAction)($camp, new ExternalIdentity('Marc', 'Dupont', 'marc@example.com'));

        Livewire::actingAs($this->manager)
            ->test('admin.trainings.camp-externals', ['packId' => $camp->id])
            ->call('openPrice', $registration->id)
            ->set('priceAmount', '40')
            ->set('priceReason', 'Two days out of four')
            ->call('savePrice')
            ->assertHasNoErrors();

        expect($registration->fresh()->getAmountDue())->toBe(40.0);
    });

    it('takes a withdrawal and an encoding error apart', function (): void {
        $camp = externalScreensCamp();
        $leaving = (new EnrollExternalInCampAction)($camp, new ExternalIdentity('Marc', 'Dupont', 'marc@example.com'));
        $mistake = (new EnrollExternalInCampAction)($camp, new ExternalIdentity('Paul', 'Durand', 'paul@example.com'));

        Livewire::actingAs($this->manager)
            ->test('admin.trainings.camp-externals', ['packId' => $camp->id])
            ->call('openExit', $leaving->id)
            ->set('exitMode', 'withdrawal')
            ->call('confirmExit')
            ->call('openExit', $mistake->id)
            ->set('exitMode', 'error')
            ->call('confirmExit')
            ->assertDispatched('external-registrations-changed');

        expect($leaving->fresh()->status)->toBe('left')
            ->and($mistake->fresh()->status)->toBe('cancelled');
    });

    it('lets a coach read the list but not change it', function (): void {
        $camp = externalScreensCamp();
        $registration = (new EnrollExternalInCampAction)($camp, new ExternalIdentity('Marc', 'Dupont', 'marc@example.com', '0470 00 00 00'));
        $coach = User::factory()->withRole(Role::COACH)->create();

        Livewire::actingAs($coach)
            ->test('admin.trainings.camp-externals', ['packId' => $camp->id])
            ->assertSee('0470 00 00 00')
            ->call('openExit', $registration->id)
            ->assertForbidden();
    });
});

describe('the coach calls the roll', function (): void {
    it('lists the non-members with the number to call, and marks them', function (): void {
        $coach = User::factory()->isCoach()->create();
        $camp = externalScreensCamp(['trainer_id' => $coach->id]);
        $registration = (new EnrollExternalInCampAction)($camp, new ExternalIdentity(
            firstName: 'Léa', lastName: 'Dupont', email: 'parent@example.com', isMinor: true,
            guardianFirstName: 'Sophie', guardianLastName: 'Dupont', guardianPhone: '0470 12 34 56',
        ));
        $session = Training::factory()->past()->for($coach, 'trainer')->for($camp, 'trainingPack')->create();

        Livewire::actingAs($coach)
            ->test('pages::club-events.trainings.coach')
            ->call('viewSession', $session->id)
            ->assertSee('Léa Dupont')
            ->assertSee('0470 12 34 56')
            ->call('setExternalAttendance', $registration->id, 'present')
            ->call('validateAttendance');

        expect(app(TrainingAttendanceService::class)->externalStatuses($session))->toBe([$registration->id => 'present']);
    });

    it('refuses to mark a non-member of another stage', function (): void {
        $coach = User::factory()->isCoach()->create();
        $camp = externalScreensCamp(['trainer_id' => $coach->id]);
        $elsewhere = (new EnrollExternalInCampAction)(externalScreensCamp(), new ExternalIdentity('Marc', 'Dupont', 'marc@example.com'));
        $session = Training::factory()->past()->for($coach, 'trainer')->for($camp, 'trainingPack')->create();

        Livewire::actingAs($coach)
            ->test('pages::club-events.trainings.coach')
            ->call('viewSession', $session->id)
            ->call('setExternalAttendance', $elsewhere->id, 'present');

        expect(app(TrainingAttendanceService::class)->externalStatuses($session))->toBe([]);
    });
});

describe('the stage sheet', function (): void {
    it('shows the non-members on the roster and in the attendance grid', function (): void {
        $camp = externalScreensCamp();
        (new EnrollExternalInCampAction)($camp, new ExternalIdentity('Marc', 'Dupont', 'marc@example.com'));

        Livewire::actingAs($this->manager)
            ->test('pages::club-events.trainings.index')
            ->call('openPack', $camp->id)
            ->assertSeeLivewire('admin.trainings.camp-externals')
            ->assertSee(__('non-member'));
    });
});
