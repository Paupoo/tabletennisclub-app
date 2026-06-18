<?php

declare(strict_types=1);

use App\Domains\Bar\Models\BarCategory;
use App\Domains\Bar\Models\BarOrder;
use App\Domains\Bar\Models\BarPayment;
use App\Domains\Bar\Models\BarProduct;
use App\Domains\Bar\Models\BarStockMovement;
use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\ClubAdmin\Club\Models\Table;
use App\Domains\ClubAdmin\Contact\Models\Contact;
use App\Domains\ClubAdmin\Contact\Models\EmailTemplate;
use App\Domains\ClubAdmin\Contact\Models\Spam;
use App\Domains\ClubAdmin\Payment\Models\BankImport;
use App\Domains\ClubAdmin\Payment\Models\CashRegister;
use App\Domains\ClubAdmin\Payment\Models\CashRegisterEntry;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\Subscriptions\Models\Registration;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\ClubPosts\Models\EventPost;
use App\Domains\ClubPosts\Models\NewsPost;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\InterclubResult;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Competitions\Tournament\Models\MatchSet;
use App\Domains\Competitions\Tournament\Models\Pool;
use App\Domains\Competitions\Tournament\Models\TableTournament;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentMatch;
use App\Domains\Competitions\Tournament\Models\TournamentPair;
use App\Domains\Competitions\Tournament\Models\TournamentRegistration;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Models\MeetingActionItem;
use App\Domains\Meetings\Models\MeetingAgendaItem;
use App\Domains\Meetings\Models\MeetingDateVote;
use App\Domains\Meetings\Models\MeetingMinutes;
use App\Domains\Meetings\Models\MeetingUser;
use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Domains\Shared\Models\AppSetting;
use App\Domains\Shared\Traits\HasAuditLog;
use App\Domains\Trainings\Models\Training;
use App\Domains\Trainings\Models\TrainingPack;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/**
 * @return array<int, class-string>
 */
function auditedModels(): array
{
    return [
        User::class,
        Guardian::class,
        Subscription::class,
        Registration::class,
        Payment::class,
        Transaction::class,
        CashRegister::class,
        CashRegisterEntry::class,
        BankImport::class,
        Contact::class,
        EmailTemplate::class,
        Spam::class,
        Room::class,
        Table::class,
        Season::class,
        League::class,
        Club::class,
        Team::class,
        Interclub::class,
        InterclubResult::class,
        Tournament::class,
        TournamentMatch::class,
        TournamentPair::class,
        TournamentRegistration::class,
        Pool::class,
        TableTournament::class,
        MatchSet::class,
        Training::class,
        TrainingPack::class,
        Meeting::class,
        MeetingUser::class,
        MeetingMinutes::class,
        MeetingAgendaItem::class,
        MeetingActionItem::class,
        MeetingDateVote::class,
        NewsPost::class,
        EventPost::class,
        BarProduct::class,
        BarCategory::class,
        BarOrder::class,
        BarPayment::class,
        BarStockMovement::class,
        AppSetting::class,
    ];
}

it('logs an activity when an audited model is updated', function () {
    $user = User::factory()->create(['first_name' => 'Original']);

    $user->update(['first_name' => 'Changed']);

    $activity = Activity::query()->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('updated')
        ->and($activity->subject)->toBeInstanceOf(User::class)
        ->and($activity->subject->id)->toBe($user->id)
        ->and($activity->attribute_changes['attributes']['first_name'])->toBe('Changed')
        ->and($activity->attribute_changes['old']['first_name'])->toBe('Original');
});

it('logs a created activity when an audited model is created', function () {
    $room = Room::factory()->create();

    $activity = Activity::query()
        ->forSubject($room)
        ->where('description', 'created')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->subject->id)->toBe($room->id);
});

it('logs a deleted activity for each transaction when bulk-deleted', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $transactions = collect([
        Transaction::create(['date' => '2026-06-01', 'description' => 'A', 'amount' => 10]),
        Transaction::create(['date' => '2026-06-02', 'description' => 'B', 'amount' => 20]),
    ]);

    Livewire::actingAs($admin)
        ->test('pages::club-admin.treasury.transactions')
        ->set('selected', $transactions->pluck('id')->map(fn ($id) => (string) $id)->all())
        ->call('bulkDelete');

    expect(Activity::query()->where('subject_type', Transaction::class)->where('description', 'deleted')->count())
        ->toBe(2);
});

it('logs a deleted activity for each contact when bulk-deleted', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $contacts = Contact::factory()->count(2)->create();

    Livewire::actingAs($admin)
        ->test('pages::website.contacts.index')
        ->set('selected', $contacts->pluck('id')->map(fn ($id) => (string) $id)->all())
        ->call('bulkDelete');

    expect(Activity::query()->where('subject_type', Contact::class)->where('description', 'deleted')->count())
        ->toBe(2);
});

it('logs a deleted activity for each spam entry when bulk-deleted', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $spams = Spam::factory()->count(2)->create();

    Livewire::actingAs($admin)
        ->test('pages::website.spams.index')
        ->set('selected', $spams->pluck('id')->map(fn ($id) => (string) $id)->all())
        ->call('bulkDelete');

    expect(Activity::query()->where('subject_type', Spam::class)->where('description', 'deleted')->count())
        ->toBe(2);
});

it('never bulk-deletes an audited model via whereIn()->delete(), which bypasses the audit log', function () {
    $shortNames = array_unique(array_map(fn (string $class): string => class_basename($class), auditedModels()));
    $violations = [];

    foreach ([app_path(), resource_path('views/pages')] as $directory) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $content = file_get_contents($file->getRealPath());

            foreach ($shortNames as $shortName) {
                if (preg_match('/\b' . preg_quote($shortName, '/') . '::whereIn\([^)]*\)\s*->\s*delete\(\)/', $content)) {
                    $violations[] = "{$file->getRealPath()} bulk-deletes {$shortName} via whereIn()->delete()";
                }
            }
        }
    }

    expect($violations)->toBeEmpty(
        "Mass deletes bypass Eloquent events and the audit log:\n" . implode("\n", $violations)
        . "\nUse Model::whereIn('id', \$ids)->get()->each(fn (\$m) => \$m->delete()) instead."
    );
});

it('applies the audit trait to every model in the agreed scope', function (string $modelClass) {
    expect(in_array(HasAuditLog::class, class_uses_recursive($modelClass), true))
        ->toBeTrue("{$modelClass} should use HasAuditLog");
})->with(auditedModels());

it('forbids the audit log page to users without access', function () {
    $user = User::factory()->create(['is_admin' => false, 'is_committee_member' => false, 'committee_role' => null]);

    $this->actingAs($user)
        ->get(route('admin.audit.index'))
        ->assertForbidden();
});

it('shows the audit log page to authorised users', function () {
    $user = User::factory()->create(['is_admin' => true]);

    $this->actingAs($user)
        ->get(route('admin.audit.index'))
        ->assertOk();
});

it('lists logged activity and filters it by item type', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $room = Room::factory()->create(['name' => 'Salle A']);
    $editedMember = User::factory()->create(['first_name' => 'Marcel']);
    $editedMember->update(['first_name' => 'Marcelle']);

    Livewire::actingAs($admin)
        ->test('pages::club-admin.audit.index')
        ->assertSee('Salle A')
        ->assertSee('Marcelle')
        ->set('modelFilter', Room::class)
        ->assertSee('Salle A')
        ->assertDontSee('Marcelle');
});

it('renders the audit log when a logged activity has an array-cast attribute', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    // Spam casts `inputs` to an array, so the created activity stores an array
    // value the audit table must render without an "Array to string" error.
    Spam::factory()->create([
        'inputs' => ['email' => 'spammer@example.com', 'message' => 'buy now'],
    ]);

    Livewire::actingAs($admin)
        ->test('pages::club-admin.audit.index')
        ->assertOk()
        ->assertSee('spammer@example.com');
});

it('grants audit log access to admins and management committee roles', function (array $attributes, bool $expected) {
    $user = User::factory()->create($attributes);

    expect($user->canViewAuditLog())->toBe($expected);
})->with([
    'platform admin' => [['is_admin' => true, 'is_committee_member' => false, 'committee_role' => null], true],
    'president' => [['is_admin' => false, 'is_committee_member' => true, 'committee_role' => CommitteeRolesEnum::PRESIDENT], true],
    'vice-president' => [['is_admin' => false, 'is_committee_member' => true, 'committee_role' => CommitteeRolesEnum::VICE_PRESIDENT], true],
    'secretary' => [['is_admin' => false, 'is_committee_member' => true, 'committee_role' => CommitteeRolesEnum::SECRETARY], true],
    'treasurer' => [['is_admin' => false, 'is_committee_member' => true, 'committee_role' => CommitteeRolesEnum::TREASURER], true],
    'plain administrator committee role' => [['is_admin' => false, 'is_committee_member' => true, 'committee_role' => CommitteeRolesEnum::ADMINISTRATOR], false],
    'regular member' => [['is_admin' => false, 'is_committee_member' => false, 'committee_role' => null], false],
]);
