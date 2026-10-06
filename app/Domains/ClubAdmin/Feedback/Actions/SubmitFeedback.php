<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Feedback\Actions;

use App\Domains\ClubAdmin\Feedback\Models\FeedbackEntry;
use App\Domains\ClubAdmin\Feedback\Models\FeedbackTheme;
use App\Domains\ClubAdmin\Feedback\Notifications\NewFeedbackNotification;
use App\Domains\ClubAdmin\Feedback\Services\FeedbackReaders;
use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Support\Facades\Notification;

final class SubmitFeedback
{
    /**
     * File what a member writes to the committee, in the permanent box.
     *
     * Anonymous means that nothing on the row leads back to its author — not
     * even to someone reading the database. So the author is not stored, and
     * the timestamps keep only the day: a second-exact time would match the
     * signed offer of help sent in the same request, or a server log line.
     */
    public function __invoke(User $author, FeedbackTheme $theme, string $body, bool $anonymous): FeedbackEntry
    {
        $entry = new FeedbackEntry([
            'user_id' => $anonymous ? null : $author->id,
            'feedback_theme_id' => $theme->id,
            'body' => trim($body),
        ]);

        if ($anonymous) {
            $entry->timestamps = false;
            $entry->created_at = $entry->updated_at = now()->startOfDay();
        }

        $entry->save();
        $entry->timestamps = true;

        $entry->setRelation('theme', $theme);
        $entry->setRelation('author', $anonymous ? null : $author);

        Notification::send((new FeedbackReaders)->toNotify(), new NewFeedbackNotification($entry));

        return $entry;
    }
}
