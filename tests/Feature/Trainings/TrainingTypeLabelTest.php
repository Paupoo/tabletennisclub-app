<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\TrainingType;
use Livewire\Livewire;

it('translates the training types', function (): void {
    app()->setLocale('fr_BE');

    expect(TrainingType::DIRECTED->label())->toBe('Dirigé')
        ->and(TrainingType::SUPERVISED->label())->toBe('Supervisé')
        ->and(TrainingType::FREE->label())->toBe('Libre');
})->group('training', 'i18n');

it('offers the translated types in the pack wizard', function (): void {
    app()->setLocale('fr_BE');

    // Même défaut que levelOptions() : le sélecteur listait la valeur anglaise
    // de l'enum alors que la traduction existait déjà.
    $options = Livewire::actingAs(User::factory()->isAdmin()->create())
        ->test('pages::club-events.trainings.index')
        ->get('typeOptions');

    expect(collect($options)->pluck('name'))->toContain('Dirigé')
        ->and(collect($options)->pluck('name'))->not->toContain('Directed');
})->group('training', 'i18n');
