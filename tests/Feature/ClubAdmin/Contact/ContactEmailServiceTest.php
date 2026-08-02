<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Contact\Models\Contact;
use App\Domains\ClubAdmin\Contact\Models\EmailTemplate;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\ContactReasonEnum;
use App\Mail\CustomEmail;
use App\Services\ClubAdmin\Contact\ContactEmailService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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
    $contact->status = 'new';

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

describe('sendCustom()', function (): void {
    beforeEach(function (): void {
        Mail::fake();

        $this->service = new ContactEmailService;
        $this->contact = makeContact();
        $this->user = makeUser();
        $this->mailData = ['subject' => 'Hello', 'body' => 'Test body'];
    });

    it('Sends custom e-mail to contact only when send_copy is false', function (): void {

        $this->service->sendCustom($this->contact, $this->mailData, $this->user, false);

        Mail::assertQueuedCount(1);
        Mail::assertQueued(CustomEmail::class, fn ($mail) => $mail->hasTo($this->contact->email));
    });

    it('Sends custom e-mail to contact and to user when send_copy is true', function (): void {
        $this->service->sendCustom($this->contact, $this->mailData, $this->user, true);

        Mail::assertQueued(CustomEmail::class, fn ($mail) => $mail->hasTo($this->contact->email));
        Mail::assertQueued(CustomEmail::class, fn ($mail) => $mail->hasTo($this->user->email));
        Mail::assertQueuedCount(2);

    });

    it('Writes a log after sending', function (): void {
        // Watch out: Log checks must be done before launching the function that initiate them
        Log::shouldReceive('info')->once()->withArgs(function ($message, array $context): bool {
            return $context['contact_id'] === $this->contact->id
                && $context['subject'] === $this->mailData['subject']
                && $context['admin_user'] === $this->user->id;
        });
        $this->service->sendCustom($this->contact, $this->mailData, $this->user, true);

    });
})->group('contact');

// ─────────────────────────────────────────────────────────────
// Suite : sendTemplate()  — now resolves templates from the DB
// ─────────────────────────────────────────────────────────────

describe('sendTemplate()', function (): void {

    beforeEach(function (): void {
        Mail::fake();
        Log::spy();

        $this->service = new ContactEmailService;
    });

    it('sends a CustomEmail with the resolved subject and body to the contact', function (): void {
        config()->set('app.name', 'Mon Club TT');

        EmailTemplate::factory()->create([
            'key' => 'welcome',
            'subject' => 'Bienvenue {{first_name}}',
            'body' => 'Bonjour {{first_name}}, bienvenue chez {{club_name}} ({{interest}}).',
            'apply_status' => 'processed',
        ]);

        $contact = Contact::factory()->create([
            'first_name' => 'Alice',
            'status' => 'new',
            'interest' => ContactReasonEnum::JOIN_US,
        ]);

        $this->service->sendTemplate($contact, 'welcome');

        Mail::assertQueued(CustomEmail::class, function (CustomEmail $mail) use ($contact): bool {
            return $mail->hasTo($contact->email)
                && $mail->emailData['subject'] === 'Bienvenue Alice'
                && str_contains($mail->emailData['message'], 'bienvenue chez Mon Club TT')
                && str_contains($mail->emailData['message'], ContactReasonEnum::JOIN_US->getLabel());
        });
    });

    it('returns a success message string', function (): void {
        EmailTemplate::factory()->create(['key' => 'welcome']);
        $contact = Contact::factory()->create();

        expect($this->service->sendTemplate($contact, 'welcome'))->toBeString()->toContain('welcome');
    });

    it('applies the template apply_status to the contact (processed)', function (): void {
        EmailTemplate::factory()->appliesStatus('processed')->create(['key' => 'welcome']);
        $contact = Contact::factory()->create(['status' => 'new']);

        $this->service->sendTemplate($contact, 'welcome');

        expect($contact->fresh()->status)->toBe('processed');
    });

    it('applies the template apply_status to the contact (rejected)', function (): void {
        EmailTemplate::factory()->appliesStatus('rejected')->create(['key' => 'polite_decline']);
        $contact = Contact::factory()->create(['status' => 'new']);

        $this->service->sendTemplate($contact, 'polite_decline');

        expect($contact->fresh()->status)->toBe('rejected');
    });

    it('does not change the status when the template carries no apply_status', function (): void {
        EmailTemplate::factory()->create(['key' => 'request_info', 'apply_status' => null]);
        $contact = Contact::factory()->create(['status' => 'new']);

        $this->service->sendTemplate($contact, 'request_info');

        expect($contact->fresh()->status)->toBe('new');
    });

    it('ignores an invalid apply_status and logs a warning', function (): void {
        Log::shouldReceive('warning')->once();
        Log::shouldReceive('info')->zeroOrMoreTimes();

        EmailTemplate::factory()->appliesStatus('bogus_status')->create(['key' => 'weird']);
        $contact = Contact::factory()->create(['status' => 'new']);

        $this->service->sendTemplate($contact, 'weird');

        expect($contact->fresh()->status)->toBe('new');
    });

    it('treats a leftover pending apply_status as invalid and leaves the contact untouched', function (): void {
        Log::shouldReceive('warning')->once();
        Log::shouldReceive('info')->zeroOrMoreTimes();

        EmailTemplate::factory()->appliesStatus('pending')->create(['key' => 'legacy']);
        $contact = Contact::factory()->create(['status' => 'processed']);

        $this->service->sendTemplate($contact, 'legacy');

        expect($contact->fresh()->status)->toBe('processed');
    });

    it('throws InvalidArgumentException for an unknown template key', function (): void {
        $contact = Contact::factory()->create();

        expect(fn () => $this->service->sendTemplate($contact, 'unknown_template'))
            ->toThrow(InvalidArgumentException::class, 'unknown_template');
    });

    it('writes an info log after sending', function (): void {
        Log::shouldReceive('info')->once()->withArgs(function ($message, array $context): bool {
            return isset($context['contact_id']) && isset($context['template']);
        });

        EmailTemplate::factory()->create(['key' => 'welcome']);
        $contact = Contact::factory()->create();

        $this->service->sendTemplate($contact, 'welcome');
    });
})->group('contact');

// ─────────────────────────────────────────────────────────────
// Rendering — guards against payloads missing keys CustomEmail needs.
// Mail::fake() does NOT render the mailable, so these call ->render()
// explicitly to exercise CustomEmail::content().
// ─────────────────────────────────────────────────────────────

describe('CustomEmail rendering', function (): void {
    beforeEach(function (): void {
        $this->service = new ContactEmailService;
        Mail::fake();
    });

    it('renders the free custom email without missing-key errors', function (): void {
        $contact = Contact::factory()->create(['first_name' => 'Zoé']);
        $user = User::factory()->create(['first_name' => 'Marie', 'last_name' => 'Secrétaire']);

        $this->service->sendCustom($contact, ['subject' => 'Bonjour', 'body' => 'Salut Zoé !'], $user);

        Mail::assertQueued(CustomEmail::class, function (CustomEmail $mail): true {
            expect($mail->render())->toContain('Salut Zoé !');

            return true;
        });
    });

    it('renders a custom email with a club copy without errors', function (): void {
        $contact = Contact::factory()->create();
        $user = User::factory()->create();

        $this->service->sendCustom($contact, ['subject' => 'Sujet', 'body' => 'Corps du message'], $user, sendCopy: true);

        Mail::assertQueued(CustomEmail::class, fn (CustomEmail $mail): true => is_string($mail->render()));
    });

    /*
     * The rendered mail goes through CssToInlineStyles, which rewrites every tag
     * it keeps into `<script style="...">`. Asserting on the literal payload
     * would therefore pass even when the tag survives — these assert on the bare
     * `<script` opening instead.
     */
    it('escapes HTML typed into the body instead of rendering it', function (): void {
        $contact = Contact::factory()->create();
        $user = User::factory()->create();

        $this->service->sendCustom($contact, [
            'subject' => 'Sujet',
            'body' => 'Bonjour <script>alert(1)</script> et <b>gras</b>',
        ], $user);

        Mail::assertQueued(CustomEmail::class, function (CustomEmail $mail): true {
            expect($mail->render())
                ->not->toContain('<script')
                ->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')
                ->toContain('&lt;b&gt;gras&lt;/b&gt;');

            return true;
        });
    });

    it('escapes HTML the copy sent to the club carries too', function (): void {
        $contact = Contact::factory()->create();
        $user = User::factory()->create();

        $this->service->sendCustom($contact, [
            'subject' => 'Sujet',
            'body' => '<script>alert(1)</script>',
        ], $user, sendCopy: true);

        Mail::assertQueued(CustomEmail::class, function (CustomEmail $mail): true {
            expect($mail->render())
                ->not->toContain('<script')
                ->toContain('&lt;script&gt;alert(1)&lt;/script&gt;');

            return true;
        });
    });

    it('escapes contact details a visitor typed into the public form', function (): void {
        $contact = Contact::factory()->create(['first_name' => '<script>alert(1)</script>']);
        $user = User::factory()->create();

        $this->service->sendCustom($contact, [
            'subject' => 'Sujet',
            'body' => 'Bonjour {{ $contact->first_name }}',
        ], $user);

        Mail::assertQueued(CustomEmail::class, function (CustomEmail $mail): true {
            expect($mail->render())
                ->not->toContain('<script')
                ->toContain('&lt;script&gt;alert(1)&lt;/script&gt;');

            return true;
        });
    });

    it('still turns line breaks into <br> and bare URLs into links', function (): void {
        $contact = Contact::factory()->create();
        $user = User::factory()->create();

        $this->service->sendCustom($contact, [
            'subject' => 'Sujet',
            'body' => "Première ligne\nSeconde ligne https://ctt-ottignies.be/inscription",
        ], $user);

        Mail::assertQueued(CustomEmail::class, function (CustomEmail $mail): true {
            expect($mail->render())
                ->toContain('<br')
                ->toContain('href="https://ctt-ottignies.be/inscription"');

            return true;
        });
    });

    it('renders a database template email', function (): void {
        EmailTemplate::factory()->create([
            'key' => 'welcome',
            'subject' => 'Bienvenue',
            'body' => 'Bonjour {{first_name}}',
        ]);
        $contact = Contact::factory()->create(['first_name' => 'Lou']);

        $this->service->sendTemplate($contact, 'welcome');

        Mail::assertQueued(CustomEmail::class, function (CustomEmail $mail): true {
            expect($mail->render())->toContain('Bonjour Lou');

            return true;
        });
    });
})->group('contact');
