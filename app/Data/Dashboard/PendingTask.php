<?php

declare(strict_types=1);

namespace App\Data\Dashboard;

/**
 * Something waiting for the reader to act, counted once and shown twice: as a
 * pill on the dashboard, and as the counter of the menu entry it leads to.
 *
 * Only ever built with a count above zero, and only for whoever holds the right
 * to do the work — reading a screen is not a reason to be told about it.
 */
readonly class PendingTask
{
    /**
     * @param  string  $key  Stable identifier, the one the menu asks for.
     * @param  'error'|'warning'|'info'  $type  The pill's tone on the dashboard.
     */
    public function __construct(
        public string $key,
        public int $count,
        public string $label,
        public string $icon,
        public string $route,
        public string $type = 'warning',
    ) {}

    /**
     * The shape the dashboard's pills have always read.
     *
     * @return array{type: string, icon: string, label: string, route: string}
     */
    public function toAlert(): array
    {
        return [
            'type' => $this->type,
            'icon' => $this->icon,
            'label' => $this->label,
            'route' => $this->route,
        ];
    }
}
