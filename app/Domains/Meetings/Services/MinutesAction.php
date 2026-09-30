<?php

declare(strict_types=1);

namespace App\Domains\Meetings\Services;

use App\Domains\Meetings\Models\MeetingActionItem;

/**
 * One action of published minutes, with where it stands today.
 *
 * `days` counts from today to the due date: negative when overdue, null when
 * there is no due date. `mine` flags an action assigned to the reader.
 */
final readonly class MinutesAction
{
    /**
     * @param  'overdue'|'todo'|'done'  $status
     */
    public function __construct(
        public MeetingActionItem $item,
        public string $status,
        public ?int $days,
        public bool $mine,
    ) {}

    /** The status in words: the colour only repeats it. */
    public function label(): string
    {
        return match ($this->status) {
            'overdue' => trans_choice('{1}Overdue by a day|[2,*]Overdue by :count days', abs((int) $this->days), ['count' => abs((int) $this->days)]),
            'done' => __('Done'),
            default => match (true) {
                $this->days === null => __('To do'),
                $this->days === 0 => __('Due today'),
                default => trans_choice('{1}To do · tomorrow|[2,*]To do · in :count days', $this->days, ['count' => $this->days]),
            },
        };
    }
}
