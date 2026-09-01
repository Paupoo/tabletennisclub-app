<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\MemberImport;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Ranking;
use App\Domains\Shared\Enums\Role;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

pest()->group('club-admin', 'users', 'import');

const IMPORT_COMPONENT = 'pages::club-admin.users.import';

const IMPORT_HEADER = 'Licence;Nom;DATE NAISSANCE;CH;CD;LFM;CONF;SA;Statut;Date 1ere affiliation;Email;Tel;GSM;Adresse;Numéro;CP;Localité';

/**
 * A listing as the federation hands it over: Windows-1252 bytes, CRLF endings.
 *
 * @param  array<int, string>  $lines
 */
function importListing(array $lines): UploadedFile
{
    $utf8 = implode("\r\n", [IMPORT_HEADER, ...$lines]) . "\r\n";

    return UploadedFile::fake()->createWithContent(
        'Liste-affilies.csv',
        mb_convert_encoding($utf8, 'Windows-1252', 'UTF-8'),
    );
}

beforeEach(function (): void {
    $this->secretary = User::factory()->withRole(Role::MEMBERS)->create();
    actingAs($this->secretary);
});

describe('who may seed the roster from the federation', function (): void {

    it('opens the screen for the members délégation', function (): void {
        get(route('admin.users.import'))->assertSuccessful();
    });

    it('is offered from the members list, where the roster is looked after', function (): void {
        Livewire::test('pages::club-admin.users.index')
            ->assertSee(route('admin.users.import'), escape: false);

        actingAs(User::factory()->create());

        Livewire::test('pages::club-admin.users.index')
            ->assertDontSee(route('admin.users.import'), escape: false);
    });

    it('closes it to a member who holds no such duty', function (): void {
        actingAs(User::factory()->create());

        get(route('admin.users.import'))->assertForbidden();
    });

    it('refuses to write the roster on behalf of someone who may not', function (): void {
        $listing = importListing([
            '166036;DUPONT MARC;1990-06-05;C2;*;N;N;SE;JO;2020-09-24;marc@example.com;;0475123456;RUE DU TEST;13;1348;LOUVAIN-LA-NEUVE',
        ]);

        actingAs(User::factory()->create());

        Livewire::test(IMPORT_COMPONENT)
            ->set('importFile', $listing)
            ->call('parse')
            ->assertForbidden();
    });
});

describe('reviewing a federation listing before importing it', function (): void {

    it('proposes what to do with each affiliate the listing carries', function (): void {
        User::factory()->create(['licence' => '166036', 'first_name' => 'Marc', 'last_name' => 'Dupont']);

        Livewire::test(IMPORT_COMPONENT)
            ->set('importFile', importListing([
                '166036;DUPONT MARC;1990-06-05;C2;*;N;N;SE;JO;2020-09-24;marc@example.com;;0475123456;RUE DU TEST;13;1348;LOUVAIN-LA-NEUVE',
                '166037;LEGRAND ANNE;1985-03-02;D4;D4;N;N;SE;LR;2021-09-01;anne@example.com;;0475987654;RUE DU TEST;15;1348;LOUVAIN-LA-NEUVE',
            ]))
            ->call('parse')
            ->assertSet('step', 2)
            ->assertCount('rows', 2)
            ->assertSet('rows.2.action', 'update')
            ->assertSet('rows.2.lastName', 'Dupont')
            ->assertSet('rows.3.action', 'create')
            ->assertSet('rows.3.firstName', 'Anne');
    });

    /*
     * A listing of two hundred lines where every one of them shouts as loudly as
     * the next is a listing nobody reads. The handful that ask something are held
     * apart from the ones that only have to be known about.
     */
    it('holds apart the affiliates that ask something from the ones that do not', function (): void {
        User::factory()->create(['licence' => '166036', 'first_name' => 'Marc', 'last_name' => 'Dupont']);

        $component = Livewire::test(IMPORT_COMPONENT)
            ->set('importFile', importListing([
                '166036;DUPONT MARC;1990-06-05;C2;*;N;N;SE;JO;2020-09-24;marc@example.com;;0475123456;RUE DU TEST;13;1348;LOUVAIN-LA-NEUVE',
                '166037;DE CLERCQ ANNE;1985-03-02;D4;D4;N;N;SE;LR;2021-09-01;anne@example.com;;0475987654;RUE DU TEST;15;1348;LOUVAIN-LA-NEUVE',
                '166038;PETIT LEA;2014-05-08;NC;;N;N;PU;JO;2023-09-01;papa@example.com;;0475111222;RUE DU TEST;17;1348;LOUVAIN-LA-NEUVE',
            ]))
            ->call('parse')
            ->assertSee(__('Needs your attention'))
            ->assertSee(__('Nothing to report'))
            // The roster answered, the parser did not guess: nothing to look at.
            ->assertSet('rows.2.needsReview', false)
            // Past two words the split of the name is a guess.
            ->assertSet('rows.3.needsReview', true)
            // A child's address is usually a parent's, and the file rarely proves it.
            ->assertSet('rows.4.needsReview', true);

        expect(array_keys($component->instance()->linesToReview))->toBe([3, 4])
            ->and(array_keys($component->instance()->linesReadToImport))->toBe([2]);
    });

    /*
     * The two sections are settled when the file is read and never recomputed. A
     * line that changed sides the moment it was answered would shift the grid
     * under the pointer and hand the next click to the wrong affiliate.
     */
    it('leaves an answered line in the section it was filed under', function (): void {
        User::factory()->create([
            'first_name' => 'Marc',
            'last_name' => 'Dupont',
            'birthdate' => '1991-02-11',
            'licence' => null,
            'email' => 'other@example.com',
        ]);

        $component = Livewire::test(IMPORT_COMPONENT)
            ->set('importFile', importListing([
                '166036;DUPONT MARC;1990-06-05;C2;*;N;N;SE;JO;2020-09-24;marc@example.com;;0475123456;RUE DU TEST;13;1348;LOUVAIN-LA-NEUVE',
            ]))
            ->call('parse')
            ->assertSet('rows.2.needsReview', true)
            ->set('rows.2.action', 'create')
            ->assertSet('rows.2.needsReview', true);

        expect(array_keys($component->instance()->linesToReview))->toBe([2])
            ->and($component->instance()->linesReadToImport)->toBe([]);
    });

    /*
     * An archived member is a question, so their line is held with the ones that
     * ask something — never filed away as settled.
     */
    it('files an archived member among the lines that ask something', function (): void {
        User::factory()->create([
            'licence' => '166036',
            'first_name' => 'Marc',
            'last_name' => 'Dupont',
            'birthdate' => '1990-06-05',
        ])->delete();

        $component = Livewire::test(IMPORT_COMPONENT)
            ->set('importFile', importListing([
                '166036;DUPONT MARC;1990-06-05;C2;*;N;N;SE;JO;2020-09-24;marc@example.com;;0475123456;RUE DU TEST;13;1348;LOUVAIN-LA-NEUVE',
            ]))
            ->call('parse')
            ->assertSet('rows.2.outcome', 'archived')
            ->assertSet('rows.2.needsReview', true);

        expect($component->instance()->linesReadToImport)->toBe([]);
    });

    /*
     * Columns the federation shifted by one: the street landed where the postcode
     * should be. The parser reads it, flags it, and the reviewer is the one who
     * looks — so the line cannot sit in the folded section.
     */
    it('files a line whose columns look shifted among the ones that ask something', function (): void {
        User::factory()->create(['licence' => '166036', 'first_name' => 'Marc', 'last_name' => 'Dupont']);

        Livewire::test(IMPORT_COMPONENT)
            ->set('importFile', importListing([
                '166036;DUPONT MARC;1990-06-05;C2;*;N;N;SE;JO;2020-09-24;marc@example.com;;0475123456;RUE DU TEST;13;LOUVAIN-LA-NEUVE;',
            ]))
            ->call('parse')
            ->assertSet('rows.2.needsAddressReview', true)
            ->assertSet('rows.2.needsReview', true);
    });

    /*
     * The card was lifted out of the grid into a partial, and a partial that lost
     * the line it was handed would bind every card to the same affiliate: the
     * secretary would correct one name and overwrite another. The binding is what
     * proves the extraction, because setting `rows.N` in a test never goes through
     * the form at all.
     */
    it('binds each rendered card to its own line of the listing', function (): void {
        Livewire::test(IMPORT_COMPONENT)
            ->set('importFile', importListing([
                '166036;DUPONT MARC;1990-06-05;C2;*;N;N;SE;JO;2020-09-24;marc@example.com;;0475123456;RUE DU TEST;13;1348;LOUVAIN-LA-NEUVE',
                '166037;DE CLERCQ ANNE;1985-03-02;D4;D4;N;N;SE;LR;2021-09-01;anne@example.com;;0475987654;RUE DU TEST;15;1348;LOUVAIN-LA-NEUVE',
            ]))
            ->call('parse')
            ->assertSeeHtml('rows.2.lastName')
            ->assertSeeHtml('rows.2.action')
            ->assertSeeHtml('rows.3.lastName')
            ->assertSeeHtml('rows.3.action')
            ->assertDontSeeHtml('rows.0.lastName');
    });

    /*
     * Nothing to fold away, so no fold: an empty section is a heading that promises
     * something and delivers a blank.
     */
    it('drops a section when it holds nothing', function (): void {
        User::factory()->create(['licence' => '166036', 'first_name' => 'Marc', 'last_name' => 'Dupont']);

        Livewire::test(IMPORT_COMPONENT)
            ->set('importFile', importListing([
                '166036;DUPONT MARC;1990-06-05;C2;*;N;N;SE;JO;2020-09-24;marc@example.com;;0475123456;RUE DU TEST;13;1348;LOUVAIN-LA-NEUVE',
            ]))
            ->call('parse')
            ->assertSee(__('Nothing to report'))
            ->assertDontSee(__('Needs your attention'));
    });

    /*
     * The two answers the matcher cannot commit to. Left to a default, either
     * would write the federation's data onto somebody else's file — so the import
     * simply refuses to run until a human has said which it is.
     */
    it('holds the import back until every doubtful line has been settled', function (): void {
        User::factory()->create([
            'first_name' => 'Marc',
            'last_name' => 'Dupont',
            'birthdate' => '1991-02-11',
            'licence' => null,
            'email' => 'other@example.com',
        ]);

        $component = Livewire::test(IMPORT_COMPONENT)
            ->set('importFile', importListing([
                '166036;DUPONT MARC;1990-06-05;C2;*;N;N;SE;JO;2020-09-24;marc@example.com;;0475123456;RUE DU TEST;13;1348;LOUVAIN-LA-NEUVE',
            ]))
            ->call('parse')
            ->assertSet('rows.2.outcome', 'suspect')
            ->assertSet('rows.2.action', '')
            ->call('import')
            ->assertSet('step', 2);

        expect(MemberImport::count())->toBe(0)
            ->and(User::query()->where('licence', '166036')->exists())->toBeFalse();

        $component->set('rows.2.action', 'create')
            ->call('import')
            ->assertSet('step', 3);

        expect(User::query()->where('licence', '166036')->exists())->toBeTrue();
    });

    /*
     * The federation runs first and last name together in one column, and past
     * two words the split is a guess. The grid is where the guess gets fixed, and
     * what the reviewer leaves is what the roster records — not what was read.
     */
    it('records the names as the reviewer corrected them', function (): void {
        Livewire::test(IMPORT_COMPONENT)
            ->set('importFile', importListing([
                '166038;LEROY MARC ANTOINE;1994-11-30;B6;*;N;N;SE;JO;2019-09-12;marcantoine@example.com;;0470112233;RUE DU TEST;21;1348;LOUVAIN-LA-NEUVE',
            ]))
            ->call('parse')
            ->assertSet('rows.2.needsNameReview', true)
            ->assertSet('rows.2.lastName', 'Leroy Marc')
            ->assertSet('rows.2.firstName', 'Antoine')
            ->set('rows.2.lastName', 'Leroy')
            ->set('rows.2.firstName', 'Marc Antoine')
            ->call('import')
            ->assertSet('step', 3);

        $created = User::query()->where('licence', '166038')->first();

        expect($created->last_name)->toBe('Leroy')
            ->and($created->first_name)->toBe('Marc Antoine');
    });

    /*
     * The listing carries the birthdates, addresses and phone numbers of children.
     * It is read once and destroyed: the club keeps the members, not the file.
     */
    it('destroys the uploaded listing once it has been read into the roster', function (): void {
        $component = Livewire::test(IMPORT_COMPONENT)
            ->set('importFile', importListing([
                '166036;DUPONT MARC;1990-06-05;C2;*;N;N;SE;JO;2020-09-24;marc@example.com;;0475123456;RUE DU TEST;13;1348;LOUVAIN-LA-NEUVE',
            ]))
            ->call('parse');

        $uploaded = $component->instance()->importFile->getRealPath();

        expect(file_exists($uploaded))->toBeTrue();

        $component->call('import')->assertSet('step', 3);

        expect(file_exists($uploaded))->toBeFalse();
    });

    /*
     * The guarantee the whole feature is built around, checked on the path the
     * secretary actually takes.
     */
    it('sends nothing at all', function (): void {
        Mail::fake();
        Notification::fake();

        Livewire::test(IMPORT_COMPONENT)
            ->set('importFile', importListing([
                '166036;DUPONT MARC;1990-06-05;C2;*;N;N;SE;JO;2020-09-24;marc@example.com;;0475123456;RUE DU TEST;13;1348;LOUVAIN-LA-NEUVE',
                '166037;LEGRAND ANNE;1985-03-02;D4;D4;N;N;SE;LR;2021-09-01;anne@example.com;;0475987654;RUE DU TEST;15;1348;LOUVAIN-LA-NEUVE',
            ]))
            ->call('parse')
            ->call('import')
            ->assertSet('step', 3);

        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        Notification::assertNothingSent();
    });
});

describe('the report the run leaves behind', function (): void {

    it('counts what it wrote and keeps the lines it could not read', function (): void {
        Livewire::test(IMPORT_COMPONENT)
            ->set('importFile', importListing([
                '166036;DUPONT MARC;1990-06-05;C2;*;N;N;SE;JO;2020-09-24;marc@example.com;;0475123456;RUE DU TEST;13;1348;LOUVAIN-LA-NEUVE',
                ';LEGRAND ANNE;1985-03-02;D4;D4;N;N;SE;LR;2021-09-01;anne@example.com;;0475987654;RUE DU TEST;15;1348;LOUVAIN-LA-NEUVE',
            ]))
            ->call('parse')
            ->assertCount('rows', 1)
            ->call('import')
            ->assertSet('step', 3);

        $import = MemberImport::query()->latest('id')->first();

        expect($import->new_count)->toBe(1)
            ->and($import->error_count)->toBe(1)
            ->and($import->failed_rows)->toHaveCount(1)
            ->and($import->failed_rows[0]['line'])->toBe(3)
            ->and($import->failed_rows[0]['reason'])->toBe(__('Missing licence number.'))
            // The history outlives the file. It records where a line failed and
            // why, never what the line held.
            ->and(json_encode($import->failed_rows))->not->toContain('LEGRAND')
            ->and(json_encode($import->failed_rows))->not->toContain('anne@example.com');
    });

    /*
     * A member the club holds and the federation does not list. It proves nothing
     * on its own — a committee member who plays no interclub is never in the
     * export — so it is shown and nothing more. No bulk archiving, no automatic
     * anything.
     */
    it('names the members the listing did not carry, without touching them', function (): void {
        $season = makeActiveSeason();
        $absentee = activeMember($season, ['licence' => '900001', 'first_name' => 'Solange', 'last_name' => 'Absente']);
        $listed = activeMember($season, ['licence' => '166036', 'first_name' => 'Marc', 'last_name' => 'Dupont']);
        $unlicensed = activeMember($season, ['licence' => null, 'first_name' => 'Sans', 'last_name' => 'Licence']);

        Livewire::test(IMPORT_COMPONENT)
            ->set('importFile', importListing([
                '166036;DUPONT MARC;1990-06-05;C2;*;N;N;SE;JO;2020-09-24;marc@example.com;;0475123456;RUE DU TEST;13;1348;LOUVAIN-LA-NEUVE',
            ]))
            ->call('parse')
            ->call('import')
            ->assertSet('step', 3)
            ->assertSee('Absente')
            ->assertDontSee('Sans Licence');

        expect($absentee->fresh()->trashed())->toBeFalse()
            ->and($listed->fresh()->ranking)->toBe(Ranking::C2)
            ->and($unlicensed->fresh())->not->toBeNull();
    });
});

/*
 * Twenty-two of the fifty-eight affiliates are children, and the federation lists
 * a parent's mailbox against each of them. An address is a login: left as it
 * comes, it would hand a child their parent's account for years to come.
 */
describe('the children listed under an adult address', function (): void {

    it('hands the address to the parent and reaches the child through them', function (): void {
        Livewire::test(IMPORT_COMPONENT)
            ->set('importFile', importListing([
                '166040;DECRETON CRISTINA;1984-04-17;NC;NC;N;N;SE;LR;2015-09-01;famille@example.com;;0498112233;RUE DU TEST;30;1348;LOUVAIN-LA-NEUVE',
                '166041;DECRETON CHLOE;2013-05-22;NC;NC;N;N;CA;LR;2024-09-01;famille@example.com;;0498112233;RUE DU TEST;30;1348;LOUVAIN-LA-NEUVE',
            ]))
            ->call('parse')
            ->assertSet('rows.2.keepsEmail', true)
            ->assertSet('rows.3.keepsEmail', false)
            ->assertSet('rows.3.guardianAddress', true)
            ->call('import')
            ->assertSet('step', 3);

        $parent = User::query()->where('licence', '166040')->first();
        $child = User::query()->where('licence', '166041')->first();

        expect($parent->email)->toBe('famille@example.com')
            ->and($child->email)->toBeNull()
            ->and($child->guardians()->first()?->user_id)->toBe($parent->id)
            ->and($child->contactEmail())->toBe('famille@example.com');
    });

    /*
     * The nineteen the file cannot betray: one child, one parental address, no
     * collision to detect. Only the secretary knows, and the box is where they
     * say so.
     */
    it('lets the reviewer say an address belongs to a parent who does not play', function (): void {
        Livewire::test(IMPORT_COMPONENT)
            ->set('importFile', importListing([
                '166042;CARTIAUX PAUL;2014-02-08;NC;NC;N;N;PO;LR;2025-09-01;olivier.cartiaux@example.com;;0470445566;RUE DU TEST;42;1348;LOUVAIN-LA-NEUVE',
            ]))
            ->call('parse')
            ->assertSet('rows.2.isMinor', true)
            ->assertSet('rows.2.guardianAddress', false)
            ->assertSet('rows.2.guardianFirstName', 'Olivier')
            ->assertSet('rows.2.guardianLastName', 'Cartiaux')
            ->set('rows.2.guardianAddress', true)
            ->call('import')
            ->assertSet('step', 3);

        $child = User::query()->where('licence', '166042')->first();
        $guardian = $child->guardians()->first();

        expect($child->email)->toBeNull()
            ->and($child->guardian_phone_number)->toBe('0470445566')
            ->and($guardian?->user_id)->toBeNull()
            ->and($guardian?->first_name)->toBe('Olivier')
            ->and($guardian?->last_name)->toBe('Cartiaux')
            ->and($guardian?->email)->toBe('olivier.cartiaux@example.com')
            ->and($child->contactEmail())->toBe('olivier.cartiaux@example.com');
    });

    it('leaves an adult in charge of their own address', function (): void {
        Livewire::test(IMPORT_COMPONENT)
            ->set('importFile', importListing([
                '166043;LEGRAND ANNE;1985-03-02;D4;D4;N;N;SE;LR;2021-09-01;anne@example.com;;0475987654;RUE DU TEST;15;1348;LOUVAIN-LA-NEUVE',
            ]))
            ->call('parse')
            ->assertSet('rows.2.isMinor', false)
            ->assertSet('rows.2.guardianAddress', false)
            ->call('import');

        $member = User::query()->where('licence', '166043')->first();

        expect($member->email)->toBe('anne@example.com')
            ->and($member->phone_number)->toBe('0475987654')
            ->and($member->guardians()->count())->toBe(0);
    });
});

/*
 * The federation lists one mailbox per household, so a child arrives carrying
 * their parent's. When that parent is already on the roster — the ordinary case,
 * since they play too — the address used to hand the child their parent's file:
 * the parent was proposed for update, the child was never created, and the
 * parent's licence was overwritten with their child's.
 */
describe('importing a child whose guardian is already a member', function (): void {

    it('creates the child instead of proposing their parent for update', function (): void {
        $marie = User::factory()->create([
            'licence' => '111111',
            'ranking' => 'D4',
            'email' => 'famille.lambert@example.com',
            'first_name' => 'Marie',
            'last_name' => 'Lambert',
            'birthdate' => '1982-09-14',
        ]);

        Livewire::test(IMPORT_COMPONENT)
            ->set('importFile', importListing([
                '111111;LAMBERT MARIE;1982-09-14;D4;D4;N;N;SE;LR;2021-09-01;famille.lambert@example.com;;0475987654;RUE DU TEST;15;1348;LOUVAIN-LA-NEUVE',
                '166044;DUPONT LOUIS;2016-03-04;D6;;N;N;SE;JO;2024-09-01;famille.lambert@example.com;;0475987654;RUE DU TEST;15;1348;LOUVAIN-LA-NEUVE',
            ]))
            ->call('parse')
            ->assertSet('rows.3.outcome', 'new')
            ->assertSet('rows.3.action', 'create')
            ->call('import');

        $louis = User::query()->where('licence', '166044')->first();

        expect($louis)->not->toBeNull()
            ->and($louis->first_name)->toBe('Louis')
            ->and($louis->last_name)->toBe('Dupont')
            // The address is her login and stays hers; he is reached through her.
            ->and($louis->email)->toBeNull()
            ->and($louis->guardians()->first()?->user_id)->toBe($marie->id)
            ->and($louis->contactEmail())->toBe('famille.lambert@example.com');

        // The other half of the bug: his line used to be written onto her file.
        expect($marie->fresh()->licence)->toBe('111111')
            ->and($marie->fresh()->ranking)->toBe(Ranking::D4)
            ->and($marie->fresh()->first_name)->toBe('Marie');
    });

    /*
     * The same address, on a parent who is not in this listing at all — they let
     * their affiliation lapse, or never played. Nothing names them as a guardian
     * here, so the child arrives without one and is linked by hand; what matters
     * is that he arrives.
     */
    it('creates the child even when the parent is absent from the listing', function (): void {
        $marie = User::factory()->create([
            'licence' => '111111',
            'email' => 'famille.lambert@example.com',
            'first_name' => 'Marie',
            'last_name' => 'Lambert',
            'birthdate' => '1982-09-14',
        ]);

        Livewire::test(IMPORT_COMPONENT)
            ->set('importFile', importListing([
                '166045;DUPONT LOUIS;2016-03-04;D6;;N;N;SE;JO;2024-09-01;famille.lambert@example.com;;0475987654;RUE DU TEST;15;1348;LOUVAIN-LA-NEUVE',
            ]))
            ->call('parse')
            ->assertSet('rows.2.action', 'create')
            ->call('import');

        $louis = User::query()->where('licence', '166045')->first();

        expect($louis)->not->toBeNull()
            // `unlessTaken()` refuses him an address another member already holds.
            ->and($louis->email)->toBeNull();

        expect($marie->fresh()->licence)->toBe('111111');
    });
});

/*
 * The listing is exported once a season and the club barely moves between two of
 * them: most of what it carries is a member the club already holds, in the state
 * the club already holds them. Those lines used to be laid out card by card,
 * every minor among them demanding an answer that had been given the year before.
 * They are now recognised, folded away, and written off.
 */
describe('the affiliates the listing has nothing new to say about', function (): void {

    /*
     * The scenario in one test: the same file, twice. Whatever the first run
     * wrote, the second must find nothing left to do — which is also the only
     * honest way to build a member identical to a row, mutators and casts
     * included.
     */
    it('asks nothing of the file it has just written', function (): void {
        $line = '166036;DUPONT MARC;1990-06-05;C2;*;N;N;SE;JO;2020-09-24;marc@example.com;;0475123456;RUE DU TEST;13;1348;LOUVAIN-LA-NEUVE';

        Livewire::test(IMPORT_COMPONENT)
            ->set('importFile', importListing([$line]))
            ->call('parse')
            ->call('import')
            ->assertSet('step', 3);

        Livewire::test(IMPORT_COMPONENT)
            ->set('importFile', importListing([$line]))
            ->call('parse')
            ->assertSet('rows.2.unchanged', true)
            ->assertSet('rows.2.needsReview', false)
            ->assertSet('rows.2.action', 'unchanged')
            ->assertSet('tally.unchanged', 1)
            ->assertSet('tally.update', 0);
    });

    /*
     * A ranking moves every season, and that alone is an import. The point of the
     * fold is to hide what has nothing to say, never to swallow something that has.
     */
    it('takes a line back out of the fold when a single field moves', function (): void {
        Livewire::test(IMPORT_COMPONENT)
            ->set('importFile', importListing([
                '166036;DUPONT MARC;1990-06-05;C2;*;N;N;SE;JO;2020-09-24;marc@example.com;;0475123456;RUE DU TEST;13;1348;LOUVAIN-LA-NEUVE',
            ]))
            ->call('parse')
            ->call('import');

        Livewire::test(IMPORT_COMPONENT)
            ->set('importFile', importListing([
                '166036;DUPONT MARC;1990-06-05;B6;*;N;N;SE;JO;2020-09-24;marc@example.com;;0475123456;RUE DU TEST;13;1348;LOUVAIN-LA-NEUVE',
            ]))
            ->call('parse')
            ->assertSet('rows.2.unchanged', false)
            ->assertSet('rows.2.action', 'update')
            ->assertSet('tally.unchanged', 0);
    });

    /*
     * Whose address is this? — the question every minor raises, and the one that
     * made the screen unusable: twenty-two children re-asked every August. It is a
     * question only until it has been answered, and the answer is on file.
     */
    it('stops asking about a child once a guardian answers for them', function (): void {
        $line = '166042;CARTIAUX PAUL;2014-02-08;NC;NC;N;N;PO;LR;2025-09-01;olivier.cartiaux@example.com;;0470445566;RUE DU TEST;42;1348;LOUVAIN-LA-NEUVE';

        Livewire::test(IMPORT_COMPONENT)
            ->set('importFile', importListing([$line]))
            ->call('parse')
            ->set('rows.2.guardianAddress', true)
            ->call('import')
            ->assertSet('step', 3);

        expect(User::query()->where('licence', '166042')->first()->guardians()->exists())->toBeTrue();

        Livewire::test(IMPORT_COMPONENT)
            ->set('importFile', importListing([$line]))
            ->call('parse')
            ->assertSet('rows.2.isMinor', true)
            ->assertSet('rows.2.unchanged', true)
            ->assertSet('rows.2.needsReview', false);
    });

    /*
     * The other half of the same rule. A child nobody answers for is a loose end,
     * and folding them away would bury it for good.
     */
    it('keeps asking about a child no guardian answers for', function (): void {
        $line = '166042;CARTIAUX PAUL;2014-02-08;NC;NC;N;N;PO;LR;2025-09-01;olivier.cartiaux@example.com;;0470445566;RUE DU TEST;42;1348;LOUVAIN-LA-NEUVE';

        Livewire::test(IMPORT_COMPONENT)
            ->set('importFile', importListing([$line]))
            ->call('parse')
            ->call('import')
            ->assertSet('step', 3);

        expect(User::query()->where('licence', '166042')->first()->guardians()->exists())->toBeFalse();

        Livewire::test(IMPORT_COMPONENT)
            ->set('importFile', importListing([$line]))
            ->call('parse')
            ->assertSet('rows.2.unchanged', false)
            ->assertSet('rows.2.needsReview', true);
    });

    /*
     * The whole point of the fold. Alpine's version keeps the cards in the page
     * and only hides them, which on a listing of two hundred is two hundred cards
     * rebuilt every time an action is picked elsewhere. Closed, these are not
     * rendered at all.
     */
    it('does not build the folded cards until they are asked for', function (): void {
        $line = '166036;DUPONT MARC;1990-06-05;C2;*;N;N;SE;JO;2020-09-24;marc@example.com;;0475123456;RUE DU TEST;13;1348;LOUVAIN-LA-NEUVE';

        Livewire::test(IMPORT_COMPONENT)
            ->set('importFile', importListing([$line]))
            ->call('parse')
            ->call('import');

        Livewire::test(IMPORT_COMPONENT)
            ->set('importFile', importListing([$line]))
            ->call('parse')
            ->assertSee(__('Already up to date'))
            ->assertDontSeeHtml('unchanged-2')
            ->call('toggleUnchanged')
            ->assertSeeHtml('unchanged-2')
            ->assertSee('Dupont');
    });

    /*
     * "Nothing changed" is a claim about the file. The secretary may know
     * something the file does not, so the way out stays open — and the line does
     * not move when they take it, because a section that reshuffles itself hands
     * the next click to the wrong affiliate.
     */
    it('lets the reviewer write a line the file called unchanged', function (): void {
        $line = '166036;DUPONT MARC;1990-06-05;C2;*;N;N;SE;JO;2020-09-24;marc@example.com;;0475123456;RUE DU TEST;13;1348;LOUVAIN-LA-NEUVE';

        Livewire::test(IMPORT_COMPONENT)
            ->set('importFile', importListing([$line]))
            ->call('parse')
            ->call('import');

        Livewire::test(IMPORT_COMPONENT)
            ->set('importFile', importListing([$line]))
            ->call('parse')
            ->call('forceUpdate', 2)
            ->assertSet('rows.2.action', 'update')
            ->assertSet('rows.2.unchanged', true)
            ->assertSet('tally.update', 1)
            ->assertSet('tally.unchanged', 0)
            ->call('import')
            ->assertSet('step', 3);

        expect(MemberImport::query()->latest('id')->first()->updated_count)->toBe(1);
    });
});

/*
 * Fifty-eight affiliates answered one select at a time is the other half of what
 * made the screen unusable. The sections are what a bulk action applies to — and
 * the line the screen refuses to cross is that none of them ever writes a line
 * the matcher would not commit to.
 */
describe('answering a whole section at once', function (): void {

    /*
     * A namesake with a different birthdate, next to an affiliate nobody knows.
     * The first holds the import back; the second is not the bulk action's
     * business.
     */
    it('sets the undecided lines aside without touching the settled ones', function (): void {
        User::factory()->create([
            'first_name' => 'Marc',
            'last_name' => 'Dupont',
            'birthdate' => '1980-01-01',
            'email' => 'autre@example.com',
            'licence' => null,
        ]);

        Livewire::test(IMPORT_COMPONENT)
            ->set('importFile', importListing([
                '166036;DUPONT MARC;1990-06-05;C2;*;N;N;SE;JO;2020-09-24;marc@example.com;;0475123456;RUE DU TEST;13;1348;LOUVAIN-LA-NEUVE',
                '166037;LEGRAND ANNE;1985-03-02;D4;D4;N;N;SE;LR;2021-09-01;anne@example.com;;0475987654;RUE DU TEST;15;1348;LOUVAIN-LA-NEUVE',
            ]))
            ->call('parse')
            ->assertSet('rows.2.outcome', 'suspect')
            ->assertSet('rows.2.action', '')
            ->assertSet('tally.undecided', 1)
            ->call('skipUndecided')
            ->assertSet('rows.2.action', 'skip')
            ->assertSet('rows.3.action', 'create')
            ->assertSet('tally.undecided', 0)
            ->call('import')
            ->assertSet('step', 3);

        // The namesake was set aside, never written over.
        expect(User::query()->where('licence', '166036')->exists())->toBeFalse()
            ->and(User::query()->where('licence', '166037')->exists())->toBeTrue();
    });

    it('sets aside every line it was ready to write, and takes it back', function (): void {
        Livewire::test(IMPORT_COMPONENT)
            ->set('importFile', importListing([
                '166036;DUPONT MARC;1990-06-05;C2;*;N;N;SE;JO;2020-09-24;marc@example.com;;0475123456;RUE DU TEST;13;1348;LOUVAIN-LA-NEUVE',
                '166037;LEGRAND ANNE;1985-03-02;D4;D4;N;N;SE;LR;2021-09-01;anne@example.com;;0475987654;RUE DU TEST;15;1348;LOUVAIN-LA-NEUVE',
            ]))
            ->call('parse')
            ->assertSet('tally.create', 2)
            ->call('skipReady')
            ->assertSet('tally.create', 0)
            ->assertSet('tally.skip', 2)
            ->call('restoreReady')
            ->assertSet('tally.create', 2)
            ->assertSet('tally.skip', 0);
    });

    /*
     * Safe by construction: these lines were filed as unchanged because nothing
     * would move, so forcing them writes the date the federation was last read
     * and nothing else. It is how the club says "the listing still carries them".
     */
    it('writes the already up to date lines when asked, and only their sync date', function (): void {
        $line = '166036;DUPONT MARC;1990-06-05;C2;*;N;N;SE;JO;2020-09-24;marc@example.com;;0475123456;RUE DU TEST;13;1348;LOUVAIN-LA-NEUVE';

        Livewire::test(IMPORT_COMPONENT)
            ->set('importFile', importListing([$line]))
            ->call('parse')
            ->call('import');

        $member = User::query()->where('licence', '166036')->first();
        $member->forceFill(['federation_synced_at' => CarbonImmutable::parse('2025-08-14 10:00:00')])->save();
        $before = $member->fresh();

        Livewire::test(IMPORT_COMPONENT)
            ->set('importFile', importListing([$line]))
            ->call('parse')
            ->assertSet('tally.unchanged', 1)
            ->call('updateUnchanged')
            ->assertSet('tally.update', 1)
            ->assertSet('tally.unchanged', 0)
            ->call('releaseUnchanged')
            ->assertSet('tally.unchanged', 1)
            ->call('updateUnchanged')
            ->call('import')
            ->assertSet('step', 3);

        $after = $member->fresh();

        expect($after->federation_synced_at->greaterThan($before->federation_synced_at))->toBeTrue()
            ->and($after->ranking)->toBe($before->ranking)
            ->and($after->email)->toBe($before->email)
            ->and($after->street)->toBe($before->street)
            ->and(MemberImport::query()->latest('id')->first()->updated_count)->toBe(1);
    });
});
