<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Communications\Models\Communication;
use App\Domains\ClubAdmin\Communications\Models\CommunicationRecipient;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use App\Mail\CommunicationMail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/*
| Writing from the application: a markdown message with a live preview, a test
| sent to oneself, then the real sending, which lands on the communication's
| own page — where its progress is followed, its failures retried, and from
| where it can be written again next season.
*/

const COMPOSE_COMPONENT = 'pages::club-admin.communications.index';
const HISTORY_COMPONENT = 'pages::club-admin.communications.history';
const SHOW_COMPONENT = 'pages::club-admin.communications.show';

beforeEach(function (): void {
    Carbon::setTestNow('2026-10-15');

    Club::factory()->ownClub()->create(['email_contact' => 'club@example.com']);
    $this->season = Season::factory()->create(['start_at' => '2026-09-01', 'end_at' => '2027-06-30', 'is_active' => true]);
    $this->author = User::factory()->isCommitteeMember()->create(['email' => 'author@example.com', 'birthdate' => null]);
    actingAs($this->author);
});

function composeMember(Season $season, string $email): User
{
    $member = User::factory()->create(['email' => $email, 'birthdate' => '1990-01-01']);
    Subscription::factory()->create(['user_id' => $member->id, 'season_id' => $season->id, 'status' => 'confirmed']);

    return $member;
}

describe('writing', function (): void {

    it('answers to the author by default', function (): void {
        Livewire::test(COMPOSE_COMPONENT)->assertSet('replyTo', 'author@example.com');
    });

    it('previews the message as it will be rendered, html escaped', function (): void {
        Livewire::test(COMPOSE_COMPONENT)
            ->set('body', 'Hello **everyone** <b>bold</b>')
            ->assertSeeHtml('<strong>everyone</strong>')
            ->assertDontSeeHtml('<b>bold</b>');
    });

    it('drops an invitation block into the message', function (): void {
        $tournament = Tournament::factory()->create(['name' => 'Christmas tournament', 'status' => TournamentStatusEnum::PUBLISHED]);

        Livewire::test(COMPOSE_COMPONENT)
            ->set('body', 'Come along!')
            ->set('invitationTarget', 'tournament')
            ->set('invitationId', $tournament->id)
            ->call('insertInvitation')
            ->assertSet('body', fn (string $body): bool => str_starts_with($body, 'Come along!')
                && str_contains($body, '**Christmas tournament**')
                && str_contains($body, route('communications.invitation', ['tournament', $tournament->id])));
    });

    it('sends a test to the author only', function (): void {
        Mail::fake();
        composeMember($this->season, 'member@example.com');

        Livewire::test(COMPOSE_COMPONENT)
            ->set('subject', 'Club dinner')
            ->set('body', 'See you there.')
            ->call('sendTest');

        Mail::assertSent(CommunicationMail::class, 1);
        Mail::assertSent(CommunicationMail::class, fn (CommunicationMail $mail): bool => $mail->hasTo('author@example.com'));
    });

    it('requires a subject and a body', function (): void {
        Livewire::test(COMPOSE_COMPONENT)
            ->call('send')
            ->assertHasErrors(['subject' => 'required', 'body' => 'required']);

        expect(Communication::count())->toBe(0);
    });

    it('sends to the audience, then opens the communication page', function (): void {
        Mail::fake();
        composeMember($this->season, 'one@example.com');
        composeMember($this->season, 'two@example.com');

        $component = Livewire::test(COMPOSE_COMPONENT)
            ->set('subject', 'Club dinner')
            ->set('body', 'See you there.')
            ->call('send');

        $communication = Communication::sole();

        $component->assertRedirect(route('admin.communications.show', $communication));
        expect($communication->recipient_count)->toBe(2);
        Mail::assertSent(CommunicationMail::class, 2);
    });

    it('starts from a past communication, with its filters', function (): void {
        $past = Communication::factory()->create([
            'subject' => 'Reaffiliation 2025',
            'body' => 'Come back!',
            'criteria' => ['base' => 'former', 'licences' => ['competitive'], 'genders' => [], 'age_bands' => []],
        ]);

        Livewire::withQueryParams(['from' => $past->id])
            ->test(COMPOSE_COMPONENT)
            ->assertSet('subject', 'Reaffiliation 2025')
            ->assertSet('body', 'Come back!')
            ->assertSet('base', 'former')
            ->assertSet('licences', ['competitive']);
    });
});

describe('the history', function (): void {

    it('lists what was sent, newest first', function (): void {
        Communication::factory()->create(['subject' => 'Older', 'sent_at' => now()->subWeek()]);
        Communication::factory()->create(['subject' => 'Newer', 'sent_at' => now()]);

        Livewire::test(HISTORY_COMPONENT)->assertSeeInOrder(['Newer', 'Older']);
    });

    it('is closed to a member without a seat', function (): void {
        actingAs(User::factory()->create());

        get(route('admin.communications.history'))->assertForbidden();
    });

    it('follows the progress and retries the failures', function (): void {
        $communication = Communication::factory()->create(['recipient_count' => 3]);
        CommunicationRecipient::factory()->count(2)->create(['communication_id' => $communication->id]);
        $failed = CommunicationRecipient::factory()->failed()->create(['communication_id' => $communication->id, 'email' => 'bounced@example.com']);

        Livewire::test(SHOW_COMPONENT, ['communication' => $communication])
            ->assertSee('2 / 3')
            ->assertSee('bounced@example.com')
            ->assertSee('Connection refused')
            ->call('retryFailed');

        expect($failed->fresh()->status)->toBe(CommunicationRecipient::STATUS_SENT);
    });
});

it('renders every component of the three screens', function (): void {
    $communication = Communication::factory()->create();
    CommunicationRecipient::factory()->failed()->create(['communication_id' => $communication->id]);
    composeMember($this->season, 'member@example.com');

    foreach ([
        Livewire::test(COMPOSE_COMPONENT)->set('invitationTarget', 'tournament')->html(),
        Livewire::test(HISTORY_COMPONENT)->html(),
        Livewire::test(SHOW_COMPONENT, ['communication' => $communication])->html(),
    ] as $html) {
        expect($html)->not->toContain('<x-');
    }
});
