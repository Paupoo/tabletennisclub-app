<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;

/*
 * Le sélecteur de procuration vit dans le menu du membre, donc dans un
 * sous-menu Mary imbriqué dans un autre. Ce test garde le rendu réel : la
 * logique est couverte par AccountProxyTest, pas le fait que la balise sorte.
 */

beforeEach(function (): void {
    $this->guardianMember = User::factory()->create();

    $this->ward = User::factory()->create(['email' => null]);
    $this->ward->forceFill(['email_verified_at' => null])->save();

    $guardian = Guardian::factory()->create([
        'user_id' => $this->guardianMember->id,
        'first_name' => $this->guardianMember->first_name,
        'last_name' => $this->guardianMember->last_name,
        'email' => $this->guardianMember->email,
    ]);
    $this->ward->guardians()->attach($guardian->id);
});

/**
 * Le menu du membre est un <details> replié : le sélecteur y est rendu sans
 * être visible, donc assertSee ne le trouverait pas. On interroge le DOM.
 */
const READ_SWITCHER = <<<'JS'
(() => {
  const roots = [...document.querySelectorAll('[wire\\:id]')]
    .filter((el) => el.textContent.includes(SWITCHER_TITLE));

  const items = roots.flatMap((root) => [...root.querySelectorAll('[wire\\:click]')])
    .map((el) => ({ click: el.getAttribute('wire:click'), text: el.textContent.trim() }));

  return { found: roots.length, items };
})()
JS;

it('offers the ward in the member menu', function (): void {
    $this->actingAs($this->guardianMember);

    $page = visit(route('dashboard'))->assertNoJavaScriptErrors();

    $result = $page->script(
        'const SWITCHER_TITLE = ' . json_encode(__('I am acting for')) . ';' . READ_SWITCHER
    );
    $state = is_array($result[0] ?? null) ? $result[0] : (array) $result;

    expect($state['found'])->toBeGreaterThan(0, 'Le sélecteur doit être rendu dans le menu.');
    expect(collect($state['items'])->pluck('click'))
        ->toContain('actFor(' . $this->ward->id . ')');
    expect(collect($state['items'])->pluck('text')->implode(' '))
        ->toContain($this->ward->first_name);
});

it('shows nothing to a member who answers for nobody', function (): void {
    $this->actingAs(User::factory()->create());

    visit(route('dashboard'))
        ->assertNoJavaScriptErrors()
        ->assertDontSee(__('I am acting for'));
});
