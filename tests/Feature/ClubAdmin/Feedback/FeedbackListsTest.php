<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Feedback\Models\FeedbackTheme;
use App\Domains\ClubAdmin\Feedback\Models\HelpTask;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;
use Livewire\Livewire;

const FEEDBACK_LISTS_COMPONENT = 'pages::club-admin.feedback.lists';

beforeEach(function (): void {
    $this->delegate = User::factory()->withRole(Role::FEEDBACK)->create();
});

it('opens the lists to the délégation only', function (): void {
    $this->actingAs($this->delegate)->get(route('admin.feedback.lists'))->assertOk();
    $this->actingAs(User::factory()->isCommitteeMember()->create())->get(route('admin.feedback.lists'))->assertForbidden();
});

it('adds a theme before the permanent one', function (): void {
    Livewire::actingAs($this->delegate)
        ->test(FEEDBACK_LISTS_COMPONENT)
        ->set('newTheme', 'Stage d’été')
        ->call('addTheme')
        ->assertHasNoErrors();

    expect(FeedbackTheme::offered()->pluck('name')->take(-2)->values()->all())->toBe(['Stage d’été', 'Autre']);
});

it('adds a help task before the permanent one', function (): void {
    Livewire::actingAs($this->delegate)
        ->test(FEEDBACK_LISTS_COMPONENT)
        ->set('newTask', 'Laver les maillots')
        ->call('addTask');

    expect(HelpTask::offered()->pluck('name')->take(-2)->values()->all())->toBe(['Laver les maillots', 'Rejoindre le comité']);
});

it('renames a theme', function (): void {
    $theme = FeedbackTheme::query()->where('name', 'Stages')->firstOrFail();

    Livewire::actingAs($this->delegate)
        ->test(FEEDBACK_LISTS_COMPONENT)
        ->set("themeNames.{$theme->id}", 'Stages et camps')
        ->call('renameTheme', $theme->id);

    expect($theme->fresh()->name)->toBe('Stages et camps');
});

it('hides and shows again a theme', function (): void {
    $theme = FeedbackTheme::query()->where('name', 'Réunions')->firstOrFail();

    $screen = Livewire::actingAs($this->delegate)->test(FEEDBACK_LISTS_COMPONENT)->call('toggleTheme', $theme->id);
    expect($theme->fresh()->hidden_at)->not->toBeNull();

    $screen->call('toggleTheme', $theme->id);
    expect($theme->fresh()->hidden_at)->toBeNull();
});

it('never hides the permanent entries', function (): void {
    $other = FeedbackTheme::query()->where('is_permanent', true)->sole();
    $committee = HelpTask::query()->where('is_permanent', true)->sole();

    Livewire::actingAs($this->delegate)
        ->test(FEEDBACK_LISTS_COMPONENT)
        ->call('toggleTheme', $other->id)
        ->call('toggleTask', $committee->id);

    expect($other->fresh()->hidden_at)->toBeNull()
        ->and($committee->fresh()->hidden_at)->toBeNull();
});

it('moves a task up the list, but never past the permanent one at the end', function (): void {
    $names = fn (): array => HelpTask::offered()->pluck('name')->all();
    $referee = HelpTask::query()->where('name', 'Arbitrer')->firstOrFail();
    $before = $names();
    $index = array_search('Arbitrer', $before, true);

    Livewire::actingAs($this->delegate)->test(FEEDBACK_LISTS_COMPONENT)->call('moveTask', $referee->id, 'up');

    expect(array_search('Arbitrer', $names(), true))->toBe($index - 1);

    $last = HelpTask::offered()->where('is_permanent', false)->get()->last();
    Livewire::actingAs($this->delegate)->test(FEEDBACK_LISTS_COMPONENT)->call('moveTask', $last->id, 'down');

    expect(collect($names())->last())->toBe('Rejoindre le comité');
});

it('refuses a committee seat that edits the lists', function (): void {
    $theme = FeedbackTheme::query()->firstOrFail();

    Livewire::actingAs(User::factory()->isCommitteeMember()->create())
        ->test(FEEDBACK_LISTS_COMPONENT)
        ->assertForbidden();
});
