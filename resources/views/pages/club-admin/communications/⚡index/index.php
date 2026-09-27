<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Communications\Data\Audience;
use App\Domains\ClubAdmin\Communications\Data\AudienceCriteria;
use App\Domains\ClubAdmin\Communications\Services\AudienceBuilder;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Shared\Enums\AudienceAgeBand;
use App\Domains\Shared\Enums\AudienceBase;
use App\Domains\Shared\Enums\AudienceLicence;
use App\Domains\Shared\Enums\Gender;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

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
 */
new class extends Component
{
    use HasBreadcrumbs;

    /** How many addresses one mailto link carries: longer links get cut by some clients. */
    public const int MAILTO_BATCH_SIZE = 50;

    /** @var list<string> */
    public array $ageBands = [];

    public string $base = AudienceBase::Active->value;

    /** @var list<int> */
    public array $excludedUserIds = [];

    /** @var list<string> */
    public array $genders = [];

    /** @var list<int> */
    public array $includedUnclassifiedIds = [];

    /** @var list<string> */
    public array $licences = [];

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
        return new AudienceCriteria(
            base: AudienceBase::tryFrom($this->base) ?? AudienceBase::Active,
            licences: array_values(array_filter(array_map(AudienceLicence::tryFrom(...), $this->licences))),
            genders: array_values(array_filter(array_map(Gender::tryFrom(...), $this->genders))),
            ageBands: array_values(array_filter(array_map(AudienceAgeBand::tryFrom(...), $this->ageBands))),
            excludedUserIds: $this->excludedUserIds,
            includedUnclassifiedIds: $this->includedUnclassifiedIds,
        );
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

    /**
     * The addresses left the application: say who took them, when, and for
     * which audience. Neither the list nor any message is kept.
     *
     * @param  'copy'|'mailto'  $channel
     */
    public function recordExport(string $channel): void
    {
        $criteria = $this->criteria();

        activity()
            ->causedBy(Auth::user())
            ->event('communication_addresses_exported')
            ->withProperties([
                'channel' => in_array($channel, ['copy', 'mailto'], true) ? $channel : 'copy',
                'address_count' => $this->addressCount,
                'member_count' => $this->audience()->members->count(),
                'criteria' => [
                    'base' => $criteria->base->value,
                    'licences' => array_map(fn (AudienceLicence $licence): string => $licence->value, $criteria->licences),
                    'genders' => array_map(fn (Gender $gender): string => $gender->value, $criteria->genders),
                    'age_bands' => array_map(fn (AudienceAgeBand $band): string => $band->value, $criteria->ageBands),
                    'excluded_user_ids' => $criteria->excludedUserIds,
                    'included_unclassified_ids' => $criteria->includedUnclassifiedIds,
                ],
            ])
            ->log('communication_addresses_exported');
    }

    public function render(): View
    {
        return $this->view()->title(__('Communications'));
    }

    public function toggleExclusion(int $userId): void
    {
        $this->excludedUserIds = $this->toggled($this->excludedUserIds, $userId);
    }

    public function toggleUnclassifiedInclusion(int $userId): void
    {
        $this->includedUnclassifiedIds = $this->toggled($this->includedUnclassifiedIds, $userId);
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
            'genderOptions' => Gender::options(),
            'ageBandOptions' => array_map(fn (AudienceAgeBand $band): array => ['id' => $band->value, 'name' => $band->label()], AudienceAgeBand::cases()),
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Communications'));
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
