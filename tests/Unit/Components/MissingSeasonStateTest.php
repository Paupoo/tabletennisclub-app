<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Support\Facades\File;

pest()->group('components', 'emptyState');

/*
 * "No season yet" is the first screen a club sees after installing, and it used
 * to change shape with the door you came through: a full-width amber alert on
 * trainings, a bare centred sentence on interclubs, an alert AND an empty state
 * on the planning board. Only the seasons screen offered a way out.
 *
 * One shape, and it carries the action that lifts the prerequisite.
 */

it('names the missing prerequisite and points at the way out', function (): void {
    $this->actingAs(User::factory()->isAdmin()->create());

    $html = (string) $this->blade('<x-admin.shared.missing-season-state />');

    expect($html)->toContain(__('No active season'))
        ->and($html)->toContain(route('admin.seasons.index'));
});

it('says nothing it cannot back up when the reader cannot manage seasons', function (): void {
    $this->actingAs(User::factory()->create());

    $html = (string) $this->blade('<x-admin.shared.missing-season-state />');

    expect($html)->toContain(__('No active season'))
        ->and($html)->not->toContain(route('admin.seasons.index'));
});

it('takes the sentence that says what this screen needs the season for', function (): void {
    $this->actingAs(User::factory()->isAdmin()->create());

    $html = (string) $this->blade(
        '<x-admin.shared.missing-season-state :message="$message" />',
        ['message' => 'Une saison active porte le calendrier des matchs.']
    );

    expect($html)->toContain('Une saison active porte le calendrier des matchs.');
});

it('is the only shape the season-guarded screens use', function (): void {
    $guarded = [
        'pages/club-events/trainings/⚡index/index.blade.php',
        'pages/club-events/interclubs/⚡interclubs/interclubs.blade.php',
        'pages/club-admin/planning/⚡board/board.blade.php',
        'pages/club-events/interclubs/teams/⚡index/index.blade.php',
    ];

    $offenders = [];

    foreach ($guarded as $relative) {
        $contents = (string) File::get(resource_path('views/' . $relative));

        if (! str_contains($contents, '<x-admin.shared.missing-season-state')) {
            $offenders[] = $relative;
        }
    }

    expect($offenders)->toBe([], "Screens still rolling their own missing-season state:\n" . implode("\n", $offenders));
});

it('leaves no dead-end sentence about picking a season', function (): void {
    $deadEnds = [
        'pages/club-events/interclubs/⚡interclubs/interclubs.blade.php' => 'Select a season to manage the schedule.',
        'pages/club-events/trainings/⚡index/index.blade.php' => 'No seasons found. Create a season first.',
        'pages/club-events/interclubs/teams/⚡index/index.blade.php' => 'Aucune saison active. Activez une saison',
    ];

    $offenders = [];

    foreach ($deadEnds as $relative => $sentence) {
        if (str_contains((string) File::get(resource_path('views/' . $relative)), $sentence)) {
            $offenders[] = $relative;
        }
    }

    expect($offenders)->toBe([], "Instructions pointing at a control nothing shows:\n" . implode("\n", $offenders));
});
