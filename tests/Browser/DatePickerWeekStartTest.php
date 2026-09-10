<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Meetings\Models\Meeting;

/*
 * Les calendriers du back-office ouvrent sur un lundi.
 *
 * Flatpickr est livré en anglais : sans localisation, sa première colonne est
 * un dimanche, ce qu'aucun agenda belge ne fait. La localisation est posée une
 * fois pour toutes dans `resources/js/app.js`, d'après la langue du document —
 * ce test garde ce point, sur la page où la sélection d'une date d'échéance
 * l'a rendu visible.
 */

const READ_CALENDAR_WEEKDAYS = <<<'JS'
(() => {
  if (! window.flatpickr) {
    return { loaded: false };
  }

  const input = document.createElement('input');
  document.body.appendChild(input);

  const instance = window.flatpickr(input, { inline: true });
  const weekdays = [...instance.calendarContainer.querySelectorAll('.flatpickr-weekday')]
    .map((day) => day.textContent.trim());

  instance.destroy();
  input.remove();

  return {
    loaded: true,
    firstDayOfWeek: window.flatpickr.l10ns.default.firstDayOfWeek,
    weekdays,
  };
})()
JS;

beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->isCommitteeMember()->create();
});

it('opens its calendars on a Monday', function (): void {
    $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $this->admin->id]);
    $this->actingAs($this->admin);

    $page = visit(route('admin.meetings.minutes', $meeting));

    $result = $page->script(READ_CALENDAR_WEEKDAYS);
    $state = is_array($result[0] ?? null) ? $result[0] : (array) $result;

    expect($state['loaded'])->toBeTrue('flatpickr doit être chargé sur la page des PV.');
    expect($state['firstDayOfWeek'])->toBe(1, 'La semaine commence un lundi, pas un dimanche.');
    expect($state['weekdays'][0])->toBe('lun', 'La première colonne du calendrier doit être le lundi.');
});
