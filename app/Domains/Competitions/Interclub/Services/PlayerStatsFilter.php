<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Interclub\Services;

/**
 * Who the statistics are about, and over which stretch of time.
 *
 * `seasonId` null means the running season and 0 every season; `months`, when
 * set, wins over both and reads "the last N months" — the only honest window
 * in September, when the running season holds three matches.
 */
readonly class PlayerStatsFilter
{
    /**
     * @param  array<int, string>  $rankings
     * @param  array<int, int>  $userIds
     */
    public function __construct(
        public ?int $seasonId = null,
        public ?int $months = null,
        public array $rankings = [],
        public ?string $gender = null,
        public ?int $bornFrom = null,
        public ?int $bornTo = null,
        public ?int $teamId = null,
        public bool $onlyTeamMatches = false,
        public bool $includeFormer = false,
        public array $userIds = [],
    ) {}

    /**
     * The filter for a period as the screens name it in their URL —
     * `current`, `all`, `months-N` or a season id — with the other criteria.
     *
     * @param  array<string, mixed>  $values
     */
    public static function forPeriod(string $period, array $values = []): self
    {
        [$seasonId, $months] = match (true) {
            $period === 'all' => [0, null],
            str_starts_with($period, 'months-') => [null, (int) substr($period, strlen('months-'))],
            ctype_digit($period) => [(int) $period, null],
            default => [null, null],
        };

        return self::from([...$values, 'seasonId' => $seasonId, 'months' => $months]);
    }

    /**
     * @param  array{seasonId?: int|null, months?: int|null, rankings?: array<int, string>, gender?: string|null, bornFrom?: int|null, bornTo?: int|null, teamId?: int|null, onlyTeamMatches?: bool, includeFormer?: bool, userIds?: array<int, int>}  $values
     */
    public static function from(array $values): self
    {
        $intOrNull = static fn (mixed $value): ?int => $value === null || $value === '' ? null : (int) $value;

        return new self(
            seasonId: $intOrNull($values['seasonId'] ?? null),
            months: $intOrNull($values['months'] ?? null) ?: null,
            rankings: array_values(array_filter((array) ($values['rankings'] ?? []))),
            gender: ($values['gender'] ?? null) ?: null,
            bornFrom: $intOrNull($values['bornFrom'] ?? null),
            bornTo: $intOrNull($values['bornTo'] ?? null),
            teamId: $intOrNull($values['teamId'] ?? null) ?: null,
            onlyTeamMatches: (bool) ($values['onlyTeamMatches'] ?? false),
            includeFormer: (bool) ($values['includeFormer'] ?? false),
            userIds: array_map('intval', array_values(array_filter((array) ($values['userIds'] ?? [])))),
        );
    }
}
