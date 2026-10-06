<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Feedback\Notifications;

use App\Domains\ClubAdmin\Feedback\Models\FeedbackCampaign;
use App\Domains\ClubAdmin\Feedback\Services\CampaignResults;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * The survey closed: the figures, to the whole committee.
 */
class CampaignSummaryNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public FeedbackCampaign $campaign) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Survey closed: :title', ['title' => $this->campaign->title]),
            'body' => $this->figures(),
            'url' => route('admin.feedback.index', ['tab' => 'results', 'campaign' => $this->campaign->id]),
            'category' => 'feedback',
            'icon' => 'o-chart-bar',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__('Survey closed: :title', ['title' => $this->campaign->title]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->first_name]))
            ->line($this->figures());

        $themes = app(CampaignResults::class)->commentsByTheme($this->campaign);

        if ($themes->isNotEmpty()) {
            $mail->line(__('Comments per theme: :themes.', [
                'themes' => $themes->map(fn (array $row): string => "{$row['theme']} ({$row['count']})")->implode(', '),
            ]));
        }

        return $mail
            ->line(__('The account to the members is yours to write, in Communications.'))
            ->action(__('Read the answers'), route('admin.feedback.index', ['tab' => 'results', 'campaign' => $this->campaign->id]));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    private function figures(): string
    {
        $results = app(CampaignResults::class);
        $average = $results->average($this->campaign);

        return __(':count answers, average rating :average out of 5.', [
            'count' => $results->responses($this->campaign),
            'average' => $average === null ? '–' : number_format($average, 1, ',', ' '),
        ]);
    }
}
