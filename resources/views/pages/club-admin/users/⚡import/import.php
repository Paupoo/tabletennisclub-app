<?php

declare(strict_types=1);

use App\Actions\User\ImportFederationMembersAction;
use App\Data\User\FederationRow;
use App\Data\User\ImportLine;
use App\Data\User\MemberMatch;
use App\Data\User\SharedAddressDecision;
use App\Domains\ClubAdmin\Users\Models\MemberImport;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\Gender;
use App\Domains\Shared\Enums\ImportLineAction;
use App\Domains\Shared\Enums\MemberMatchOutcome;
use App\Domains\Shared\Enums\Permission;
use App\Domains\Shared\Support\AddressNormalizer;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Services\ClubAdmin\Users\FederationListingParser;
use App\Services\ClubAdmin\Users\FederationMemberMatcher;
use App\Services\ClubAdmin\Users\SharedAddressResolver;
use App\Support\Breadcrumb;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

/**
 * Seeding the club roster from the federation's affiliate listing.
 *
 * The screen is where the file stops being data and becomes a decision. Nothing
 * is written until a human has been through every line: the matcher can only
 * propose, and the two things it cannot settle — a namesake and a member who was
 * archived — are handed over undecided, which holds the import back until they
 * are answered.
 */
new class extends Component
{
    use HasBreadcrumbs, Toast, WithFileUploads;

    /**
     * Lines the parser could not read, carried to the import history as a line
     * number and a reason — never as what the line held.
     *
     * @var array<int, array{line: int, reason: string}>
     */
    public array $failures = [];

    public mixed $importFile = null;

    public ?int $importId = null;

    /**
     * The listing, as the reviewer is leaving it. Keyed by the line the affiliate
     * sits on in the file, which is also how a child names their guardian.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $rows = [];

    /**
     * Whether the reviewer asked to see the affiliates the listing had nothing
     * new to say about.
     *
     * Server-side rather than a class toggle: on a listing of two hundred, the
     * unchanged are the bulk of it, and cards nobody asked for cost a render
     * apiece every time an action is picked elsewhere on the screen. Folded
     * away, they are not built at all.
     */
    public bool $showUnchanged = false;

    public int $step = 1;

    /**
     * Members the club holds and the listing did not carry.
     *
     * Shown, and nothing else. Absence from the export proves nothing on its own:
     * a committee member who plays no interclub has never been in it. Restricted
     * to licensed members affiliated this season or the one before, because the
     * roster holds years of former members and a list of two hundred names is a
     * list nobody reads.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function absentees(): Collection
    {
        $listed = array_filter(array_column($this->rows, 'licence'));
        $seasons = Season::query()->orderByDesc('start_at')->limit(2)->pluck('id');

        return User::query()
            ->whereNotNull('licence')
            ->when($listed !== [], fn (Builder $query): Builder => $query->whereNotIn('licence', $listed))
            ->whereHas('subscriptions', fn (Builder $query): Builder => $query->whereIn('season_id', $seasons))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * What may be done with a line, given what the roster answered.
     *
     * A member who is only archived still holds the licence, which is unique:
     * creating a second file for them would fail on the constraint, so the choice
     * is between taking their file back and leaving them be.
     *
     * An unchanged line is not offered here at all: it carries no select, only
     * a way to force it open, because there is nothing to choose between when
     * every field already agrees.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public function actionOptions(string $outcome): array
    {
        $create = ['id' => ImportLineAction::CREATE->value, 'name' => __('Create')];
        $update = ['id' => ImportLineAction::UPDATE->value, 'name' => __('Update the existing member')];
        $skip = ['id' => ImportLineAction::SKIP->value, 'name' => __('Ignore')];

        return match (MemberMatchOutcome::from($outcome)) {
            MemberMatchOutcome::NEW => [$create, $skip],
            MemberMatchOutcome::MATCHED => [$update, $skip],
            MemberMatchOutcome::ARCHIVED => [
                ['id' => ImportLineAction::UPDATE->value, 'name' => __('Restore the archived member')],
                $skip,
            ],
            MemberMatchOutcome::SUSPECT => [$create, $update, $skip],
        };
    }

    /**
     * Write a line the screen had classed as already up to date after all.
     *
     * The line does not move: it was filed under the unchanged when the file was
     * read and it stays there, marked. Sections that reshuffle themselves under
     * the pointer hand the next click to the wrong affiliate, which is why the
     * filing is settled once and never recomputed.
     */
    public function forceUpdate(int $line): void
    {
        if (($this->rows[$line]['unchanged'] ?? false) === true) {
            $this->rows[$line]['action'] = ImportLineAction::UPDATE->value;
        }
    }

    /**
     * Write the reviewed listing into the roster.
     *
     * Refuses while a line is still undecided rather than falling back on a
     * default: the whole point of the review is that these lines have no
     * defensible default.
     */
    public function import(): void
    {
        Gate::authorize(Permission::UsersImport->value);

        if ($this->undecidedCount() > 0) {
            $this->error(__('Some lines still need a decision.'));

            return;
        }

        $import = ImportFederationMembersAction::handle(
            array_map($this->importLine(...), array_values($this->rows)),
            Auth::user(),
            $this->failures,
        );

        $this->importId = $import->id;
        $this->step = 3;

        // The listing has said everything it had to say. It carries the birthdates,
        // addresses and phone numbers of children, so it does not outlive the run
        // that read it — the roster keeps the members, nothing keeps the file.
        $this->importFile?->delete();
        $this->importFile = null;
    }

    /**
     * The run, as the history recorded it.
     */
    #[Computed]
    public function importRun(): ?MemberImport
    {
        return $this->importId === null ? null : MemberImport::find($this->importId);
    }

    /**
     * The lines nobody has to look at, but which still have something to write:
     * an affiliate the club does not hold, or one whose file the listing moves.
     *
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function linesReadToImport(): array
    {
        return array_filter(
            $this->rows,
            static fn (array $row): bool => ! $row['needsReview'] && ! $row['unchanged'],
        );
    }

    /**
     * The lines that ask the reviewer something.
     *
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function linesToReview(): array
    {
        return array_filter($this->rows, static fn (array $row): bool => $row['needsReview']);
    }

    /**
     * The lines the listing had nothing to say about.
     *
     * The bulk of a yearly export, and the reason the screen used to be unusable:
     * a member the club already holds, in the state the club already holds them.
     * Nothing is written for them, and nothing is asked.
     *
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function linesUnchanged(): array
    {
        return array_filter($this->rows, static fn (array $row): bool => $row['unchanged']);
    }

    public function parse(): void
    {
        Gate::authorize(Permission::UsersImport->value);

        $this->validate([
            'importFile' => 'required|file|mimes:csv,txt',
        ]);

        $listing = (new FederationListingParser)->parse(
            (string) file_get_contents($this->importFile->getRealPath()),
        );

        $decisions = (new SharedAddressResolver)->resolve($listing->rows);
        $matcher = new FederationMemberMatcher;
        $rows = [];

        foreach ($listing->rows as $row) {
            $rows[$row->lineNumber] = $this->reviewRow(
                $matcher->match($row),
                $decisions[$row->lineNumber] ?? null,
            );
        }

        $this->rows = $rows;
        $this->failures = $listing->failures;
        $this->step = 2;
    }

    /**
     * Put the already-up-to-date lines back to writing nothing.
     */
    public function releaseUnchanged(): void
    {
        $this->setActionWhere(
            static fn (array $row): bool => $row['unchanged'],
            static fn (): string => ImportLineAction::UNCHANGED->value,
        );
    }

    /**
     * Take back a line that was forced open.
     */
    public function releaseUpdate(int $line): void
    {
        if (($this->rows[$line]['unchanged'] ?? false) === true) {
            $this->rows[$line]['action'] = ImportLineAction::UNCHANGED->value;
        }
    }

    public function render(): mixed
    {
        return $this->view([
            'breadcrumbs' => $this->getBreadcrumbs(),
        ]);
    }

    /**
     * Hand a whole section back to what the screen had proposed for it.
     *
     * The counterpart of {@see skipReady()}: a bulk action nobody can undo is a
     * bulk action nobody dares press.
     */
    public function restoreReady(): void
    {
        $this->setActionWhere(
            static fn (array $row): bool => ! $row['needsReview'] && ! $row['unchanged'],
            fn (array $row): string => $this->proposedAction(MemberMatchOutcome::from($row['outcome'])),
        );
    }

    /**
     * Set aside every line the screen was ready to write.
     *
     * For the run where only the newcomers are wanted, or where the listing is
     * being read to see what it says before letting it near the roster.
     */
    public function skipReady(): void
    {
        $this->setActionWhere(
            static fn (array $row): bool => ! $row['needsReview'] && ! $row['unchanged'],
            static fn (): string => ImportLineAction::SKIP->value,
        );
    }

    /**
     * Set aside the lines nobody has answered for.
     *
     * The one bulk action the screen needs, because those lines are what holds
     * the import back. It only ever sets aside: a namesake and an archived
     * member are exactly the two answers the matcher would not commit to, and
     * writing them in bulk is how the federation's data lands on somebody else's
     * file. Setting a line aside costs the club a line; writing it onto the
     * wrong person costs them a member.
     */
    public function skipUndecided(): void
    {
        $this->setActionWhere(
            static fn (array $row): bool => $row['action'] === '',
            static fn (): string => ImportLineAction::SKIP->value,
        );
    }

    /**
     * How many lines are heading each way, the undecided ones included.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function tally(): array
    {
        $actions = array_column($this->rows, 'action');

        return [
            'create' => count(array_keys($actions, ImportLineAction::CREATE->value, true)),
            'update' => count(array_keys($actions, ImportLineAction::UPDATE->value, true)),
            'unchanged' => count(array_keys($actions, ImportLineAction::UNCHANGED->value, true)),
            'skip' => count(array_keys($actions, ImportLineAction::SKIP->value, true)),
            'undecided' => $this->undecidedCount(),
        ];
    }

    /**
     * Show or fold away the affiliates that are already up to date.
     */
    public function toggleUnchanged(): void
    {
        $this->showUnchanged = ! $this->showUnchanged;
    }

    /**
     * How many lines nobody has answered for yet.
     */
    public function undecidedCount(): int
    {
        return count(array_filter(
            $this->rows,
            static fn (array $row): bool => $row['action'] === '',
        ));
    }

    /**
     * Keep the address warning honest while the reviewer types.
     *
     * Only the badge moves. Which section a line sits in is settled when the
     * file is read and never recomputed: a line that jumped to another fold the
     * moment it was corrected would shift the grid under the pointer.
     */
    public function updated(string $property): void
    {
        if (preg_match('/^rows\.(\d+)\.(street|cityCode|cityName)$/', $property, $matches) !== 1) {
            return;
        }

        $line = (int) $matches[1];

        $this->rows[$line]['needsAddressReview'] = AddressNormalizer::looksShifted(
            $this->rows[$line]['street'],
            $this->rows[$line]['cityCode'],
            $this->rows[$line]['cityName'],
        );
    }

    /**
     * Write every already-up-to-date line after all.
     *
     * Harmless by construction: these lines were filed here because nothing
     * would change, so the only column that moves is `federation_synced_at`.
     * That is the point — it is how the club says "the listing still carries
     * them" without touching anything else.
     */
    public function updateUnchanged(): void
    {
        $this->setActionWhere(
            static fn (array $row): bool => $row['unchanged'],
            static fn (): string => ImportLineAction::UPDATE->value,
        );
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->users()
            ->current(__('Federation import'));
    }

    /**
     * Who the address might belong to, read off the address itself.
     *
     * `firstname.lastname@…` is the common shape and gives both names; anything
     * else gives none, and the family name of the child is the better guess.
     * This is a suggestion on a form, never something written unconfirmed.
     *
     * @return array{firstName: ?string, lastName: string}
     */
    private function guardianNameFrom(?string $email, string $childLastName): array
    {
        $local = Str::before((string) $email, '@');
        $parts = array_values(array_filter(preg_split('/[._-]+/', $local) ?: []));

        if (count($parts) !== 2) {
            return ['firstName' => null, 'lastName' => $childLastName];
        }

        return [
            'firstName' => mb_convert_case($parts[0], MB_CASE_TITLE),
            'lastName' => mb_convert_case($parts[1], MB_CASE_TITLE),
        ];
    }

    /**
     * The grid line, back in the shape the import action reads.
     *
     * What comes back out is not what the parser produced: the reviewer corrects
     * the names it could only guess at, and those corrections are what gets
     * written.
     *
     * @param  array<string, mixed>  $row
     */
    private function importLine(array $row): ImportLine
    {
        // Asked again here rather than read off the row: this is the last word
        // before anything is written, and a stale flag would let through exactly
        // what it was raised to stop.
        $suspectAddress = AddressNormalizer::looksShifted(
            $row['street'],
            $row['cityCode'],
            $row['cityName'],
        );

        return new ImportLine(
            row: new FederationRow(
                lineNumber: (int) $row['line'],
                licence: (string) $row['licence'],
                lastName: (string) $row['lastName'],
                firstName: (string) $row['firstName'],
                birthdate: $row['birthdate'] === null ? null : CarbonImmutable::parse((string) $row['birthdate']),
                ranking: (string) $row['ranking'],
                gender: Gender::from((string) $row['gender']),
                federationLicenceType: $row['federationLicenceType'],
                email: $row['email'],
                phone: $row['phone'],
                // An address still failing the rule that flagged it is handed
                // over empty, and an empty cell is never written back. The club
                // keeps the address it has rather than having a shifted export
                // laid over it — and a locality landing in a ten-character
                // postcode column never reaches the database, where it would
                // abort the whole run.
                street: $suspectAddress ? null : $row['street'],
                cityCode: $suspectAddress ? null : $row['cityCode'],
                cityName: $suspectAddress ? null : $row['cityName'],
            ),
            action: ImportLineAction::from((string) $row['action']),
            existingUserId: $row['existingUserId'],
            // Ticking the box says the address is a parent's, which settles both
            // questions at once: the child is not given it as a login, and they are
            // reached through whoever holds it.
            keepsEmail: $row['keepsEmail'] && ! $row['guardianAddress'],
            guardianLineNumber: $row['guardianAddress'] ? $row['guardianLineNumber'] : null,
            // Nobody in the listing holds this address, so the parent does not play:
            // the club records them without a member account of their own.
            externalGuardian: $row['guardianAddress'] && $row['guardianLineNumber'] === null,
            guardianFirstName: $row['guardianFirstName'],
            guardianLastName: $row['guardianLastName'],
            guardianEmail: $row['email'],
            guardianPhone: $row['phone'],
        );
    }

    /**
     * Whether this line asks for nothing at all: nothing to write, nothing to
     * ask.
     *
     * Nothing to write is Eloquent's own answer, taken from
     * {@see ImportFederationMembersAction::pendingChanges()} — the values are
     * laid over a copy of the member and the dirty attributes read back — so the
     * screen and the writer cannot drift apart on what an update would do.
     *
     * Nothing to ask is the other half, and it is the half that empties the
     * screen. A name the parser had to guess at, an address that looks shifted:
     * both are questions the file raises regardless of what the roster holds. A
     * minor is a question too — whose address is this? — but only until it has
     * been answered: a child already tied to a guardian was settled last year,
     * and asking again every August is how the screen came to be unusable.
     *
     * Anything short of MATCHED is out of reach by construction. An archived
     * member and a namesake are undecided by design, and a new affiliate has
     * nothing to be unchanged against.
     */
    private function isUnchanged(
        MemberMatch $match,
        ?SharedAddressDecision $decision,
        bool $minor,
        bool $hasGuardian,
    ): bool {
        $existing = $match->existing;

        if ($match->outcome !== MemberMatchOutcome::MATCHED || ! $existing instanceof User) {
            return false;
        }

        if ($match->row->needsNameReview || $match->row->needsAddressReview) {
            return false;
        }

        if ($minor && ! $hasGuardian) {
            return false;
        }

        $line = new ImportLine(
            row: $match->row,
            action: ImportLineAction::UPDATE,
            existingUserId: $existing->id,
            keepsEmail: $this->keepsEmail($decision, $hasGuardian),
        );

        return ImportFederationMembersAction::pendingChanges($line, $existing) === [];
    }

    /**
     * Whether this affiliate may keep the listed address as their own login.
     *
     * A child already reached through a guardian never may. The listing carries
     * the parent's mailbox against them year after year, and the club settled
     * that question the first time it read the file: without this, every import
     * would offer to hand the child their parent's login again, and the line
     * would be a question for as long as the child plays.
     */
    private function keepsEmail(?SharedAddressDecision $decision, bool $hasGuardian): bool
    {
        return ! $hasGuardian && ($decision?->keepsEmail ?? true);
    }

    /**
     * What the matcher's answer means for the import, when it means anything.
     *
     * A namesake and an archived record are questions, not conclusions: they name
     * a candidate the matcher is not sure of, and turning either into an update
     * would write the federation's data onto somebody else's file. They arrive
     * blank, and the import waits.
     */
    private function proposedAction(MemberMatchOutcome $outcome): string
    {
        return match ($outcome) {
            MemberMatchOutcome::NEW => ImportLineAction::CREATE->value,
            MemberMatchOutcome::MATCHED => ImportLineAction::UPDATE->value,
            MemberMatchOutcome::ARCHIVED, MemberMatchOutcome::SUSPECT => '',
        };
    }

    /**
     * One line of the grid: what the file says, what the roster answered, and
     * what will be done about it.
     *
     * @return array<string, mixed>
     */
    private function reviewRow(MemberMatch $match, ?SharedAddressDecision $decision): array
    {
        $row = $match->row;
        $minor = $row->birthdate !== null && $row->birthdate->age < 18;
        $guardianName = $this->guardianNameFrom($row->email, $row->lastName);
        // The answer given the first time this child was read, taken back off the
        // roster instead of being asked for again.
        $hasGuardian = $minor
            && $match->existing instanceof User
            && $match->existing->guardians()->exists();
        $unchanged = $this->isUnchanged($match, $decision, $minor, $hasGuardian);

        return [
            'line' => $row->lineNumber,
            'licence' => $row->licence,
            'lastName' => $row->lastName,
            'firstName' => $row->firstName,
            'birthdate' => $row->birthdate?->toDateString(),
            'ranking' => $row->ranking,
            'gender' => $row->gender->value,
            'federationLicenceType' => $row->federationLicenceType,
            'email' => $row->email,
            'phone' => $row->phone,
            'street' => $row->street,
            'cityCode' => $row->cityCode,
            'cityName' => $row->cityName,
            'needsNameReview' => $row->needsNameReview,
            'needsAddressReview' => $row->needsAddressReview,
            // Settled once, when the file is read, and never recomputed: a line that
            // moved to the other section the moment it was answered would shift the
            // grid under the pointer and hand the next click to the wrong affiliate.
            'needsReview' => ! $unchanged && (
                $this->proposedAction($match->outcome) === ''
                || $row->needsNameReview
                || $row->needsAddressReview
                || $minor
            ),
            'unchanged' => $unchanged,
            'outcome' => $match->outcome->value,
            'existingUserId' => $match->existing?->id,
            'existingLabel' => $match->existing?->full_name,
            'discrepancies' => $match->discrepancies,
            'action' => $unchanged
                ? ImportLineAction::UNCHANGED->value
                : $this->proposedAction($match->outcome),
            'keepsEmail' => $this->keepsEmail($decision, $hasGuardian),
            'isMinor' => $minor,
            // Ticked where two affiliates were listed under one address, since the
            // file proved it there — and ticked again for a child the club already
            // reaches through a guardian, which is the same answer given a year
            // earlier. Everywhere else it is only offered: a child alone on a
            // parent's address looks exactly like an adult on their own, and no
            // rule tells them apart. The secretary knows the families.
            'guardianAddress' => $hasGuardian || ($minor && $decision !== null),
            'guardianLineNumber' => $decision?->guardianLineNumber,
            'guardianFirstName' => $guardianName['firstName'],
            'guardianLastName' => $guardianName['lastName'],
        ];
    }

    /**
     * Give every line the section describes the same action.
     *
     * @param  callable(array<string, mixed>): bool  $matches
     * @param  callable(array<string, mixed>): string  $action
     */
    private function setActionWhere(callable $matches, callable $action): void
    {
        foreach ($this->rows as $line => $row) {
            if ($matches($row)) {
                $this->rows[$line]['action'] = $action($row);
            }
        }
    }
};
