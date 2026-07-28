<?php

declare(strict_types=1);

namespace App\Services\ClubAdmin\Users;

use App\Data\User\FederationRow;
use App\Data\User\ParsedFederationListing;
use App\Domains\Shared\Enums\Gender;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Reads the federation's affiliate listing into rows the club can reason about.
 *
 * The file is Windows-1252, semicolon separated, CRLF terminated. The encoding is
 * forced rather than guessed: detection mistakes accented names for UTF-8 and
 * silently mangles them, and a member whose name arrives wrong stays wrong.
 */
class FederationListingParser
{
    /**
     * Header labels as the federation writes them, once stripped of accents and
     * case, mapped to the names used here. Matching on a normalised label rather
     * than on column position keeps the parser working when a column moves.
     *
     * @var array<string, string>
     */
    private const COLUMNS = [
        'licence' => 'licence',
        'nom' => 'name',
        'date naissance' => 'birthdate',
        'ch' => 'mens_ranking',
        'cd' => 'ladies_ranking',
        'lfm' => 'lfm',
        'conf' => 'conf',
        'sa' => 'age_category',
        'statut' => 'licence_type',
        'date 1ere affiliation' => 'first_affiliation',
        'email' => 'email',
        'tel' => 'landline',
        'gsm' => 'mobile',
        'adresse' => 'street',
        'numero' => 'house_number',
        'cp' => 'city_code',
        'localite' => 'city_name',
    ];

    public function parse(string $contents): ParsedFederationListing
    {
        $lines = $this->lines($this->decode($contents));

        if ($lines === []) {
            return new ParsedFederationListing;
        }

        $header = $this->header(array_shift($lines));
        $rows = [];
        $failures = [];

        // Line 1 is the header, so the first record sits on line 2.
        $lineNumber = 1;

        foreach ($lines as $line) {
            $lineNumber++;

            if (trim($line) === '') {
                continue;
            }

            $cells = $this->cells($header, $line);
            $reason = $this->rejectionReason($cells);

            if ($reason !== null) {
                $failures[] = ['line' => $lineNumber, 'reason' => $reason];

                continue;
            }

            $rows[] = $this->row($cells, $lineNumber);
        }

        return new ParsedFederationListing(rows: $rows, failures: $failures);
    }

    /**
     * Map one raw line onto the header, tolerating a row carrying fewer or more
     * cells than the header announces.
     *
     * @param  array<int, string>  $header
     * @return array<string, ?string>
     */
    private function cells(array $header, string $line): array
    {
        $values = explode(';', $line);
        $cells = [];

        foreach ($header as $index => $key) {
            $cells[$key] = $this->clean($values[$index] ?? null);
        }

        return $cells;
    }

    private function clean(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        // The export escapes apostrophes several times over, so a street arrives
        // as `AVENUE DE L\\\\'EXEMPLE`. The backslashes carry no meaning.
        $value = trim((string) preg_replace("/\\\\+'/", "'", $value));

        return $value === '' ? null : $value;
    }

    /**
     * Windows-1252 to UTF-8, BOM removed.
     */
    private function decode(string $contents): string
    {
        $contents = (string) preg_replace('/^\xEF\xBB\xBF/', '', $contents);

        return (string) mb_convert_encoding($contents, 'UTF-8', 'Windows-1252');
    }

    /**
     * @return array<int, string> Column keys, in file order.
     */
    private function header(string $line): array
    {
        return array_map(
            fn (string $label): string => self::COLUMNS[$this->normalizeLabel($label)]
                ?? $this->normalizeLabel($label),
            explode(';', $line),
        );
    }

    /**
     * @return array<int, string>
     */
    private function lines(string $contents): array
    {
        return preg_split('/\r\n|\r|\n/', $contents) ?: [];
    }

    /**
     * Whether the address looks like it was shifted by a dropped cell.
     *
     * The postal code is the tell: Belgian codes are four digits, so a town name
     * sitting in that column means every address column moved one to the left.
     * The row is still importable — only the address is suspect — so this reports
     * a doubt rather than a rejection.
     *
     * @param  array<string, ?string>  $cells
     */
    private function looksShifted(array $cells): bool
    {
        if (($cells['street'] ?? null) === null) {
            return false;
        }

        $cityCode = $cells['city_code'] ?? null;

        return $cityCode === null
            || preg_match('/^\d{4}$/', $cityCode) !== 1
            || ($cells['city_name'] ?? null) === null;
    }

    private function normalizeLabel(string $label): string
    {
        return mb_strtolower(trim(Str::ascii($label)));
    }

    /**
     * Why this line cannot become a member, if it cannot.
     *
     * The reason is deliberately generic: it ends up in the import history, which
     * outlives the file, and must never carry what the line held.
     *
     * @param  array<string, ?string>  $cells
     */
    private function rejectionReason(array $cells): ?string
    {
        if (($cells['licence'] ?? null) === null) {
            return __('Missing licence number.');
        }

        if (($cells['name'] ?? null) === null) {
            return __('Missing name.');
        }

        return null;
    }

    /**
     * @param  array<string, ?string>  $cells
     */
    private function row(array $cells, int $lineNumber): FederationRow
    {
        [$lastName, $firstName, $needsNameReview] = $this->splitName($cells['name'] ?? '');

        return new FederationRow(
            lineNumber: $lineNumber,
            licence: $cells['licence'] ?? '',
            lastName: $lastName,
            firstName: $firstName,
            birthdate: isset($cells['birthdate']) ? CarbonImmutable::parse($cells['birthdate']) : null,
            // The club keeps a single, general ranking; the ladies' column is read
            // only to tell men from women, the file having no sex column of its own.
            ranking: $cells['mens_ranking'] ?? 'NA',
            gender: ($cells['ladies_ranking'] ?? '*') === '*' ? Gender::MEN : Gender::WOMEN,
            federationLicenceType: $cells['licence_type'] ?? null,
            email: $cells['email'] ?? null,
            // A shared landline reaches the household; a mobile reaches the person.
            phone: $cells['mobile'] ?? $cells['landline'] ?? null,
            street: $this->street($cells),
            cityCode: $cells['city_code'] ?? null,
            cityName: $cells['city_name'] ?? null,
            needsNameReview: $needsNameReview,
            needsAddressReview: $this->looksShifted($cells),
        );
    }

    /**
     * The federation runs first and last name together in one column, with no
     * separator. Two words are unambiguous; beyond that the split is a guess —
     * `DE LA FONTAINE CLAIRE` and `LEROY MARC ANTOINE` are indistinguishable —
     * so the last word is taken as the first name and the row asks for a human
     * to confirm it.
     *
     * @return array{0: string, 1: string, 2: bool}
     */
    private function splitName(string $name): array
    {
        $words = preg_split('/\s+/', trim($name), flags: PREG_SPLIT_NO_EMPTY) ?: [];

        if ($words === []) {
            return ['', '', false];
        }

        if (count($words) === 1) {
            return [$this->titleCase($words[0]), '', true];
        }

        $firstName = array_pop($words);

        return [
            $this->titleCase(implode(' ', $words)),
            $this->titleCase($firstName),
            count($words) > 1,
        ];
    }

    /**
     * @param  array<string, ?string>  $cells
     */
    private function street(array $cells): ?string
    {
        $street = trim(($cells['street'] ?? '') . ' ' . ($cells['house_number'] ?? ''));

        return $street === '' ? null : $street;
    }

    /**
     * Mirrors the casing the User model applies on write, so the review screen
     * shows exactly what will be recorded.
     */
    private function titleCase(string $value): string
    {
        return mb_convert_case($value, MB_CASE_TITLE);
    }
}
