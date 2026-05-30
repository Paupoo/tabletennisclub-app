<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Contact\StoreContactAction;
use App\Domains\Shared\Enums\ContactReasonEnum;
use App\Mail\ContactFormConfirmationEmail;
use App\Mail\ContactFormNotificationEmail;
use App\Domains\ClubAdmin\Contact\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

it('creates a contact record in the database', function (): void {
    Mail::fake();

    (new StoreContactAction)->execute([
        'first_name' => 'Alice',
        'last_name' => 'Martin',
        'email' => 'alice@test.com',
        'interest' => ContactReasonEnum::JOIN_US->value,
        'message' => 'Bonjour le club',
    ]);

    expect(Contact::where('email', 'alice@test.com')->exists())->toBeTrue();
});

it('sends a confirmation email to the contact', function (): void {
    Mail::fake();

    (new StoreContactAction)->execute([
        'first_name' => 'Alice',
        'last_name' => 'Martin',
        'email' => 'alice@test.com',
        'interest' => ContactReasonEnum::JOIN_US->value,
        'message' => 'Bonjour',
    ]);

    Mail::assertSent(ContactFormConfirmationEmail::class, fn ($mail): bool => $mail->hasTo('alice@test.com'));
});

it('sends a notification email to the club admin', function (): void {
    Mail::fake();

    (new StoreContactAction)->execute([
        'first_name' => 'Bob',
        'last_name' => 'Durand',
        'email' => 'bob@test.com',
        'interest' => ContactReasonEnum::JOIN_US->value,
        'message' => 'Question',
    ]);

    Mail::assertQueued(ContactFormNotificationEmail::class);
});

it('returns the created Contact instance', function (): void {
    Mail::fake();

    $contact = (new StoreContactAction)->execute([
        'first_name' => 'Clara',
        'last_name' => 'Petit',
        'email' => 'clara@test.com',
        'interest' => ContactReasonEnum::JOIN_US->value,
        'message' => 'Test',
    ]);

    expect($contact)->toBeInstanceOf(Contact::class);
    expect($contact->exists)->toBeTrue();
});
