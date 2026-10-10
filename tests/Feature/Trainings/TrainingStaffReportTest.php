<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Trainings\Models\Training;
use App\Domains\Trainings\Services\TrainingStaffReport;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function (): void {
    // Un calendrier figé : les chiffres attendus sont écrits à la main.
    $this->travelTo(CarbonImmutable::parse('2026-03-15 12:00'));

    $season = Season::factory()->create(['is_active' => true, 'start_at' => '2025-09-01', 'end_at' => '2026-08-31']);
    $pack = makeTrainingPack($season, ['name' => 'Mardi Confirmés']);

    $this->holder = User::factory()->create(['first_name' => 'Anne', 'last_name' => 'Arnould']);
    $this->standIn = User::factory()->create(['first_name' => 'Bruno', 'last_name' => 'Bastin']);
    $this->sparring = User::factory()->create(['first_name' => 'Jean', 'last_name' => 'Sparre']);

    $session = fn (string $day, User $coach, array $state = []): Training => Training::factory()
        ->for($pack, 'trainingPack')
        ->create(['season_id' => $season->id, 'trainer_id' => $coach->id, 'start' => "{$day} 18:00", 'end' => "{$day} 19:30", ...$state]);

    $counted = ['attendance_taken_at' => '2026-03-01 20:00'];

    $feb03 = $session('2026-02-03', $this->holder, $counted);
    $feb10 = $session('2026-02-10', $this->holder, $counted);
    $session('2026-02-17', $this->holder, ['status' => 'cancelled_closed']);
    $feb24 = $session('2026-02-24', $this->standIn, $counted);
    $mar03 = $session('2026-03-03', $this->holder);
    $session('2026-03-20', $this->holder);

    $feb03->sparrings()->attach($this->sparring->id, ['source' => 'pack', 'attendance' => 'present']);
    $feb10->sparrings()->attach($this->sparring->id, ['source' => 'pack', 'attendance' => 'absent']);
    $feb24->sparrings()->attach($this->sparring->id, ['source' => 'pack', 'removed_at' => '2026-02-20 10:00']);
    $mar03->sparrings()->attach($this->sparring->id, ['source' => 'pack']);
});

describe('who did what', function (): void {
    it('counts, over the season, the sessions given, the help confirmed and what is still to record', function (): void {
        $rows = app(TrainingStaffReport::class)->rows(CarbonImmutable::parse('2025-09-01'), CarbonImmutable::parse('2026-08-31'));

        expect(collect($rows)->map(fn (array $row): array => [$row['name'], $row['coached'], $row['sparred'], $row['pending']])->all())
            ->toBe([
                ['Anne Arnould', 2, 0, 1],
                ['Bruno Bastin', 1, 0, 0],
                ['Jean Sparre', 0, 1, 1],
            ]);
    })->group('training', 'staff');

    it('narrows the count to one month', function (): void {
        $rows = app(TrainingStaffReport::class)->rows(CarbonImmutable::parse('2026-02-01'), CarbonImmutable::parse('2026-02-28'));

        expect(collect($rows)->map(fn (array $row): array => [$row['name'], $row['coached'], $row['sparred'], $row['pending']])->all())
            ->toBe([
                ['Anne Arnould', 2, 0, 0],
                ['Bruno Bastin', 1, 0, 0],
                ['Jean Sparre', 0, 1, 0],
            ]);
    })->group('training', 'staff');

    it('details one person session by session', function (): void {
        $detail = app(TrainingStaffReport::class)->detail($this->sparring, CarbonImmutable::parse('2025-09-01'), CarbonImmutable::parse('2026-08-31'));

        expect(collect($detail)->map(fn (array $line): array => [$line['date'], $line['pack'], $line['role'], $line['status']])->all())
            ->toBe([
                ['2026-02-03', 'Mardi Confirmés', 'sparring', 'present'],
                ['2026-02-10', 'Mardi Confirmés', 'sparring', 'absent'],
                ['2026-03-03', 'Mardi Confirmés', 'sparring', 'to_record'],
            ]);
    })->group('training', 'staff');
});

describe('the coaching staff screen', function (): void {
    it('shows the committee who did what, and one person in detail', function (): void {
        $committee = User::factory()->isCommitteeMember()->create();

        Livewire::actingAs($committee)
            ->test('pages::club-events.trainings.staff')
            ->set('period', 'season')
            ->assertOk()
            ->assertSee('Anne Arnould')
            ->assertSee('Jean Sparre')
            ->call('showDetail', $this->sparring->id)
            ->assertSee('03/02/2026');
    })->group('training', 'staff');

    it('keeps a coach out: the screen prepares what people get paid', function (): void {
        $coach = User::factory()->isCoach()->create();

        $this->actingAs($coach)->get(route('admin.trainings.staff'))->assertForbidden();
    })->group('training', 'staff', 'security');

    it('exports the count of the period', function (): void {
        $committee = User::factory()->isCommitteeMember()->create();

        Livewire::actingAs($committee)
            ->test('pages::club-events.trainings.staff')
            ->set('period', '2026-02')
            ->call('export', 'csv')
            ->assertFileDownloaded();
    })->group('training', 'staff');
});
