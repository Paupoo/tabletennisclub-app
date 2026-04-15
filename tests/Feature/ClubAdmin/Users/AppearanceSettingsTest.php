<?php

declare(strict_types=1);

namespace Tests\Feature\ClubAdmin\Users;

use App\Models\ClubAdmin\Users\User;
use Livewire\Livewire;

// Évite la répétition du nom du composant dans chaque test
const APPEARANCE_COMPONENT = 'club-admin.users.user-space.settings.appearance-settings';

beforeEach(function () {
    $this->user = User::factory()->create(['theme' => null]);
});

describe('User Appearance Settings Test', function() {
// ─────────────────────────────────────────────
    // 1. Default theme
    // ─────────────────────────────────────────────

    it('sets "auto" as the default theme when no theme is stored in the database', function () {
        Livewire::actingAs($this->user)
            ->test(APPEARANCE_COMPONENT, ['user' => $this->user])
            ->assertSet('theme_choice', 'auto');
    });

    it('uses the theme previously saved in the database', function () {
        $this->user->update(['theme' => 'dark']);

        Livewire::actingAs($this->user)
            ->test(APPEARANCE_COMPONENT, ['user' => $this->user])
            ->assertSet('theme_choice', 'dark');
    });

    // ─────────────────────────────────────────────
    // 2. Database persistence
    // ─────────────────────────────────────────────

    it('saves the selected theme to the database', function () {
        Livewire::actingAs($this->user)
            ->test(APPEARANCE_COMPONENT, ['user' => $this->user])
            ->set('theme_choice', 'dark');

        expect($this->user->fresh()->theme)->toBe('dark');
    });

    it('saves "light" theme correctly', function () {
        Livewire::actingAs($this->user)
            ->test(APPEARANCE_COMPONENT, ['user' => $this->user])
            ->set('theme_choice', 'light');

        expect($this->user->fresh()->theme)->toBe('light');
    });

    it('saves "auto" theme correctly', function () {
        $this->user->update(['theme' => 'dark']);

        Livewire::actingAs($this->user)
            ->test(APPEARANCE_COMPONENT, ['user' => $this->user])
            ->set('theme_choice', 'auto');

        expect($this->user->fresh()->theme)->toBe('auto');
    });

    // ─────────────────────────────────────────────
    // 3. Browser event (set-theme)
    // ─────────────────────────────────────────────

    it('dispatches the set-theme event with "dark"', function () {
        Livewire::actingAs($this->user)
            ->test(APPEARANCE_COMPONENT, ['user' => $this->user])
            ->set('theme_choice', 'dark')
            ->assertDispatched('set-theme', theme: 'dark');
    });

    it('dispatches the set-theme event with "light"', function () {
        Livewire::actingAs($this->user)
            ->test(APPEARANCE_COMPONENT, ['user' => $this->user])
            ->set('theme_choice', 'light')
            ->assertDispatched('set-theme', theme: 'light');
    });

    it('dispatches the set-theme event with "auto"', function () {
        Livewire::actingAs($this->user)
            ->test(APPEARANCE_COMPONENT, ['user' => $this->user])
            ->set('theme_choice', 'auto')
            ->assertDispatched('set-theme', theme: 'auto');
    });

    // ─────────────────────────────────────────────
    // 4. Validation
    // ─────────────────────────────────────────────

    it('rejects an invalid theme value', function () {
        Livewire::actingAs($this->user)
            ->test(APPEARANCE_COMPONENT, ['user' => $this->user])
            ->set('theme_choice', 'invalid-theme')
            ->assertHasErrors(['theme_choice']);
    });

    it('rejects a value longer than 20 characters', function () {
        Livewire::actingAs($this->user)
            ->test(APPEARANCE_COMPONENT, ['user' => $this->user])
            ->set('theme_choice', str_repeat('a', 21))
            ->assertHasErrors(['theme_choice']);
    });

    // ─────────────────────────────────────────────
    // 5. User feedback
    // ─────────────────────────────────────────────

    // Le toast Mary UI est rendu côté JS (Alpine), pas via un event Livewire capturable.
    // On vérifie donc que le composant n'a pas d'erreur et que la DB est bien mise à jour —
    // ce qui confirme que updatedThemeChoice() s'est bien exécuté jusqu'au bout (toast inclus).
    it('completes the full update flow without errors when changing the theme', function () {
        Livewire::actingAs($this->user)
            ->test(APPEARANCE_COMPONENT, ['user' => $this->user])
            ->set('theme_choice', 'dark')
            ->assertHasNoErrors();

        expect($this->user->fresh()->theme)->toBe('dark');
    });
})->group('Users');
    