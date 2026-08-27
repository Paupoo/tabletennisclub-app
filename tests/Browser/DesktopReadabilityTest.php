<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;

/*
 * Trois excès mesurés à 1440x900 lors de la passe desktop :
 *
 *  - les affiliations rendaient toute la saison d'un coup, sans paginateur ;
 *  - le journal d'audit déversait un diff de 649 caractères dans une cellule,
 *    qui étirait la ligne et repoussait le tableau hors de l'écran ;
 *  - une explication courait sur 1028 px, soit ~130 caractères par ligne.
 *
 * Ce que mesure ce test est ce qui s'affiche : un diff replié vit en
 * display:none et ne coûte rien à la hauteur d'une ligne.
 */

const DESKTOP_READING = <<<'JS_WRAP'
(() => {
  const main = document.querySelector('main') || document.body;
  const off = el => el.closest('.drawer-side, dialog') !== null;
  const trim = s => s.replace(/\s+/g, ' ').trim();

  const visibleText = el => [...el.querySelectorAll('*')].concat([el])
    .filter(n => n.getBoundingClientRect().height > 0)
    .flatMap(n => [...n.childNodes].filter(c => c.nodeType === 3).map(c => c.textContent.trim()))
    .join(' ');

  const rows = [...main.querySelectorAll('tbody tr')]
    .filter(el => !off(el) && el.getBoundingClientRect().height > 0);

  const cells = [...main.querySelectorAll('tbody td')].filter(c => !off(c));

  const prose = [...main.querySelectorAll('p, li, dd')]
    .filter(el => !off(el) && trim(el.textContent || '').length > 40)
    .filter(el => el.getBoundingClientRect().height > 0)
    .map(el => el.getBoundingClientRect().width);

  return JSON.stringify({
    lignes: rows.length,
    cellule_max: cells.reduce((n, c) => Math.max(n, trim(visibleText(c)).length), 0),
    prose_max: prose.length ? Math.round(Math.max(...prose)) : 0,
  });
})()
JS_WRAP;

beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->isCommitteeMember()->create();
    $this->season = makeActiveSeason();
    $this->actingAs($this->admin);
});

it('paginates the affiliations instead of rendering the whole season', function (): void {
    $members = User::factory()->count(40)->create();
    foreach ($members as $member) {
        Subscription::factory()->for($member)->create([
            'season_id' => $this->season->id,
            'status' => 'confirmed',
        ]);
    }

    $reading = json_decode((string) visit(route('admin.users.registrations'))->resize(1440, 900)->script(DESKTOP_READING), true);

    expect($reading['lignes'])->toBeLessThanOrEqual(20, 'a page of affiliations belongs in one page, not a whole season');
});

it('folds an audit diff instead of stretching the row with it', function (): void {
    // Une modification de masse : huit champs d'un coup, ce que fait un import.
    $member = User::factory()->create();
    $member->update([
        'first_name' => 'Prénom modifié',
        'last_name' => 'Nom de famille modifié',
        'phone_number' => '0470111222',
        'street' => 'Rue du Sport et des Loisirs 144',
        'city_name' => 'Ottignies-Louvain-la-Neuve',
        'city_code' => '1348',
        'licence' => '654321',
        'ranking' => 'C4',
    ]);

    $reading = json_decode((string) visit(route('admin.audit.index'))->resize(1440, 900)->script(DESKTOP_READING), true);

    expect($reading['cellule_max'])->toBeLessThan(200, 'a table cell holding a paragraph pushes the table off the screen');
});

it('keeps an explanation inside a readable measure', function (): void {
    $reading = json_decode((string) visit(route('admin.seasons.index'))->resize(1440, 900)->script(DESKTOP_READING), true);

    // 720 px ≈ 90 caractères, la limite haute du confort de lecture.
    expect($reading['prose_max'])->toBeLessThan(720, 'the eye loses the line beyond 90 characters');
});
