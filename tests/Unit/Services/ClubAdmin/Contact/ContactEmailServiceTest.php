<?php

declare(strict_types=1);

namespace Tests\Unit\Services\ClubAdmin\Contact;

use App\Mail\CustomEmail;
use App\Mail\MembershipInfoDetailEmail;
use App\Mail\PoliteDeclineEmail;
use App\Mail\RequestInfoEmail;
use App\Mail\WelcomeEmail;
use App\Models\ClubAdmin\Contact\Contact;
use App\Models\ClubAdmin\Users\User;
use App\Services\ClubAdmin\Contact\ContactEmailService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Mockery;

// ─────────────────────────────────────────────────────────────
// Shared helpers
// ─────────────────────────────────────────────────────────────

/**
 * Create mock with basic Contact data
 */
function makeContact(int $id = 1, string $email = 'contact@example.com'): Contact
{
    $contact = Mockery::mock(Contact::class)->makePartial();
    $contact->id = $id;
    $contact->email = $email;
    $contact->status = 'pending';

    return $contact;
}

/**
 * Create mock with basic User data
 */
function makeUser(int $id = 99, string $email = 'admin@example.com'): User
{
    $user = Mockery::mock(User::class)->makePartial();
    $user->id = $id;
    $user->email = $email;

    return $user;
}

// ─────────────────────────────────────────────────────────────
// Suite : sendCustom()
// ─────────────────────────────────────────────────────────────

describe('sendCustom()', function () {
    beforeEach(function () {
        Mail::fake();

        $this->service = new ContactEmailService;
        $this->contact = makeContact();
        $this->user = makeUser();
        $this->mailData = ['subject' => 'Hello', 'body' => 'Test body'];
    });

    it('Sends custom e-mail to contact only when send_copy is false', function () {

        $this->service->sendCustom($this->contact, $this->mailData, $this->user, false);

        Mail::assertSentCount(1);
        Mail::assertSent(CustomEmail::class, fn ($mail) => $mail->hasTo($this->contact->email));
    });

    it('Sends custom e-mail to contact and to user when send_copy is true', function () {
        $this->service->sendCustom($this->contact, $this->mailData, $this->user, true);

        Mail::assertSent(CustomEmail::class, fn ($mail) => $mail->hasTo($this->contact->email));
        Mail::assertSent(CustomEmail::class, fn ($mail) => $mail->hasTo($this->user->email));
        Mail::assertSentCount(2);

    });

    it('Writes a log after sending', function () {
        // Watch out: Log checks must be done before launching the function that initiate them
        Log::shouldReceive('info')->once()->withArgs(function ($message, $context) {
            return $context['contact_id'] === $this->contact->id
                && $context['subject'] === $this->mailData['subject']
                && $context['admin_user'] === $this->user->id;
        });
        $this->service->sendCustom($this->contact, $this->mailData, $this->user, true);

    });
});

// ─────────────────────────────────────────────────────────────
// Suite : sendTemplate()
// ─────────────────────────────────────────────────────────────

describe('sendTemplate()', function () {

    beforeEach(function () {
        Mail::fake();
        Log::spy();

        $this->service = new ContactEmailService;
        $this->contact = makeContact();
    });

    // ── Templates simples ────────────────────────────────────

    it("Sends the welcome Email when template 'welcome' is called", function () {
        $result = $this->service->sendTemplate($this->contact, 'welcome');
        Mail::assertSent(WelcomeEmail::class, fn ($mail) => $mail->hasTo($this->contact->email));
        expect($result)->toContain('welcome');
    });

    it("Sends the MembershipInfoDetailEmail Email when template 'membership_info' is called", function () {
        $result = $this->service->sendTemplate($this->contact, 'membership_info');
        Mail::assertSent(MembershipInfoDetailEmail::class, fn ($mail) => $mail->hasTo($this->contact->email));
        expect($result)->toContain('membership_info');
    });

    it("Sends the RequestInfoEmail Email when template 'request_info' is called", function () {
        $result = $this->service->sendTemplate($this->contact, 'request_info');
        Mail::assertSent(RequestInfoEmail::class, fn ($mail) => $mail->hasTo($this->contact->email));
        expect($result)->toContain('request_info');
    });

    it("Sends PoliteDeclineEmail and update the contact status to reject when 'polite_decline' is called", function () {
        // We need a true mock to check if update instruction received
        $contact = makeContact();
        $contact->shouldReceive('update')
            ->once()
            ->with(['status' => 'rejected']);

        $result = $this->service->sendTemplate($contact, 'polite_decline');
        Mail::assertSent(PoliteDeclineEmail::class, fn ($mail) => $mail->hasTo($contact->email));
        expect($result)->toContain('polite_decline');
    });

    // ── Template inconnu ─────────────────────────────────────

    it('lève une InvalidArgumentException pour un template inconnu', function () {
        expect(fn () => $this->service->sendTemplate($this->contact, 'unknown_template'))
            ->toThrow(InvalidArgumentException::class);
    });

    it('inclut le nom du template dans le message d\'exception', function () {
        expect(fn () => $this->service->sendTemplate($this->contact, 'bad_template'))
            ->toThrow(InvalidArgumentException::class, 'bad_template');
    });

    // ── Log ──────────────────────────────────────────────────

    it('écrit un log info après chaque envoi de template', function () {
        Log::shouldReceive('info')->once()->withArgs(function ($message, $context) {
            return isset($context['contact_id']) && isset($context['template']);
        });
        $this->service->sendTemplate($this->contact, 'welcome');

    });
});
