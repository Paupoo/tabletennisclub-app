<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Contact\Models\Contact;
use App\Domains\ClubAdmin\Fines\Models\Fine;
use App\Domains\ClubAdmin\Fines\Notifications\FineIssuedNotification;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Notifications\MeetingCancelledNotification;
use App\Mail\ContactFormNotificationEmail;
use App\Support\MailText;

/*
|--------------------------------------------------------------------------
| Line breaks of free text in e-mails
|--------------------------------------------------------------------------
|
| `MailMessage::line()` joins the lines of a plain string with a space, and a
| markdown mail folds a single line break into the paragraph. A reason typed
| on three lines reached the member as one block. MailText keeps the breaks
| and escapes the rest.
|
*/

it('turns line breaks into <br> and escapes everything else', function (): void {
    expect((string) MailText::keepingLineBreaks("Salle fermée\n<b>demain</b>"))
        ->toBe("Salle fermée<br>\n&lt;b&gt;demain&lt;/b&gt;");
});

it('keeps the line breaks of a meeting cancellation message', function (): void {
    $member = User::factory()->create();
    $notification = new MeetingCancelledNotification(Meeting::factory()->create(), "Salle fermée\nOn reprogramme bientôt");

    $html = (string) $notification->toMail($member)->render();

    expect($html)->toContain('Salle fermée<br>')
        ->toContain('On reprogramme bientôt');
});

it('keeps the line breaks of a fine\'s pedagogical message', function (): void {
    $fine = Fine::factory()->create(['pedagogical_message' => "Premier rappel\nMerci de prévenir"]);

    $html = (string) new FineIssuedNotification($fine)
        ->toMail($fine->user)->render();

    expect($html)->toContain('Premier rappel<br>');
});

it('keeps the line breaks of a contact form message sent to the club', function (): void {
    $contact = Contact::factory()->make(['message' => "Bonjour\nJe voudrais essayer"]);

    expect(new ContactFormNotificationEmail($contact)->render())
        ->toContain('Bonjour<br>');
});
