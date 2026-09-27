<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Communications\Models\Communication;
use App\Domains\ClubAdmin\Communications\Models\CommunicationRecipient;
use Illuminate\Console\Scheduling\Schedule;

/*
| The addresses a communication went to are personal data: they go two
| seasons later. The communication itself — what the club said — stays.
*/

it('deletes the addresses of communications sent more than two years ago, and keeps the communications', function (): void {
    $old = CommunicationRecipient::factory()->create(['created_at' => now()->subYears(2)->subDay()]);
    $recent = CommunicationRecipient::factory()->create(['created_at' => now()->subYears(2)->addDay()]);

    $this->artisan('model:prune', ['--model' => [CommunicationRecipient::class]])->assertSuccessful();

    expect(CommunicationRecipient::find($old->id))->toBeNull()
        ->and(CommunicationRecipient::find($recent->id))->not->toBeNull()
        ->and(Communication::find($old->communication_id))->not->toBeNull();
});

it('runs every night', function (): void {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event): bool => str_contains((string) $event->command, 'model:prune')
            && str_contains((string) $event->command, 'CommunicationRecipient'));

    expect($events)->toHaveCount(1);
});
