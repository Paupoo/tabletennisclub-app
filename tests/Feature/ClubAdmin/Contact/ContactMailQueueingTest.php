<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Contact\Models\Contact;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Mail\ContactFormConfirmationEmail;
use App\Mail\ContactFormNotificationEmail;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Mail;

/*
|--------------------------------------------------------------------------
| The public contact form must not depend on SMTP
|--------------------------------------------------------------------------
|
| This is the only form an anonymous visitor can submit. It stored the contact
| and then sent two mails inline, inside the request, and rethrew on failure —
| against the comment sitting right above the `throw`, which says an email
| failure must not undo the contact. The visitor was told the message had not
| gone through while it sat in the database, so they sent it again.
|
| The mails are queued now, and a failure to build them no longer costs the
| visitor their message.
|
*/

beforeEach(function (): void {
    $this->withoutMiddleware(PreventRequestForgery::class);
    $this->withoutMiddleware(ThrottleRequests::class);
    $this->withSession([
        'captcha' => ['a' => 3, 'b' => 2, 'operation' => '+'],
        'captcha_created_at' => time(),
    ]);
});

/**
 * @return array<string, mixed>
 */
function contactPayload(): array
{
    return [
        'first_name' => 'Jean',
        'last_name' => 'Dupont',
        'email' => 'jean@test.com',
        'interest' => 'JOIN_US',
        'message' => 'Hello',
        'consent' => true,
        'captcha' => 5,
    ];
}

it('queues both mails rather than sending them inside the request', function (): void {
    Mail::fake();
    Club::factory()->create(['email_contact' => 'club@test.com', 'is_own_club' => true]);
    Club::forgetOwnClub();

    $this->post(route('contact.store'), contactPayload())
        ->assertRedirect(route('home') . '#contact')
        ->assertSessionHas('success');

    Mail::assertQueued(ContactFormConfirmationEmail::class);
    Mail::assertQueued(ContactFormNotificationEmail::class);
    Mail::assertNothingSent();
});

it('keeps the message and thanks the visitor when the club has no contact address', function (): void {
    Mail::fake();
    Club::forgetOwnClub();

    $this->post(route('contact.store'), contactPayload())
        ->assertRedirect(route('home') . '#contact')
        ->assertSessionHas('success')
        ->assertSessionMissing('error');

    expect(Contact::where('email', 'jean@test.com')->exists())->toBeTrue();
});
