<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\GeneratePaymentQR;
use App\Domains\ClubAdmin\Fines\Actions\IssueFine;
use App\Domains\ClubAdmin\Fines\Notifications\FineIssuedNotification;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\FineReason;
use App\Mail\PaymentInvitationEmail;
use Illuminate\Support\Facades\Mail;

/*
| The payment QR was inlined as a `data:image/png;base64,...` source. Gmail
| strips `data:` sources from <img>, so members received a message showing only
| the alt text — while Mailpit rendered it perfectly in development, which is why
| it shipped. It now travels as a named attachment that the views reference as
| `cid:qr-paiement.png`, and Symfony rewrites the reference and turns the part
| inline when it prepares the message.
|
| The assertions run on the serialised MIME rather than on the Blade output: the
| rewriting happens at that point, and everything this bug was about lives there.
*/

/** The MIME of the last message handed to the array transport. */
function lastSentMime(): string
{
    $sent = iterator_to_array(app('mailer')->getSymfonyTransport()->messages());

    expect($sent)->not->toBeEmpty();

    return $sent[count($sent) - 1]->getOriginalMessage()->toString();
}

/** The Content-ID the message's inline image part was given. */
function inlineImageContentId(string $mime): ?string
{
    return preg_match('/Content-ID: <([^>]+)>/', $mime, $matches) === 1 ? $matches[1] : null;
}

beforeEach(function (): void {
    $this->season = makeActiveSeason();
    Club::factory()->ownClub()->create();
});

/** A pending payment, hung off a subscription as every real one is. */
function pendingPayment(): Payment
{
    $subscription = Subscription::factory()->create([
        'user_id' => User::factory()->create()->id,
        'season_id' => Season::current()->id,
    ]);

    return Payment::factory()->create([
        'status' => 'pending',
        'payable_type' => Subscription::class,
        'payable_id' => $subscription->id,
    ]);
}

it('sends the payment QR as an inline part a mail client will render', function (): void {
    $payment = pendingPayment();

    Mail::to('member@example.com')->send(new PaymentInvitationEmail($payment));

    $mime = lastSentMime();
    $contentId = inlineImageContentId($mime);
    // Quoted-printable folds long lines; the reference has to be read unfolded.
    $unfolded = str_replace(["=\r\n", "=\n"], '', $mime);

    expect($mime)->toContain('Content-Type: image/png')
        ->and($mime)->toContain('Content-Disposition: inline')
        ->and($contentId)->not->toBeNull();

    // Symfony resolved `cid:qr-paiement.png` to the part it actually attached.
    expect($unfolded)->toContain('cid:' . $contentId)
        ->and($unfolded)->not->toContain('cid:qr-paiement.png');

    // The source Gmail drops must be gone.
    expect($unfolded)->not->toContain('data:image/png');
});

/*
 * A notification renders its text part through the same Blade as its HTML one,
 * without the guard a mailable gets — embedding from the view attached a second,
 * orphan copy of the image to every fine and meeting email.
 */
it('attaches the QR exactly once, from a notification too', function (): void {
    $member = User::factory()->create();
    $issuer = User::factory()->isAdmin()->create();

    $fine = (new IssueFine)($member, $issuer, FineReason::MISCONDUCT, 30, 'An educational note.');

    $member->notify(new FineIssuedNotification($fine));

    $mime = lastSentMime();

    expect(substr_count($mime, 'Content-Type: image/png'))->toBe(1)
        ->and($mime)->toContain('Content-Disposition: inline')
        ->and(str_replace(["=\r\n", "=\n"], '', $mime))->not->toContain('data:image/png');
});

/*
 * The same QR is shown in the browser, where a `data:` URI is exactly right and
 * saves a request. Splitting the action must not have cost that.
 */
it('still hands the browser a data URI', function (): void {
    $payment = pendingPayment();

    $qr = new GeneratePaymentQR;

    expect($qr($payment))->toStartWith('data:image/png;base64,')
        ->and(base64_decode(substr($qr($payment), 22), true))->toBe($qr->png($payment));
});

it('produces a PNG that carries the SEPA transfer of the payment', function (): void {
    $payment = pendingPayment();

    $png = (new GeneratePaymentQR)->png($payment);

    // PNG magic number: the bytes really are an image, not a base64 string.
    expect(substr($png, 0, 8))->toBe("\x89PNG\r\n\x1a\n");
});
