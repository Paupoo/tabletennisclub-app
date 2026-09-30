<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Communications\Actions\SendCommunication;
use App\Domains\ClubAdmin\Communications\Actions\SendTestCommunication;
use App\Domains\ClubAdmin\Communications\Data\Audience;
use App\Domains\ClubAdmin\Communications\Data\AudienceCriteria;
use App\Domains\ClubAdmin\Communications\Models\Communication;
use App\Domains\ClubAdmin\Communications\Services\AudienceActivityOptions;
use App\Domains\ClubAdmin\Communications\Services\AudienceBuilder;
use App\Domains\ClubAdmin\Communications\Services\InvitationBlock;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Shared\Enums\AudienceActivityKind;
use App\Domains\Shared\Enums\AudienceActivityMode;
use App\Domains\Shared\Enums\AudienceAgeBand;
use App\Domains\Shared\Enums\AudienceBase;
use App\Domains\Shared\Enums\AudienceFunction;
use App\Domains\Shared\Enums\AudienceLicence;
use App\Domains\Shared\Enums\Gender;
use App\Domains\Shared\Enums\InvitationTarget;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Mary\Traits\Toast;

/**
 * Who a club-wide message reaches, before it is written.
 *
 * Every global mailing used to forget somebody or write to people who had left.
 * The audience is computed from the affiliations by {@see AudienceBuilder}, and
 * the screen shows the members it cannot reach or cannot classify rather than
 * dropping them: that is where the forgotten ones used to hide.
 *
 * The addresses leave in Bcc only, the club's own address in To, in batches a
 * mail client accepts in one link. Taking them out is recorded in the audit
 * trail: it is the members' personal data leaving the application.
 *
 * Or the message is written here, in markdown, and sent one address at a time
 * by {@see SendCommunication}. Opened with `?from=<id>`, the screen starts
 * from a past communication — its text and its filters — so next season's
 * reaffiliation call is the last one, edited.
 */
new class extends Component
{
    use HasBreadcrumbs, Toast;

    /** How many addresses one mailto link carries: longer links get cut by some clients. */
    public const int MAILTO_BATCH_SIZE = 50;

    public ?int $activityId = null;

    public string $activityKind = '';

    public string $activityMode = AudienceActivityMode::Registered->value;

    /** @var list<string> */
    public array $ageBands = [];

    public string $base = AudienceBase::Active->value;

    public string $body = '';

    /** @var list<int> */
    public array $excludedUserIds = [];

    /**
     * Coaches, captains: read among the active members only, and dropped as
     * soon as the audience starts from anyone else.
     *
     * @var list<string>
     */
    public array $functions = [];

    /** @var list<string> */
    public array $genders = [];

    /** @var list<int> */
    public array $includedUnclassifiedIds = [];

    public ?int $invitationId = null;

    public string $invitationTarget = '';

    /** @var list<string> */
    public array $licences = [];

    public ?string $replyTo = null;

    public string $subject = '';

    /** Whether the reminder mode makes sense for the chosen activity. */
    public function activityIsInvitable(): bool
    {
        return AudienceActivityKind::tryFrom($this->activityKind)?->isInvitable() ?? false;
    }

    /** @return list<array{id: int, name: string}> */
    #[Computed]
    public function activityOptions(): array
    {
        $kind = AudienceActivityKind::tryFrom($this->activityKind);

        return $kind === null ? [] : app(AudienceActivityOptions::class)->for($kind);
    }

    #[Computed]
    public function addressCount(): int
    {
        return count($this->audience()->addresses());
    }

    #[Computed]
    public function audience(): Audience
    {
        return app(AudienceBuilder::class)->build($this->criteria());
    }

    public function criteria(): AudienceCriteria
    {
        return AudienceCriteria::fromArray([
            'base' => $this->base,
            'licences' => $this->licences,
            'genders' => $this->genders,
            'age_bands' => $this->ageBands,
            'excluded_user_ids' => $this->excludedUserIds,
            'included_unclassified_ids' => $this->includedUnclassifiedIds,
            'activity' => $this->activityKind !== '' && $this->activityId !== null ? [
                'kind' => $this->activityKind,
                'id' => $this->activityId,
                'mode' => $this->activityMode,
            ] : null,
            'functions' => $this->functions,
        ]);
    }

    /** @return Collection<int, User> */
    #[Computed]
    public function excludedMembers(): Collection
    {
        return User::query()
            ->whereIn('id', $this->excludedUserIds)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->get();
    }

    /** Whether the functions can narrow the audience: only the active members hold one. */
    public function functionsApply(): bool
    {
        return $this->base === AudienceBase::Active->value;
    }

    /** Appends the chosen "invite to…" block to the message. */
    public function insertInvitation(): void
    {
        $target = InvitationTarget::tryFrom($this->invitationTarget);

        if ($target === null || $this->invitationId === null) {
            return;
        }

        $block = app(InvitationBlock::class)->markdown($target, $this->invitationId);

        $this->body = rtrim($this->body) === '' ? $block : rtrim($this->body) . "\n\n" . $block;
        $this->reset('invitationId');
    }

    /** @return list<array{id: int, name: string}> */
    #[Computed]
    public function invitationOptions(): array
    {
        $target = InvitationTarget::tryFrom($this->invitationTarget);

        return $target === null ? [] : app(InvitationBlock::class)->options($target);
    }

    /**
     * Links that open the mail client with the club in To and a batch of
     * members in Bcc — never in To, where every member would read the others.
     *
     * @return list<string>
     */
    public function mailtoBatches(): array
    {
        $club = Club::own()?->email_contact ?? (string) config('mail.from.address');

        return collect($this->audience()->addresses())
            ->chunk(self::MAILTO_BATCH_SIZE)
            ->map(fn ($batch): string => 'mailto:' . $club . '?bcc=' . implode(',', $batch->all()))
            ->values()
            ->all();
    }

    public function mount(): void
    {
        $this->replyTo = Auth::user()?->contactEmail();

        $from = request()->integer('from');
        $past = $from > 0 ? Communication::find($from) : null;

        if ($past === null) {
            return;
        }

        $criteria = AudienceCriteria::fromArray($past->criteria)->toArray();

        $this->subject = $past->subject;
        $this->body = $past->body;
        $this->replyTo = $past->reply_to ?? $this->replyTo;
        $this->base = $criteria['base'];
        $this->licences = $criteria['licences'];
        $this->genders = $criteria['genders'];
        $this->ageBands = $criteria['age_bands'];
        $this->activityKind = $criteria['activity']['kind'] ?? '';
        $this->activityId = $criteria['activity']['id'] ?? null;
        $this->activityMode = $criteria['activity']['mode'] ?? AudienceActivityMode::Registered->value;
        $this->functions = $criteria['functions'];
    }

    /**
     * The addresses left the application: say who took them, when, and for
     * which audience. Neither the list nor any message is kept.
     *
     * @param  'copy'|'mailto'  $channel
     */
    public function recordExport(string $channel): void
    {
        activity()
            ->causedBy(Auth::user())
            ->event('communication_addresses_exported')
            ->withProperties([
                'channel' => in_array($channel, ['copy', 'mailto'], true) ? $channel : 'copy',
                'address_count' => $this->addressCount,
                'member_count' => $this->audience()->members->count(),
                'criteria' => $this->criteria()->toArray(),
            ])
            ->log('communication_addresses_exported');
    }

    public function render(): View
    {
        return $this->view()->title(__('Communications'));
    }

    public function send(): void
    {
        $this->validate($this->rules());

        if ($this->addressCount === 0) {
            $this->error(__('Nobody to write to with these filters.'));

            return;
        }

        $communication = app(SendCommunication::class)(
            author: Auth::user(),
            criteria: $this->criteria(),
            subject: $this->subject,
            body: $this->body,
            replyTo: $this->replyTo,
        );

        $this->redirectRoute('admin.communications.show', $communication, navigate: true);
    }

    public function sendTest(): void
    {
        $this->validate($this->rules());

        app(SendTestCommunication::class)(Auth::user(), $this->subject, $this->body, $this->replyTo);

        $this->success(__('Test sent to :email.', ['email' => (string) Auth::user()?->contactEmail()]));
    }

    public function toggleExclusion(int $userId): void
    {
        $this->excludedUserIds = $this->toggled($this->excludedUserIds, $userId);
    }

    public function toggleUnclassifiedInclusion(int $userId): void
    {
        $this->includedUnclassifiedIds = $this->toggled($this->includedUnclassifiedIds, $userId);
    }

    public function updatedActivityKind(): void
    {
        $this->reset('activityId', 'activityMode');
    }

    public function updatedBase(): void
    {
        if (! $this->functionsApply()) {
            $this->reset('functions');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'baseOptions' => array_map(fn (AudienceBase $base): array => ['id' => $base->value, 'name' => $base->label()], AudienceBase::cases()),
            'licenceOptions' => array_map(fn (AudienceLicence $licence): array => ['id' => $licence->value, 'name' => $licence->label()], AudienceLicence::cases()),
            'functionOptions' => array_map(fn (AudienceFunction $function): array => ['id' => $function->value, 'name' => $function->label()], [AudienceFunction::Coaches, AudienceFunction::Captains]),
            'genderOptions' => Gender::options(),
            'ageBandOptions' => array_map(fn (AudienceAgeBand $band): array => ['id' => $band->value, 'name' => $band->label(), 'hint' => $band->hint()], AudienceAgeBand::cases()),
            'activityKindOptions' => array_map(fn (AudienceActivityKind $kind): array => ['id' => $kind->value, 'name' => $kind->label()], AudienceActivityKind::cases()),
            'activityModeOptions' => array_map(fn (AudienceActivityMode $mode): array => ['id' => $mode->value, 'name' => $mode->label()], [AudienceActivityMode::Registered, AudienceActivityMode::NotInvited, AudienceActivityMode::InvitedNotRegistered]),
            'invitationTargetOptions' => array_map(fn (InvitationTarget $target): array => ['id' => $target->value, 'name' => $target->label()], InvitationTarget::cases()),
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Communications'));
    }

    /** @return array<string, list<string>> */
    private function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:20000'],
            'replyTo' => ['nullable', 'email'],
        ];
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function toggled(array $ids, int $id): array
    {
        return in_array($id, $ids, true)
            ? array_values(array_diff($ids, [$id]))
            : [...$ids, $id];
    }
};
