<?php

declare(strict_types=1);

namespace tests\Architecture;

use Symfony\Component\Finder\Finder;

/*
 * The application holds two unrelated things that French happily calls the same
 * word. A `Subscription` is the season membership — the affiliation. A
 * `Registration` is a sign-up for a tournament, an event or a meeting — an
 * inscription. Both once rendered as "inscription", which is what made the
 * admin menu ambiguous.
 *
 * The vocabulary is now fixed: the record is an *affiliation*, the sum of money
 * owed for it is a *cotisation*, and signing up for an event is an
 * *inscription*. Nothing in the type system enforces that, because both sides
 * are plain translation keys — a single mis-mapped key silently relabels a
 * tournament tab as "Affiliations", or an affiliation button as "Inscription".
 *
 * These two tests resolve every translation key used on each side of the
 * boundary through lang/fr_BE.json and assert the rendered French never crosses
 * it. Training enrolments are the deliberate exception: one does enrol — sign
 * up — for a training pack, so "inscription" is correct there even on the
 * affiliation side.
 */

/**
 * @return array<int, array{file: string, key: string, value: string}>
 */
function frenchStringsUsedIn(string $root, array $relativePaths): array
{
    $translations = json_decode(
        (string) file_get_contents($root . '/lang/fr_BE.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    $directories = array_values(array_filter(
        array_map(static fn (string $path): string => $root . '/' . $path, $relativePaths),
        'is_dir',
    ));

    if ($directories === []) {
        return [];
    }

    $strings = [];

    $files = (new Finder)->files()->in($directories)->name(['*.php', '*.blade.php']);

    foreach ($files as $file) {
        $contents = (string) file_get_contents($file->getPathname());

        preg_match_all(
            '/__\(\s*\'((?:[^\'\\\\]|\\\\.)*)\'|__\(\s*"((?:[^"\\\\]|\\\\.)*)"/',
            $contents,
            $matches,
            PREG_SET_ORDER,
        );

        foreach ($matches as $match) {
            $key = str_replace(["\\'", '\\"'], ["'", '"'], $match[1] !== '' ? $match[1] : ($match[2] ?? ''));

            if ($key === '') {
                continue;
            }

            $strings[] = [
                'file' => str_replace($root . '/', '', $file->getPathname()),
                'key' => $key,
                // A key with no entry falls through to itself, so check that too.
                'value' => $translations[$key] ?? $key,
            ];
        }
    }

    return $strings;
}

it('never labels a tournament, event or meeting as an affiliation', function (): void {
    $root = dirname(__DIR__, 2);

    $strings = frenchStringsUsedIn($root, [
        'resources/views/components/admin/club-events/tournaments',
        'resources/views/pages/club-events/tournaments',
        'resources/views/pages/club-admin/users/user-space/⚡event-subscription',
        'resources/views/public/tournament',
        'app/Domains/Competitions',
        'app/Domains/Meetings',
    ]);

    $offenders = [];

    foreach ($strings as $string) {
        if (mb_stripos($string['value'], 'affiliation') !== false) {
            $offenders[] = sprintf('%s → "%s"', $string['file'], $string['value']);
        }
    }

    expect(array_values(array_unique($offenders)))->toBe([], sprintf(
        "Tournaments, events and meetings take an *inscription*, never an affiliation.\n"
        . "These translation keys render \"affiliation\" on the event side of the boundary:\n%s",
        implode("\n", array_unique($offenders)),
    ));
})->group('vocabulary');

it('never labels an affiliation as an inscription', function (): void {
    $root = dirname(__DIR__, 2);

    $strings = frenchStringsUsedIn($root, [
        'resources/views/pages/club-admin/users/⚡registrations',
        'resources/views/pages/club-admin/users/user-space/⚡registration-management',
        'app/Domains/Subscriptions',
    ]);

    $offenders = [];

    foreach ($strings as $string) {
        if (mb_stripos($string['value'], 'inscription') === false) {
            continue;
        }

        // One genuinely enrols in a training pack, so "inscription" is the right
        // word there even though the pack hangs off the affiliation record.
        $aboutTrainings = preg_match('/entra[îi]nement|training|pack/iu', $string['value']) === 1;

        if ($aboutTrainings) {
            continue;
        }

        $offenders[] = sprintf('%s → "%s"', $string['file'], $string['value']);
    }

    expect(array_values(array_unique($offenders)))->toBe([], sprintf(
        "The season membership is an *affiliation*; only the money owed for it is a cotisation.\n"
        . "These translation keys render \"inscription\" on the affiliation side of the boundary:\n%s",
        implode("\n", array_unique($offenders)),
    ));
})->group('vocabulary');
