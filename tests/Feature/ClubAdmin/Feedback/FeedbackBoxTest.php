<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Feedback\Models\FeedbackEntry;
use App\Domains\ClubAdmin\Feedback\Models\FeedbackTheme;
use App\Domains\ClubAdmin\Feedback\Models\HelpOffer;
use App\Domains\ClubAdmin\Feedback\Models\HelpTask;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\HelpRhythm;
use Livewire\Livewire;

const FEEDBACK_BOX_COMPONENT = 'pages::club-admin.users.user-space.feedback';

beforeEach(function (): void {
    $this->member = User::factory()->create();
    $this->bar = FeedbackTheme::query()->where('name', 'Bar')->firstOrFail();
});

it('files a signed feedback and lists it in « My feedback », not yet read', function (): void {
    Livewire::actingAs($this->member)
        ->test(FEEDBACK_BOX_COMPONENT, ['user' => $this->member])
        ->set('themeId', $this->bar->id)
        ->set('body', 'Le terminal de paiement ne marche pas une fois sur deux.')
        ->call('send')
        ->assertHasNoErrors()
        ->assertSee('Le terminal de paiement ne marche pas une fois sur deux.')
        ->assertSee('Envoyé, pas encore lu');

    $entry = FeedbackEntry::sole();
    expect($entry->user_id)->toBe($this->member->id)
        ->and($entry->feedback_theme_id)->toBe($this->bar->id);
});

it('keeps no trace of who wrote an anonymous feedback', function (): void {
    $this->travelTo(now()->setTime(14, 37, 12));

    Livewire::actingAs($this->member)
        ->test(FEEDBACK_BOX_COMPONENT, ['user' => $this->member])
        ->set('themeId', $this->bar->id)
        ->set('body', 'Un planning du bar affiché à l’entrée aiderait.')
        ->set('anonymous', true)
        ->call('send')
        ->assertHasNoErrors()
        ->assertDontSee('Un planning du bar affiché à l’entrée aiderait.');

    $entry = FeedbackEntry::sole();
    expect($entry->user_id)->toBeNull()
        ->and($entry->created_at->format('H:i:s'))->toBe('00:00:00')
        ->and($entry->updated_at->format('H:i:s'))->toBe('00:00:00');
});

it('refuses a feedback without a theme or a message', function (): void {
    Livewire::actingAs($this->member)
        ->test(FEEDBACK_BOX_COMPONENT, ['user' => $this->member])
        ->call('send')
        ->assertHasErrors(['themeId', 'body']);

    expect(FeedbackEntry::count())->toBe(0);
});

it('no longer offers a theme the committee hid', function (): void {
    $hidden = FeedbackTheme::factory()->hidden()->create(['name' => 'Ancienne salle']);

    Livewire::actingAs($this->member)
        ->test(FEEDBACK_BOX_COMPONENT, ['user' => $this->member])
        ->assertDontSee('Ancienne salle')
        ->set('themeId', $hidden->id)
        ->set('body', 'Texte')
        ->call('send')
        ->assertHasErrors(['themeId']);
});

it('tells the member when the committee read their feedback', function (): void {
    FeedbackEntry::factory()->for($this->member, 'author')->create([
        'feedback_theme_id' => $this->bar->id,
        'body' => 'Afficher les tableaux la veille.',
        'read_at' => '2026-10-09 10:00:00',
    ]);

    Livewire::actingAs($this->member)
        ->test(FEEDBACK_BOX_COMPONENT, ['user' => $this->member])
        ->assertSee('Lu par le comité le 9 octobre 2026');
});

it('refuses another member’s page', function (): void {
    $other = User::factory()->create();

    Livewire::actingAs($this->member)
        ->test(FEEDBACK_BOX_COMPONENT, ['user' => $other])
        ->assertForbidden();
});

it('records an offer of help in the member’s name, even beside an anonymous feedback', function (): void {
    $bar = HelpTask::query()->where('name', 'Tenir le bar')->firstOrFail();
    $committee = HelpTask::query()->where('name', 'Rejoindre le comité')->firstOrFail();

    Livewire::actingAs($this->member)
        ->test(FEEDBACK_BOX_COMPONENT, ['user' => $this->member])
        ->set('themeId', $this->bar->id)
        ->set('body', 'Un avis anonyme.')
        ->set('anonymous', true)
        ->set('helpRhythm', HelpRhythm::Regular->value)
        ->set('helpTaskIds', [$bar->id, $committee->id])
        ->set('helpMessage', 'Je peux aussi faire les courses.')
        ->call('send')
        ->assertHasNoErrors();

    $offer = HelpOffer::sole();
    expect($offer->user_id)->toBe($this->member->id)
        ->and($offer->rhythm)->toBe(HelpRhythm::Regular)
        ->and($offer->message)->toBe('Je peux aussi faire les courses.')
        ->and($offer->tasks->pluck('id')->all())->toEqualCanonicalizing([$bar->id, $committee->id])
        ->and(FeedbackEntry::sole()->user_id)->toBeNull();
});

it('lets a member offer help without writing any feedback', function (): void {
    $task = HelpTask::query()->where('name', 'Arbitrer')->firstOrFail();

    Livewire::actingAs($this->member)
        ->test(FEEDBACK_BOX_COMPONENT, ['user' => $this->member])
        ->set('helpTaskIds', [$task->id])
        ->call('send')
        ->assertHasNoErrors();

    expect(HelpOffer::count())->toBe(1)
        ->and(FeedbackEntry::count())->toBe(0);
});

it('does not ask again while the club still owes an answer to an offer', function (): void {
    HelpOffer::factory()->for($this->member, 'volunteer')->create(['created_at' => '2026-02-03 10:00:00']);

    Livewire::actingAs($this->member)
        ->test(FEEDBACK_BOX_COMPONENT, ['user' => $this->member])
        ->assertDontSee(__('Fancy giving a hand?'))
        ->assertSee('Vous nous avez proposé votre aide le 3 février 2026');
});

it('asks again once the offer was answered', function (): void {
    HelpOffer::factory()->contacted()->for($this->member, 'volunteer')->create();

    Livewire::actingAs($this->member)
        ->test(FEEDBACK_BOX_COMPONENT, ['user' => $this->member])
        ->assertSee(__('Fancy giving a hand?'));
});

it('never asks a managed account to help', function (): void {
    $ward = User::factory()->create(['email' => null]);
    $task = HelpTask::query()->firstOrFail();

    Livewire::actingAs($ward)
        ->test(FEEDBACK_BOX_COMPONENT, ['user' => $ward])
        ->assertDontSee(__('Fancy giving a hand?'))
        ->set('themeId', $this->bar->id)
        ->set('body', 'Avis de Lucas.')
        ->set('helpTaskIds', [$task->id])
        ->call('send');

    expect(HelpOffer::count())->toBe(0)
        ->and(FeedbackEntry::count())->toBe(1);
});
