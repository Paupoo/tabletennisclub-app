<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

enum Ranking: string
{
    case B0 = 'B0';
    case B2 = 'B2';
    case B4 = 'B4';
    case B6 = 'B6';
    case C0 = 'C0';
    case C2 = 'C2';
    case C4 = 'C4';
    case C6 = 'C6';
    case D0 = 'D0';
    case D2 = 'D2';
    case D4 = 'D4';
    case D6 = 'D6';
    case E0 = 'E0';
    case E2 = 'E2';
    case E4 = 'E4';
    case E6 = 'E6';
    case NA = 'NA';
    case NC = 'NC';

    /**
     * Rankings a member holds once affiliated — NA excluded.
     *
     * @return array<int, string>
     */
    public static function affiliatedValues(): array
    {
        return array_column(self::options(includeNA: false), 'id');
    }

    /**
     * Options for a select, strongest first.
     *
     * NA is the absence of a ranking, not a ranking of its own: it is offered
     * only where a member may legitimately have none yet, and never on an
     * affiliation being accepted — an unranked affiliated player is NC.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public static function options(bool $includeNA = true): array
    {
        $cases = array_filter(
            self::cases(),
            static fn (self $case): bool => $includeNA || $case !== self::NA,
        );

        return array_values(array_map(
            static fn (self $case): array => ['id' => $case->value, 'name' => $case->getLabel()],
            $cases,
        ));
    }

    /**
     * Return the localized string of a value
     */
    public function getLabel(): string
    {
        return $this === self::NA ? __('N/A') : $this->value;
    }
}
