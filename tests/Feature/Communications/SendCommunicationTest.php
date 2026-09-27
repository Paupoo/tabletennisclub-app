<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Communications\Actions\RetryFailedRecipients;
use App\Domains\ClubAdmin\Communications\Actions\SendCommunication;
use App\Domains\ClubAdmin\Communications\Actions\SendTestCommunication;
use App\Domains\ClubAdmin\Communications\Data\AudienceCriteria;
use App\Domains\ClubAdmin\Communications\Models\Communication;
use App\Domains\ClubAdmin\Communications\Models\CommunicationRecipient;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\AudienceLicence;
use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Jobs\SendCommunicationJob;
use Illuminate\Support\Carbon;
use Symfony\Component\Mime\Email;

/*
| A message written in the application goes to every address of the audience,
| one message each, from the club and answered to its author. Each address is
| greeted as who it is: the member themself, or the parent of the children it
| answers for.
*/

beforeEach(function (): void {
    Carbon::setTestNow('2026-10-15');
    config(['mail.from.address' => 'noreply@club.example']);

    Club::factory()->ownClub()->create(['email_contact' => 'club@example.com']);
    $this->season = Season::factory()->create([
        'start_at' => '2026-09-01',
        'end_at' => '2027-06-30',
        'is_active' => true,
    ]);
    $this->author = User::factory()->isCommitteeMember()->create([
        'first_name' => 'Claire',
        'last_name' => 'Secrétaire',
        'email' => 'claire@example.com',
        'birthdate' => null,
        'committee_role' => CommitteeRolesEnum::cases()[0],
    ]);
});

/** @param  array<string, mixed>  $attributes */
function sendCommunicationMember(Season $season, array $attributes = [], bool $competitive = true): User
{
    $member = User::factory()->create(array_merge(['birthdate' => '1990-05-01'], $attributes));
    Subscription::factory()->create([
        'user_id' => $member->id,
        'season_id' => $season->id,
        'status' => 'confirmed',
        'is_competitive' => $competitive,
    ]);

    return $member;
}

/**
 * Every message handed to the array transport, keyed by its only recipient.
 *
 * @return array<string, Email>
 */
function sendCommunicationSentByAddress(): array
{
    return collect(app('mailer')->getSymfonyTransport()->messages())
        ->map(fn ($sent): Email => $sent->getOriginalMessage())
        ->keyBy(function (Email $email): string {
            expect($email->getTo())->toHaveCount(1);

            return $email->getTo()[0]->getAddress();
        })
        ->all();
}

function sendCommunicationSend(User $author, ?AudienceCriteria $criteria = null, string $body = 'See you on **Saturday**.'): Communication
{
    return app(SendCommunication::class)(
        author: $author,
        criteria: $criteria ?? new AudienceCriteria,
        subject: 'Club dinner',
        body: $body,
        replyTo: 'claire@example.com',
    );
}

it('writes to each address once, from the club, answered to the author', function (): void {
    sendCommunicationMember($this->season, ['email' => 'arthur@example.com']);
    $lea = sendCommunicationMember($this->season, ['email' => null, 'birthdate' => '2014-01-01']);
    $lea->guardians()->attach(Guardian::factory()->create(['email' => 'mum@example.com']));
    $lea->guardians()->attach(Guardian::factory()->create(['email' => 'dad@example.com']));

    sendCommunicationSend($this->author);

    $sent = sendCommunicationSentByAddress();

    expect(array_keys($sent))->toEqualCanonicalizing(['arthur@example.com', 'mum@example.com', 'dad@example.com']);

    foreach ($sent as $email) {
        expect($email->getSubject())->toBe('Club dinner')
            ->and($email->getFrom()[0]->getAddress())->toBe('noreply@club.example')
            ->and($email->getReplyTo()[0]->getAddress())->toBe('claire@example.com')
            ->and($email->getHtmlBody())->toMatch('/<strong[^>]*>Saturday<\/strong>/');
    }
});

it('greets a member by name, and a parent as the parent of their children', function (): void {
    sendCommunicationMember($this->season, ['first_name' => 'Arthur', 'email' => 'arthur@example.com']);
    $parent = Guardian::factory()->create(['email' => 'parent@example.com']);
    foreach (['Léa', 'Tom'] as $name) {
        sendCommunicationMember($this->season, ['first_name' => $name, 'email' => null, 'birthdate' => '2014-01-01'])
            ->guardians()->attach($parent);
    }

    sendCommunicationSend($this->author);

    $sent = sendCommunicationSentByAddress();

    expect($sent['arthur@example.com']->getHtmlBody())->toContain(__('Hello :names,', ['names' => 'Arthur']))
        ->and($sent['parent@example.com']->getHtmlBody())
        ->toContain(e(__('Hello, parent of :names,', ['names' => 'Léa ' . __('and') . ' Tom'])));
});

it('greets a parent who is a member by their own name', function (): void {
    $marc = sendCommunicationMember($this->season, ['first_name' => 'Marc', 'email' => 'marc@example.com']);
    $guardianRecord = Guardian::factory()->create(['email' => 'marc@example.com', 'user_id' => $marc->id]);
    sendCommunicationMember($this->season, ['first_name' => 'Léa', 'email' => null, 'birthdate' => '2014-01-01'])
        ->guardians()->attach($guardianRecord);

    sendCommunicationSend($this->author);

    expect(sendCommunicationSentByAddress()['marc@example.com']->getHtmlBody())
        ->toContain(__('Hello :names,', ['names' => 'Marc']));
});

it('signs with the author and the club', function (): void {
    sendCommunicationMember($this->season, ['email' => 'arthur@example.com']);

    sendCommunicationSend($this->author);

    expect(sendCommunicationSentByAddress()['arthur@example.com']->getHtmlBody())
        ->toContain('Claire Secrétaire')
        ->toContain(e($this->author->committee_role->label()))
        ->toContain('C.T.T Ottignies-Blocry');
});

it('escapes the html an author types', function (): void {
    sendCommunicationMember($this->season, ['email' => 'arthur@example.com']);

    sendCommunicationSend($this->author, body: 'Hi <script>alert(1)</script> [x](javascript:alert(1))');

    expect(sendCommunicationSentByAddress()['arthur@example.com']->getHtmlBody())
        ->not->toContain('<script>')
        ->not->toContain('javascript:');
});

it('keeps the communication and each address it went to', function (): void {
    sendCommunicationMember($this->season, ['email' => 'competitor@example.com'], competitive: true);
    sendCommunicationMember($this->season, ['email' => 'leisure@example.com'], competitive: false);

    $communication = sendCommunicationSend($this->author, new AudienceCriteria(licences: [AudienceLicence::Competitive]));

    expect($communication->fresh())
        ->author_id->toBe($this->author->id)
        ->subject->toBe('Club dinner')
        ->member_count->toBe(1)
        ->recipient_count->toBe(1)
        ->sent_at->not->toBeNull()
        ->and($communication->criteria['licences'])->toBe(['competitive'])
        ->and($communication->recipients()->pluck('email')->all())->toBe(['competitor@example.com'])
        ->and($communication->recipients()->sole()->status)->toBe(CommunicationRecipient::STATUS_SENT);
});

it('marks an address the mail server kept refusing as failed', function (): void {
    $recipient = CommunicationRecipient::factory()->create(['status' => CommunicationRecipient::STATUS_PENDING, 'sent_at' => null]);

    (new SendCommunicationJob($recipient->id))->failed(new RuntimeException('Connection refused'));

    expect($recipient->fresh())
        ->status->toBe(CommunicationRecipient::STATUS_FAILED)
        ->error->toBe('Connection refused');
});

it('sends again to the failed addresses only', function (): void {
    $communication = Communication::factory()->create(['author_id' => $this->author->id]);
    $failed = CommunicationRecipient::factory()->failed()->create(['communication_id' => $communication->id, 'email' => 'failed@example.com']);
    CommunicationRecipient::factory()->create(['communication_id' => $communication->id, 'email' => 'already@example.com']);

    app(RetryFailedRecipients::class)($communication);

    expect(array_keys(sendCommunicationSentByAddress()))->toBe(['failed@example.com'])
        ->and($failed->fresh())
        ->status->toBe(CommunicationRecipient::STATUS_SENT)
        ->error->toBeNull();
});

it('sends a test to the author alone, and records nothing', function (): void {
    sendCommunicationMember($this->season, ['email' => 'arthur@example.com']);

    app(SendTestCommunication::class)($this->author, 'Club dinner', 'See you on **Saturday**.', 'claire@example.com');

    $sent = sendCommunicationSentByAddress();

    expect(array_keys($sent))->toBe(['claire@example.com'])
        ->and($sent['claire@example.com']->getSubject())->toBe('[' . __('Test') . '] Club dinner')
        ->and($sent['claire@example.com']->getHtmlBody())->toContain(__('Hello :names,', ['names' => 'Claire']))
        ->and(Communication::count())->toBe(0);
});
