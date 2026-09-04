<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\ClubAdmin\Users\Services\UserCalendarService;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Shared\Enums\MeetingStatusEnum;
use App\Domains\Shared\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Le calendrier du membre offre un mode « tous les événements du club ». Il
 * listait alors toutes les réunions confirmées, ce qui mettait l'agenda du
 * comité sous les yeux de n'importe quel membre. Seule l'assemblée générale est
 * convoquée pour tout le club.
 */
function calendarTitlesFor(User $user): array
{
    return app(UserCalendarService::class)
        ->eventsFor($user, showAllEvents: true, categories: ['meeting'], from: now()->startOfDay(), to: now()->addMonths(2))
        ->pluck('title')
        ->all();
}

beforeEach(function (): void {
    Meeting::factory()->committee()->create([
        'title' => 'Comité de rentrée',
        'status' => MeetingStatusEnum::CONFIRMED,
        'scheduled_at' => now()->addWeek(),
    ]);

    Meeting::factory()->generalAssembly()->create([
        'title' => 'Assemblée générale',
        'status' => MeetingStatusEnum::CONFIRMED,
        'scheduled_at' => now()->addWeeks(2),
    ]);
});

it('keeps committee meetings out of an ordinary member\'s calendar', function (): void {
    expect(calendarTitlesFor(User::factory()->create()))->toBe(['Assemblée générale']);
});

it('shows every meeting to whoever may see meetings', function (): void {
    expect(calendarTitlesFor(User::factory()->withRole(Role::MEETINGS)->create()))
        ->toBe(['Comité de rentrée', 'Assemblée générale']);
});

it('shows a member the committee meeting they were invited to', function (): void {
    $member = User::factory()->create();
    Meeting::where('title', 'Comité de rentrée')->sole()->users()->attach($member);

    expect(calendarTitlesFor($member))->toBe(['Comité de rentrée', 'Assemblée générale']);
});
