<?php

declare(strict_types=1);

namespace App\Domains\Meetings\Notifications;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Models\MeetingDecision;
use App\Domains\Meetings\Pdf\MinutesPdf;
use App\Domains\Meetings\Services\MinutesAction;
use App\Domains\Meetings\Services\MinutesReport;
use App\Jobs\SendMeetingMinutesJob;
use App\Support\Markdown;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

/**
 * Published minutes, announced to one reader — sent by {@see SendMeetingMinutesJob},
 * which throttles a general assembly's fan-out, so the notification itself is
 * not queued.
 *
 * A short mail that makes the reader open the minutes rather than copying
 * them: what was decided, what the reader has to do, a link to the reading
 * page — the same for everyone, note takers included — and the minutes as a
 * PDF, rendered now, so it carries this moment's situation.
 */
class MeetingMinutesNotification extends Notification
{
    public function __construct(public Meeting $meeting) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Minutes: :title', ['title' => $this->meeting->title]),
            'body' => __('The minutes of the :date meeting are available', ['date' => $this->meeting->scheduled_at?->translatedFormat('d M Y') ?? __('TBD')]),
            'url' => route('meetings.minutes.read', $this->meeting),
            'category' => 'meeting',
            'icon' => 'o-calendar-days',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $meeting = $this->meeting;

        $mail = (new MailMessage)
            ->subject(__('Minutes: :title', ['title' => $meeting->title]))
            ->greeting(__('Hi :name,', ['name' => $notifiable->first_name]))
            ->line(__('The minutes for **:title** (held on :date) are now available.', [
                'title' => $meeting->title,
                'date' => $meeting->scheduled_at?->translatedFormat('d M Y') ?? '—',
            ]));

        if ($meeting->minutes !== null) {
            $report = MinutesReport::for($meeting, $notifiable instanceof User ? $notifiable : null);

            $mail->line(__('At a glance: :decisions · :actions · :present', [
                'decisions' => trans_choice('{0}no decision|{1}1 decision|[2,*]:count decisions', $report->decisions->count(), ['count' => $report->decisions->count()]),
                'actions' => trans_choice('{0}no action|{1}1 action|[2,*]:count actions', $report->actions->count(), ['count' => $report->actions->count()]),
                'present' => trans_choice('{0}nobody present|{1}1 present|[2,*]:count present', $report->present->count(), ['count' => $report->present->count()]),
            ]));

            if ($report->myActions()->isNotEmpty()) {
                $mail->line('**' . trans_choice('{1}Your action|[2,*]Your actions (:count)', $report->myActions()->count(), ['count' => $report->myActions()->count()]) . '**')
                    ->line($this->yourActions($report));
            }

            if ($report->decisions->isNotEmpty()) {
                $mail->line('**' . __('Decisions') . '**')->line($this->decisions($report));
            }

            $mail->attachData(app(MinutesPdf::class)->render($report), $report->pdfFilename(), ['mime' => 'application/pdf']);
        }

        return $mail
            ->action(__('Read the minutes'), route('meetings.minutes.read', $meeting))
            ->line(__('The full minutes are attached as a PDF.'))
            ->salutation(__('Regards,'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /** Numbered as on the reading page, rendered rather than bulleted as text. */
    private function decisions(MinutesReport $report): HtmlString
    {
        return new HtmlString('<table role="presentation" style="width: 100%;">' . $report->decisions
            ->map(fn (MeetingDecision $decision): string => '<tr><td style="width: 36px; vertical-align: top; font-weight: bold; color: #1e40af;">'
                . $report->decisionNumber($decision) . '</td><td>' . Markdown::safe($decision->body) . '</td></tr>')
            ->implode('') . '</table>');
    }

    private function yourActions(MinutesReport $report): HtmlString
    {
        return new HtmlString('<ul>' . $report->myActions()
            ->map(fn (MinutesAction $action): string => '<li><strong>' . e($action->item->title) . '</strong> — '
                . e($action->item->due_date ? __('Due :date', ['date' => $action->item->due_date->translatedFormat('j M Y')]) : __('No due date'))
                . ($action->status === 'overdue' ? ' (' . e($action->label()) . ')' : '') . '</li>')
            ->implode('') . '</ul>');
    }
}
