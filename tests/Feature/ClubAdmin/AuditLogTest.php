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
use App\Domains\Shared\Enums\Role;
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

it('logs an activity when an audited model is updated', function (): void {
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

it('logs a created activity when an audited model is created', function (): void {
    $room = Room::factory()->create();

    $activity = Activity::query()
        ->forSubject($room)
        ->where('description', 'created')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->subject->id)->toBe($room->id);
});

it('logs a deleted activity for each transaction when bulk-deleted', function (): void {
    $admin = User::factory()->isAdmin()->create();
    $transactions = collect([
        Transaction::create(['date' => '2026-06-01', 'description' => 'A', 'amount' => 10]),
        Transaction::create(['date' => '2026-06-02', 'description' => 'B', 'amount' => 20]),
    ]);

    Livewire::actingAs($admin)
        ->test('pages::club-admin.treasury.transactions')
        ->set('selected', $transactions->pluck('id')->map(fn ($id): string => (string) $id)->all())
        ->call('bulkDelete');

    expect(Activity::query()->where('subject_type', Transaction::class)->where('description', 'deleted')->count())
        ->toBe(2);
});

it('logs a deleted activity for each contact when bulk-deleted', function (): void {
    $admin = User::factory()->isAdmin()->create();
    $contacts = Contact::factory()->count(2)->create();

    Livewire::actingAs($admin)
        ->test('pages::website.contacts.index')
        ->set('selected', $contacts->pluck('id')->map(fn ($id): string => (string) $id)->all())
        ->call('bulkDelete');

    expect(Activity::query()->where('subject_type', Contact::class)->where('description', 'deleted')->count())
        ->toBe(2);
});

it('logs a deleted activity for each spam entry when bulk-deleted', function (): void {
    $admin = User::factory()->isAdmin()->create();
    $spams = Spam::factory()->count(2)->create();

    Livewire::actingAs($admin)
        ->test('pages::website.spams.index')
        ->set('selected', $spams->pluck('id')->map(fn ($id): string => (string) $id)->all())
        ->call('bulkDelete');

    expect(Activity::query()->where('subject_type', Spam::class)->where('description', 'deleted')->count())
        ->toBe(2);
});

it('never bulk-deletes an audited model via whereIn()->delete(), which bypasses the audit log', function (): void {
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

it('applies the audit trait to every model in the agreed scope', function (string $modelClass): void {
    expect(in_array(HasAuditLog::class, class_uses_recursive($modelClass), true))
        ->toBeTrue("{$modelClass} should use HasAuditLog");
})->with(auditedModels());

it('forbids the audit log page to users without access', function (): void {
    $user = User::factory()->create(['committee_role' => null]);

    $this->actingAs($user)
        ->get(route('admin.audit.index'))
        ->assertForbidden();
});

it('shows the audit log page to authorised users', function (): void {
    $user = User::factory()->isAdmin()->create();

    $this->actingAs($user)
        ->get(route('admin.audit.index'))
        ->assertOk();
});

it('lists logged activity and filters it by item type', function (): void {
    $admin = User::factory()->isAdmin()->create();

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

it('finds an audit entry by the name of its author', function (): void {
    $viewer = User::factory()->isAdmin()->create(['first_name' => 'Zoé', 'last_name' => 'Verhoeven', 'email' => 'viewer@ctt.test']);
    $author = User::factory()->isAdmin()->create(['first_name' => 'Jean', 'last_name' => 'Dupont', 'email' => 'author@ctt.test']);
    $bystander = User::factory()->isAdmin()->create(['first_name' => 'Paul', 'last_name' => 'Lefebvre', 'email' => 'bystander@ctt.test']);

    $this->actingAs($author);
    Room::factory()->create(['name' => 'Alpharoom']);

    $this->actingAs($bystander);
    Room::factory()->create(['name' => 'Omegaroom']);

    Livewire::actingAs($viewer)
        ->test('pages::club-admin.audit.index')
        ->set('search', 'Dupont')
        ->assertSee('Alpharoom')
        ->assertDontSee('Omegaroom');
});

it('finds an audit entry by the name of the member it targets', function (): void {
    $viewer = User::factory()->isAdmin()->create(['first_name' => 'Zoé', 'last_name' => 'Verhoeven', 'email' => 'viewer@ctt.test']);

    $target = User::factory()->create(['first_name' => 'Alice', 'last_name' => 'Vandenberghe', 'email' => 'target@ctt.test']);
    $target->update(['first_name' => 'Alicia']);

    $other = User::factory()->create(['first_name' => 'Bob', 'last_name' => 'Lemoine', 'email' => 'other@ctt.test']);
    $other->update(['first_name' => 'Bobby']);

    Livewire::actingAs($viewer)
        ->test('pages::club-admin.audit.index')
        ->set('search', 'Vandenberghe')
        ->assertSee('Alicia')
        ->assertDontSee('Bobby');
});

it('finds audit entries by the human label of the audited model', function (): void {
    $viewer = User::factory()->isAdmin()->create(['first_name' => 'Zoé', 'last_name' => 'Verhoeven', 'email' => 'viewer@ctt.test']);

    $member = User::factory()->create(['first_name' => 'Marc', 'last_name' => 'Lemoine', 'email' => 'member@ctt.test']);
    $member->update(['first_name' => 'Marcolino']);

    Room::factory()->create(['name' => 'Alpharoom']);

    // "Salle" is the fr_BE label of the Room model; it lives nowhere in the log.
    Livewire::actingAs($viewer)
        ->test('pages::club-admin.audit.index')
        ->set('search', 'Salle')
        ->assertSee('Alpharoom')
        ->assertDontSee('Marcolino');
});

/*
| The search used to be applied as an ungrouped `where(...)->orWhere(...)`, so
| SQL operator precedence turned "search AND author" into "search OR (… AND
| author)" and entries from every other author leaked into a filtered list.
*/
it('keeps the author filter binding when it is combined with the search', function (): void {
    $viewer = User::factory()->isAdmin()->create(['first_name' => 'Zoé', 'last_name' => 'Verhoeven', 'email' => 'viewer@ctt.test']);
    $author = User::factory()->isAdmin()->create(['first_name' => 'Jean', 'last_name' => 'Dupont', 'email' => 'author@ctt.test']);
    $bystander = User::factory()->isAdmin()->create(['first_name' => 'Paul', 'last_name' => 'Lefebvre', 'email' => 'bystander@ctt.test']);

    $this->actingAs($author);
    Room::factory()->create(['name' => 'Alpharoom']);

    $this->actingAs($bystander);
    Room::factory()->create(['name' => 'Omegaroom']);

    Livewire::actingAs($viewer)
        ->test('pages::club-admin.audit.index')
        ->set('search', 'Salle')
        ->set('causerFilter', (string) $author->id)
        ->assertSee('Alpharoom')
        ->assertDontSee('Omegaroom')
        ->assertViewHas('activities', function ($activities) use ($author): bool {
            return $activities->total() > 0
                && collect($activities->items())->every(fn (Activity $activity): bool => (int) $activity->causer_id === $author->id);
        });
});

it('renders the audit log when a logged activity has an array-cast attribute', function (): void {
    $admin = User::factory()->isAdmin()->create();

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

/*
| Reading the audit log used to follow the statutory title — full admins plus the
| president, vice-president, secretary and treasurer. It is the supervision duty
| now, so the title neither grants nor withholds it.
*/
it('grants audit log access on the supervision delegation', function (array $roles, ?CommitteeRolesEnum $committeeRole, bool $expected): void {
    $user = User::factory()->withRole(...$roles)->create(['committee_role' => $committeeRole]);

    expect($user->canViewAuditLog())->toBe($expected);
})->with([
    'platform admin' => [[Role::ADMINISTRATOR], null, true],
    'supervision delegate' => [[Role::SUPERVISION], null, true],
    'supervision delegate who is also president' => [[Role::COMMITTEE, Role::SUPERVISION], CommitteeRolesEnum::PRESIDENT, true],
    'president without the delegation' => [[Role::COMMITTEE], CommitteeRolesEnum::PRESIDENT, false],
    'treasurer without the delegation' => [[Role::COMMITTEE], CommitteeRolesEnum::TREASURER, false],
    'regular member' => [[], null, false],
]);
