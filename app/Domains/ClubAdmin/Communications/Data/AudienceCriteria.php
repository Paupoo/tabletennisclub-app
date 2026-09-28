<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Communications\Data;

use App\Domains\Shared\Enums\AudienceActivityKind;
use App\Domains\Shared\Enums\AudienceActivityMode;
use App\Domains\Shared\Enums\AudienceAgeBand;
use App\Domains\Shared\Enums\AudienceBase;
use App\Domains\Shared\Enums\AudienceFunction;
use App\Domains\Shared\Enums\AudienceLicence;
use App\Domains\Shared\Enums\Gender;

/**
 * What the author of a message asked for.
 *
 * Filters of different kinds narrow each other down (women *and* competitors);
 * several values of one kind widen it (youth *or* veterans). An empty list
 * means the filter is off.
 */
final readonly class AudienceCriteria
{
    /**
     * @param  list<AudienceLicence>  $licences
     * @param  list<Gender>  $genders
     * @param  list<AudienceAgeBand>  $ageBands
     * @param  list<int>  $excludedUserIds  Members left out of this one message by hand.
     * @param  list<int>  $includedUnclassifiedIds  Members the filters could not place, kept in by hand.
     * @param  AudienceActivityKind|null  $activityKind  With $activityId: one activity, narrowing like any other filter.
     * @param  list<AudienceFunction>  $functions  Read with the active members only: a coach is one of them, affiliated or not.
     */
    public function __construct(
        public AudienceBase $base = AudienceBase::Active,
        public array $licences = [],
        public array $genders = [],
        public array $ageBands = [],
        public array $excludedUserIds = [],
        public array $includedUnclassifiedIds = [],
        public ?AudienceActivityKind $activityKind = null,
        public ?int $activityId = null,
        public AudienceActivityMode $activityMode = AudienceActivityMode::Registered,
        public array $functions = [],
    ) {}

    /**
     * Rebuilds the criteria stored with a communication, so that it can be
     * sent again next season with the same filters.
     *
     * @param  array<string, mixed>  $stored
     */
    public static function fromArray(array $stored): self
    {
        return new self(
            base: AudienceBase::tryFrom((string) ($stored['base'] ?? '')) ?? AudienceBase::Active,
            licences: self::enums(AudienceLicence::class, $stored['licences'] ?? []),
            genders: self::enums(Gender::class, $stored['genders'] ?? []),
            ageBands: self::enums(AudienceAgeBand::class, $stored['age_bands'] ?? []),
            excludedUserIds: array_values(array_map(intval(...), $stored['excluded_user_ids'] ?? [])),
            includedUnclassifiedIds: array_values(array_map(intval(...), $stored['included_unclassified_ids'] ?? [])),
            activityKind: AudienceActivityKind::tryFrom((string) ($stored['activity']['kind'] ?? '')),
            activityId: isset($stored['activity']['id']) ? (int) $stored['activity']['id'] : null,
            activityMode: AudienceActivityMode::tryFrom((string) ($stored['activity']['mode'] ?? '')) ?? AudienceActivityMode::Registered,
            functions: self::enums(AudienceFunction::class, $stored['functions'] ?? []),
        );
    }

    /** Whether an activity narrows the audience down. */
    public function hasActivity(): bool
    {
        return $this->activityKind !== null && $this->activityId !== null;
    }

    /**
     * The filters as plain values: what the audit trail and a communication
     * keep, rather than the list of people they produced.
     *
     * @return array{base: string, licences: list<string>, genders: list<string>, age_bands: list<string>, excluded_user_ids: list<int>, included_unclassified_ids: list<int>, activity: array{kind: string, id: int, mode: string}|null, functions: list<string>}
     */
    public function toArray(): array
    {
        return [
            'base' => $this->base->value,
            'licences' => array_map(fn (AudienceLicence $licence): string => $licence->value, $this->licences),
            'genders' => array_map(fn (Gender $gender): string => $gender->value, $this->genders),
            'age_bands' => array_map(fn (AudienceAgeBand $band): string => $band->value, $this->ageBands),
            'excluded_user_ids' => $this->excludedUserIds,
            'included_unclassified_ids' => $this->includedUnclassifiedIds,
            'activity' => $this->hasActivity() ? [
                'kind' => $this->activityKind->value,
                'id' => $this->activityId,
                'mode' => $this->activityMode->value,
            ] : null,
            'functions' => array_map(fn (AudienceFunction $function): string => $function->value, $this->functions),
        ];
    }

    /**
     * @template T of \BackedEnum
     *
     * @param  class-string<T>  $enum
     * @return list<T>
     */
    private static function enums(string $enum, mixed $values): array
    {
        return array_values(array_filter(array_map(
            fn (mixed $value): ?\BackedEnum => is_string($value) ? $enum::tryFrom($value) : null,
            is_array($values) ? $values : [],
        )));
    }
}
