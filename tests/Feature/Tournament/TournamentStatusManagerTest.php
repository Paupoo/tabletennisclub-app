<?php

namespace Tests\Feature\Tournament;

use App\Enums\TournamentStatusEnum;
use App\Models\Tournament;
use App\Services\TournamentStatusManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

class TournamentStatusManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_change_status_from_draft_to_published(): void
    {
        $tournament = Tournament::factory()->create([
            'status' => TournamentStatusEnum::DRAFT,
        ]);

        $manager = new TournamentStatusManager($tournament);
        $manager->setStatus(TournamentStatusEnum::PUBLISHED);

        $this->assertEquals(TournamentStatusEnum::PUBLISHED, $tournament->fresh()->status);
    }

    public function test_throws_exception_on_invalid_status_transition(): void
    {
        $tournament = Tournament::factory()->create([
            'status' => TournamentStatusEnum::DRAFT,
        ]);

        $manager = new TournamentStatusManager($tournament);

        $this->expectException(InvalidArgumentException::class);
        $manager->setStatus(TournamentStatusEnum::CLOSED); // Not allowed from DRAFT
    }

    public function test_can_change_status_from_published_to_locked(): void
{
    $tournament = Tournament::factory()->create([
        'status' => TournamentStatusEnum::PUBLISHED,
    ]);

    $manager = new TournamentStatusManager($tournament);
    $manager->setStatus(TournamentStatusEnum::LOCKED);

    $this->assertEquals(TournamentStatusEnum::LOCKED, $tournament->fresh()->status);
}

public function test_cannot_change_status_from_draft_to_closed(): void
{
    $tournament = Tournament::factory()->create([
        'status' => TournamentStatusEnum::DRAFT,
    ]);

    $manager = new TournamentStatusManager($tournament);

    $this->expectException(InvalidArgumentException::class);
    $manager->setStatus(TournamentStatusEnum::CLOSED);
}

public function test_can_cancel_from_pending(): void
{
    $tournament = Tournament::factory()->create([
        'status' => TournamentStatusEnum::PENDING,
    ]);

    $manager = new TournamentStatusManager($tournament);
    $manager->setStatus(TournamentStatusEnum::CANCELLED);

    $this->assertEquals(TournamentStatusEnum::CANCELLED, $tournament->fresh()->status);
}

public function test_get_allowed_next_statuses_returns_expected_list(): void
{
    $tournament = Tournament::factory()->create([
        'status' => TournamentStatusEnum::LOCKED,
    ]);

    $manager = new TournamentStatusManager($tournament);
    $expected = [
        TournamentStatusEnum::PUBLISHED,
        TournamentStatusEnum::PENDING,
        TournamentStatusEnum::CANCELLED,
    ];

    $this->assertEqualsCanonicalizing($expected, $manager->getAllowedNextStatuses());
}

public function test_cannot_lock_pending_tournament_with_started_matches(): void
{
    $tournament = Tournament::factory()
        ->hasMatches(1, ['status' => 'in_progress'])
        ->create([
            'status' => TournamentStatusEnum::PENDING,
        ]);

    $manager = new TournamentStatusManager($tournament);

    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('At least one match is started. This is not allowed.');

    $manager->setStatus(TournamentStatusEnum::LOCKED);
}


}
