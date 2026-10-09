<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\ExternalParticipants\EnrollExternalInCampAction;
use App\Actions\ClubAdmin\Payments\AllocateTransactionAction;
use App\Actions\ClubAdmin\Payments\OpenRefundAction;
use App\Data\ExternalParticipant\ExternalIdentity;
use App\Domains\ClubAdmin\ExternalParticipants\Models\ExternalRegistration;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\Role;
use App\Domains\Trainings\Models\Training;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Services\TrainingAttendanceService;
use App\Services\ClubAdmin\Dashboard\PendingTasks;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;

/*
 * A non-member's data lives as long as the club needs it: six months after the
 * stage ended, everything that names them goes. The money stays — the
 * accounts must be kept — and so do the status, the price and the attendance.
 */

beforeEach(function (): void {
    Mail::fake();
    Notification::fake();
    Club::factory()->ownClub()->create();
});

/**
 * A stage that ended `$monthsAgo` months ago, with one paid non-member on it.
 */
function erasureRegistration(int $monthsAgo, bool $paid = true): ExternalRegistration
{
    test()->travelTo(now()->subMonths($monthsAgo)->subWeeks(2));

    $camp = TrainingPack::factory()->camp()->create([
        // The season does not matter here, only the stage's dates: one season
        // for every stage of a test, so they never overlap.
        'season_id' => (Season::query()->first() ?? makeActiveSeason())->id,
        'price' => 80,
        'externals_open_on' => today()->toDateString(),
        'pack_start_date' => today()->addWeek()->toDateString(),
        'pack_end_date' => today()->addWeeks(2)->toDateString(),
    ]);

    $registration = (new EnrollExternalInCampAction)($camp, new ExternalIdentity(
        firstName: 'Léa',
        lastName: 'Dupont',
        email: 'parent@example.com',
        isMinor: true,
        guardianFirstName: 'Sophie',
        guardianLastName: 'Dupont',
        guardianPhone: '0470 12 34 56',
    ));

    if ($paid) {
        $claim = $registration->payments()->sole();
        (new AllocateTransactionAction)->credit($claim, 80.0, 'cash');
    }

    test()->travelBack();

    return $registration->fresh();
}

it('erases who a non-member was six months after the stage ended, and keeps the rest', function (): void {
    $registration = erasureRegistration(monthsAgo: 7);
    $session = Training::factory()->past(200)->for($registration->registrable, 'trainingPack')->create();
    app(TrainingAttendanceService::class)->recordExternal($session, $registration, 'present');

    $this->artisan('external-participants:anonymize')->assertSuccessful();

    $erased = $registration->fresh();
    expect($erased->anonymized_at)->not->toBeNull()
        ->and($erased->first_name)->toBeNull()
        ->and($erased->last_name)->toBeNull()
        ->and($erased->email)->toBeNull()
        ->and($erased->guardian_first_name)->toBeNull()
        ->and($erased->guardian_last_name)->toBeNull()
        ->and($erased->guardian_phone)->toBeNull()
        ->and($erased->status)->toBe('enrolled')
        ->and($erased->getAmountDue())->toBe(80.0)
        ->and($erased->payments()->sole()->status)->toBe('paid')
        ->and(app(TrainingAttendanceService::class)->externalStatuses($session))->toBe([$erased->id => 'present']);
});

it('leaves a stage that ended less than six months ago alone', function (): void {
    $registration = erasureRegistration(monthsAgo: 5);

    $this->artisan('external-participants:anonymize')->assertSuccessful();

    expect($registration->fresh()->email)->toBe('parent@example.com');
});

it('waits while money is still open, and tells the treasurer', function (): void {
    $unpaid = erasureRegistration(monthsAgo: 7, paid: false);
    $owedBack = erasureRegistration(monthsAgo: 7);
    (new OpenRefundAction)->forPayable($owedBack, 80.0, 'BE68539007547034');
    $treasurer = User::factory()->withRole(Role::TREASURY)->create();

    $this->artisan('external-participants:anonymize')->assertSuccessful();

    expect($unpaid->fresh()->email)->toBe('parent@example.com')
        ->and($owedBack->fresh()->email)->toBe('parent@example.com');

    $this->actingAs($treasurer)->get('/');
    expect(app(PendingTasks::class)->for($treasurer)['external_erasures']->count ?? null)->toBe(2);
});

it('erases the refund account too once the refund was wired', function (): void {
    $registration = erasureRegistration(monthsAgo: 7);
    $refund = (new OpenRefundAction)->forPayable($registration, 80.0, 'BE68539007547034');
    $refund->forceFill(['status' => 'refunded', 'amount_paid' => 80])->save();

    $this->artisan('external-participants:anonymize')->assertSuccessful();

    expect($registration->fresh()->anonymized_at)->not->toBeNull()
        ->and($refund->fresh()->refund_iban)->toBeNull();
});

it('never writes who a non-member is into the audit log', function (): void {
    $registration = erasureRegistration(monthsAgo: 0);
    $registration->update(['email' => 'other@example.com', 'status' => 'left']);

    $logged = Activity::query()->where('subject_type', ExternalRegistration::class)->get()
        ->map(fn (Activity $activity): string => json_encode($activity->toArray()))
        ->implode(' ');

    expect($logged)->toContain('left')
        ->not->toContain('Dupont')
        ->not->toContain('example.com')
        ->not->toContain('0470');
});
