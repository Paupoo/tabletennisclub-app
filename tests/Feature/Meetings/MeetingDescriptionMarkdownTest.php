<?php

declare(strict_types=1);

use App\Domains\Meetings\Models\Meeting;
use App\Services\IcsGenerator;

/*
 * A meeting's description is written in the markdown editor. A calendar file
 * shows no HTML, so the invitation's .ics carries it as plain text.
 */
it('puts a markdown description into the calendar file as plain text', function (): void {
    $meeting = Meeting::factory()->confirmed()->create(['description' => "**Budget** et [tournoi](https://ctt.be)\n\n- point un"]);

    $ics = app(IcsGenerator::class)->forMeeting($meeting);

    expect($ics)->toContain('DESCRIPTION:Budget et tournoi')
        ->not->toContain('**')
        ->not->toContain('<');
});
