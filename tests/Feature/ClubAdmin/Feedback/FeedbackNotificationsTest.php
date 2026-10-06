<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Feedback\Actions\OfferHelp;
use App\Domains\ClubAdmin\Feedback\Actions\SubmitFeedback;
use App\Domains\ClubAdmin\Feedback\Models\FeedbackTheme;
use App\Domains\ClubAdmin\Feedback\Models\HelpTask;
use App\Domains\ClubAdmin\Feedback\Notifications\HelpOfferedNotification;
use App\Domains\ClubAdmin\Feedback\Notifications\NewFeedbackNotification;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\HelpRhythm;
use App\Domains\Shared\Enums\Role;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
    $this->theme = FeedbackTheme::query()->where('name', 'Bar')->firstOrFail();
    $this->member = User::factory()->create(['first_name' => 'Sophie', 'last_name' => 'Renard']);
});

it('tells the suggestions délégation about a new feedback right away', function (): void {
    $delegate = User::factory()->withRole(Role::FEEDBACK)->create();
    $seat = User::factory()->isCommitteeMember()->create();

    (new SubmitFeedback)($this->member, $this->theme, 'Le terminal ne marche pas.', anonymous: false);

    Notification::assertSentTo($delegate, NewFeedbackNotification::class, function (NewFeedbackNotification $notification) use ($delegate): bool {
        $mail = $notification->toMail($delegate);

        return in_array('mail', $notification->via($delegate), true)
            && str_contains(implode(' ', $mail->introLines), 'Sophie Renard')
            && str_contains(implode(' ', $mail->introLines), 'Le terminal ne marche pas.');
    });
    Notification::assertNotSentTo($seat, NewFeedbackNotification::class);
});

it('never names the author of an anonymous feedback', function (): void {
    $delegate = User::factory()->withRole(Role::FEEDBACK)->create();

    (new SubmitFeedback)($this->member, $this->theme, 'Un planning du bar aiderait.', anonymous: true);

    Notification::assertSentTo($delegate, NewFeedbackNotification::class, function (NewFeedbackNotification $notification) use ($delegate): bool {
        $mail = $notification->toMail($delegate);
        $text = implode(' ', [...$mail->introLines, $mail->subject, ...array_values($notification->toArray($delegate))]);

        return ! str_contains($text, 'Sophie') && ! str_contains($text, 'Renard');
    });
});

it('falls back on the committee when nobody holds the délégation', function (): void {
    $seat = User::factory()->isCommitteeMember()->create();

    (new SubmitFeedback)($this->member, $this->theme, 'Un message.', anonymous: false);

    Notification::assertSentTo($seat, NewFeedbackNotification::class);
});

it('tells the délégation about an offer of help right away', function (): void {
    $delegate = User::factory()->withRole(Role::FEEDBACK)->create();
    $task = HelpTask::query()->where('name', 'Arbitrer')->firstOrFail();

    (new OfferHelp)($this->member, HelpRhythm::Occasional, [$task->id], null);

    Notification::assertSentTo($delegate, HelpOfferedNotification::class, function (HelpOfferedNotification $notification) use ($delegate): bool {
        $lines = implode(' ', $notification->toMail($delegate)->introLines);

        return str_contains($lines, 'Sophie Renard') && str_contains($lines, 'Arbitrer');
    });
});
