<?php

declare(strict_types=1);

namespace App\Domains\Shared\Events\Meetings;

use App\Domains\Meetings\Models\Meeting;

class MeetingCreated
{
    public function __construct(public readonly Meeting $meeting) {}
}
