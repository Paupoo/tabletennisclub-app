<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Feedback\Models\FeedbackEntry;
use App\Domains\ClubAdmin\Feedback\Models\FeedbackTheme;
use App\Domains\ClubAdmin\Feedback\Models\HelpOffer;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\FeedbackStatus;
use App\Domains\Shared\Enums\HelpOfferStatus;
use App\Domains\Shared\Enums\Role;
use Livewire\Livewire;

const FEEDBACK_ADMIN_COMPONENT = 'pages::club-admin.feedback.index';

beforeEach(function (): void {
    $this->theme = FeedbackTheme::query()->where('name', 'Bar')->firstOrFail();
    $this->delegate = User::factory()->withRole(Role::FEEDBACK)->create(['first_name' => 'Nadia', 'last_name' => 'Benali']);
    $this->seat = User::factory()->isCommitteeMember()->create();
    $this->author = User::factory()->create(['first_name' => 'Sophie', 'last_name' => 'Renard']);
});

it('opens the feedback to the committee and the délégation, and to nobody else', function (): void {
    $this->actingAs($this->seat)->get(route('admin.feedback.index'))->assertOk();
    $this->actingAs($this->delegate)->get(route('admin.feedback.index'))->assertOk();
    $this->actingAs($this->author)->get(route('admin.feedback.index'))->assertForbidden();
});

it('shows every feedback to a committee seat, naming only the signed ones', function (): void {
    FeedbackEntry::factory()->for($this->author, 'author')->create(['feedback_theme_id' => $this->theme->id, 'body' => 'Signé par Sophie.']);
    FeedbackEntry::factory()->anonymous()->create(['feedback_theme_id' => $this->theme->id, 'body' => 'Écrit sans nom.']);

    Livewire::actingAs($this->seat)
        ->test(FEEDBACK_ADMIN_COMPONENT)
        ->assertSee('Signé par Sophie.')
        ->assertSee('Sophie Renard')
        ->assertSee('Écrit sans nom.')
        ->assertSee('Anonyme');
});

it('lets the délégation mark a feedback read, which the author then sees', function (): void {
    $entry = FeedbackEntry::factory()->for($this->author, 'author')->create(['feedback_theme_id' => $this->theme->id]);

    Livewire::actingAs($this->delegate)
        ->test(FEEDBACK_ADMIN_COMPONENT)
        ->call('setStatus', $entry->id, FeedbackStatus::Read->value)
        ->assertHasNoErrors();

    $entry->refresh();
    expect($entry->status)->toBe(FeedbackStatus::Read)
        ->and($entry->read_at)->not->toBeNull();
});

it('keeps the first reading date when the status moves on', function (): void {
    $entry = FeedbackEntry::factory()->read()->create(['feedback_theme_id' => $this->theme->id, 'read_at' => '2026-10-01 09:00:00']);

    Livewire::actingAs($this->delegate)
        ->test(FEEDBACK_ADMIN_COMPONENT)
        ->call('setStatus', $entry->id, FeedbackStatus::Retained->value);

    expect($entry->fresh()->read_at->toDateString())->toBe('2026-10-01');
});

it('refuses a committee seat that tries to sort the feedback', function (): void {
    $entry = FeedbackEntry::factory()->create(['feedback_theme_id' => $this->theme->id]);

    Livewire::actingAs($this->seat)
        ->test(FEEDBACK_ADMIN_COMPONENT)
        ->call('setStatus', $entry->id, FeedbackStatus::Read->value)
        ->assertForbidden();

    expect($entry->fresh()->status)->toBe(FeedbackStatus::New);
});

it('keeps an internal note for the committee', function (): void {
    $entry = FeedbackEntry::factory()->create(['feedback_theme_id' => $this->theme->id]);

    Livewire::actingAs($this->delegate)
        ->test(FEEDBACK_ADMIN_COMPONENT)
        ->set("notes.{$entry->id}", 'À faire pour le tournoi de mars.')
        ->call('saveNote', $entry->id);

    expect($entry->fresh()->internal_note)->toBe('À faire pour le tournoi de mars.');
});

it('hides an insulting feedback behind a trace the committee can still open', function (): void {
    $entry = FeedbackEntry::factory()->anonymous()->create(['feedback_theme_id' => $this->theme->id, 'body' => 'Propos injurieux.']);

    $screen = Livewire::actingAs($this->delegate)
        ->test(FEEDBACK_ADMIN_COMPONENT)
        ->set("hideReasons.{$entry->id}", 'Propos injurieux envers une personne nommée')
        ->call('hide', $entry->id)
        ->assertHasNoErrors();

    $entry->refresh();
    expect($entry->hidden_at)->not->toBeNull()
        ->and($entry->hidden_by_id)->toBe($this->delegate->id)
        ->and($entry->hidden_reason)->toBe('Propos injurieux envers une personne nommée');

    Livewire::actingAs($this->seat)
        ->test(FEEDBACK_ADMIN_COMPONENT)
        ->assertSee('Masqué par Nadia Benali')
        ->assertDontSee('Propos injurieux.')
        ->call('reveal', $entry->id)
        ->assertSee('Propos injurieux.');
});

it('refuses to hide a feedback without saying why', function (): void {
    $entry = FeedbackEntry::factory()->create(['feedback_theme_id' => $this->theme->id]);

    Livewire::actingAs($this->delegate)
        ->test(FEEDBACK_ADMIN_COMPONENT)
        ->call('hide', $entry->id)
        ->assertHasErrors(["hideReasons.{$entry->id}"]);

    expect($entry->fresh()->hidden_at)->toBeNull();
});

it('filters the feedback on its status', function (): void {
    FeedbackEntry::factory()->create(['feedback_theme_id' => $this->theme->id, 'body' => 'Tout neuf.']);
    FeedbackEntry::factory()->read()->create(['feedback_theme_id' => $this->theme->id, 'body' => 'Déjà lu.']);

    Livewire::actingAs($this->seat)
        ->test(FEEDBACK_ADMIN_COMPONENT)
        ->set('statusFilter', FeedbackStatus::New->value)
        ->assertSee('Tout neuf.')
        ->assertDontSee('Déjà lu.');
});

it('lists the offers of help and lets the délégation record the follow-up', function (): void {
    $offer = HelpOffer::factory()->for($this->author, 'volunteer')->create(['message' => 'Je fais du web au boulot.']);

    Livewire::actingAs($this->delegate)
        ->test(FEEDBACK_ADMIN_COMPONENT)
        ->set('tab', 'help')
        ->assertSee('Sophie Renard')
        ->assertSee('Je fais du web au boulot.')
        ->call('setOfferStatus', $offer->id, HelpOfferStatus::Contacted->value);

    $offer->refresh();
    expect($offer->status)->toBe(HelpOfferStatus::Contacted)
        ->and($offer->handled_by_id)->toBe($this->delegate->id)
        ->and($offer->handled_at)->not->toBeNull();
});

it('refuses a committee seat that records a follow-up', function (): void {
    $offer = HelpOffer::factory()->create();

    Livewire::actingAs($this->seat)
        ->test(FEEDBACK_ADMIN_COMPONENT)
        ->call('setOfferStatus', $offer->id, HelpOfferStatus::Contacted->value)
        ->assertForbidden();
});
