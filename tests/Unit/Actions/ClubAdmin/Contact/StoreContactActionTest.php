<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Contact\StoreContactAction;
use App\Mail\ContactFormConfirmationEmail;
use App\Mail\ContactFormNotificationEmail;
use App\Models\ClubAdmin\Contact\Contact;
use Illuminate\Support\Facades\Mail;

describe('StoreContactAction', function () {
    beforeEach(function () {
        Mail::fake();
        // Override the club email for testing
        config(['app.club_email' => 'test-club@example.com']);
    });

    it('creates a contact with valid data', function () {
        $action = new StoreContactAction;

        $data = [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'jean@example.com',
            'phone' => '06 12 34 56 78',
            'interest' => 'JOIN_US',
            'message' => 'Je suis intéressé par une adhésion.',
            'membership_family_members' => 2,
            'membership_competitors' => 1,
            'membership_training_sessions' => 0,
            'membership_total_cost' => 150,
        ];

        $contact = $action->execute($data);

        expect($contact)
            ->toBeInstanceOf(Contact::class)
            ->id->not->toBeNull()
            ->and($contact)->first_name->toBe('Jean')
            ->last_name->toBe('Dupont')
            ->email->toBe('jean@example.com')
            ->interest->name->toBe('JOIN_US');

        $this->assertDatabaseHas('contacts', [
            'email' => 'jean@example.com',
            'first_name' => 'Jean',
        ]);
    });

    it('sends confirmation email to contact', function () {
        $action = new StoreContactAction;

        $data = [
            'first_name' => 'Marie',
            'last_name' => 'Martin',
            'email' => 'marie@example.com',
            'phone' => '07 89 90 12 34',
            'interest' => 'INFO_INTERCLUBS',
            'message' => 'Je voudrais plus d\'informations concernant les interclubs',
        ];

        $contact = $action->execute($data);

        Mail::assertSent(ContactFormConfirmationEmail::class, function ($mail) use ($contact) {
            return $mail->hasTo($contact->email);
        });
    });

    it('sends notification email to club admin', function () {
        $action = new StoreContactAction;

        $data = [
            'first_name' => 'Pierre',
            'last_name' => 'Bernard',
            'email' => 'pierre@example.com',
            'interest' => 'PARTNERSHIP',
            'message' => 'Je souhaiterais sponsoriser votre club',
        ];

        $action->execute($data);

        Mail::assertQueued(ContactFormNotificationEmail::class, function ($mail) {
            return $mail->hasTo(config('app.club_email'));
        });
    });

    it('sends both emails when contact is created', function () {
        $action = new StoreContactAction;

        $data = [
            'first_name' => 'Sophie',
            'last_name' => 'Laurent',
            'email' => 'sophie@example.com',
            'interest' => 'TRIAL',
            'message' => 'Je voudrais faire un essai',
        ];

        $contact = $action->execute($data);

        Mail::assertSent(ContactFormConfirmationEmail::class);
        Mail::assertQueued(ContactFormNotificationEmail::class);
        $sentCount = count(Mail::sent(ContactFormConfirmationEmail::class));
        $queuedCount = count(Mail::queued(ContactFormNotificationEmail::class));
        expect($sentCount + $queuedCount)->toBe(2);
    });

    it('returns the created contact even if email sending fails', function () {
        Mail::shouldReceive('to->send')
            ->andThrow(new Exception('Mail service error'));

        $action = new StoreContactAction;

        $data = [
            'first_name' => 'Thomas',
            'last_name' => 'Dubois',
            'email' => 'thomas@example.com',
            'interest' => 'PARTNERSHIP',
            'message' => 'Test message',
        ];

        expect(fn () => $action->execute($data))
            ->toThrow(Exception::class);

        // Contact should still be created
        $this->assertDatabaseHas('contacts', [
            'email' => 'thomas@example.com',
        ]);
    });

    it('stores minimal contact data', function () {
        $action = new StoreContactAction;

        $data = [
            'first_name' => 'Luc',
            'last_name' => 'Blanc',
            'email' => 'luc@example.com',
            'interest' => 'JOIN_US',
            'message' => 'Test minimal',
        ];

        $contact = $action->execute($data);

        expect($contact)->id->not->toBeNull()
            ->phone->toBeNull()
            ->membership_family_members->toBeNull();
    });
});
