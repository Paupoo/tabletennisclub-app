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
     * The lines nobody has to look at: the roster and the listing agree, and the
     * parser read them without guessing.
     *
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function linesReadToImport(): array
    {
        return array_filter($this->rows, static fn (array $row): bool => ! $row['needsReview']);
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

    public function render(): mixed
    {
        return $this->view([
            'breadcrumbs' => $this->getBreadcrumbs(),
        ]);
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
            'skip' => count(array_keys($actions, ImportLineAction::SKIP->value, true)),
            'undecided' => $this->undecidedCount(),
        ];
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
                street: $row['street'],
                cityCode: $row['cityCode'],
                cityName: $row['cityName'],
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
            'needsReview' => $this->proposedAction($match->outcome) === ''
                || $row->needsNameReview
                || $row->needsAddressReview
                || $minor,
            'outcome' => $match->outcome->value,
            'existingUserId' => $match->existing?->id,
            'existingLabel' => $match->existing?->full_name,
            'discrepancies' => $match->discrepancies,
            'action' => $this->proposedAction($match->outcome),
            'keepsEmail' => $decision?->keepsEmail ?? true,
            'isMinor' => $minor,
            // Ticked where two affiliates were listed under one address, since the
            // file proved it there. Everywhere else it is only offered: a child
            // alone on a parent's address looks exactly like an adult on their own,
            // and no rule tells them apart. The secretary knows the families.
            'guardianAddress' => $minor && $decision !== null,
            'guardianLineNumber' => $decision?->guardianLineNumber,
            'guardianFirstName' => $guardianName['firstName'],
            'guardianLastName' => $guardianName['lastName'],
        ];
    }
};
