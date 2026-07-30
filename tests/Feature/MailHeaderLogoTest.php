<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Contact\Models\Contact;
use App\Mail\ContactFormNotificationEmail;

/*
| Issue #45: the shared markdown mail header (x-mail::message) embedded the brand
| logo as an SVG sized only through CSS. Mail clients that strip SVG or ignore
| CSS on <img> rendered it at its 2834px intrinsic size — a giant logo. The header
| is shared by every markdown mail and MailMessage notification, so asserting it
| through one mailable covers them all.
*/

it('renders the header logo as a bounded raster PNG instead of an unsized SVG', function (): void {
    $contact = Contact::factory()->create();

    $mailable = new ContactFormNotificationEmail($contact);

    // Raster PNG: Gmail/Outlook strip inline SVG.
    $mailable->assertSeeInHtml('images/logo-club-email.png');
    // Explicit HTML dimensions: clients that ignore CSS still cap the size.
    $mailable->assertSeeInHtml('width="40" height="40"', escape: false);
    // The unsized SVG must be gone.
    $mailable->assertDontSeeInHtml('logo-club.svg');
});
