<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Trainings\Models\Training;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Services\TrainingStaffReport;
use Carbon\CarbonImmutable;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    // Un calendrier figé : les chiffres attendus sont écrits à la main.
    $this->travelTo(CarbonImmutable::parse('2026-03-15 12:00'));

    $season = Season::factory()->create(['name' => '2025-2026', 'is_active' => true, 'start_at' => '2025-09-01', 'end_at' => '2026-08-31']);
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

describe('what is planned and one pack at a time', function (): void {
    it('counts apart the sessions still to come, never in the other figures', function (): void {
        $rows = app(TrainingStaffReport::class)->rows(CarbonImmutable::parse('2025-09-01'), CarbonImmutable::parse('2026-08-31'));

        expect(collect($rows)->map(fn (array $row): array => [$row['name'], $row['coached'], $row['pending'], $row['upcoming']])->all())
            ->toBe([
                ['Anne Arnould', 2, 1, 1],
                ['Bruno Bastin', 1, 0, 0],
                ['Jean Sparre', 0, 1, 0],
            ]);
    })->group('training', 'staff');

    it('lists a session still to come as planned in the detail', function (): void {
        $detail = app(TrainingStaffReport::class)->detail($this->holder, CarbonImmutable::parse('2026-03-01'), CarbonImmutable::parse('2026-03-31'));

        expect(collect($detail)->map(fn (array $line): array => [$line['date'], $line['status']])->all())
            ->toBe([['2026-03-03', 'to_record'], ['2026-03-20', 'planned']]);
    })->group('training', 'staff');

    it('narrows the count to one pack', function (): void {
        $otherPack = makeTrainingPack(Season::where('is_active', true)->first(), ['name' => 'Jeudi Jeunes']);
        $other = User::factory()->create(['first_name' => 'Chloé', 'last_name' => 'Collard']);
        Training::factory()->for($otherPack, 'trainingPack')->create([
            'season_id' => $otherPack->season_id, 'trainer_id' => $other->id,
            'start' => '2026-02-05 18:00', 'end' => '2026-02-05 19:30', 'attendance_taken_at' => '2026-02-05 20:00',
        ]);

        $rows = app(TrainingStaffReport::class)->rows(CarbonImmutable::parse('2025-09-01'), CarbonImmutable::parse('2026-08-31'), $otherPack->id);

        expect(collect($rows)->map(fn (array $row): array => [$row['name'], $row['coached']])->all())
            ->toBe([['Chloé Collard', 1]]);
    })->group('training', 'staff');
});

describe('the coaching staff screen', function (): void {
    it('opens on the active season and lets the committee read a past one', function (): void {
        $committee = User::factory()->isCommitteeMember()->create();
        $past = Season::factory()->create(['name' => '2024-2025', 'start_at' => '2024-07-01', 'end_at' => '2025-06-30']);
        $pastPack = makeTrainingPack($past);
        $veteran = User::factory()->create(['first_name' => 'Victor', 'last_name' => 'Ancien']);
        Training::factory()->for($pastPack, 'trainingPack')->create([
            'season_id' => $past->id, 'trainer_id' => $veteran->id,
            'start' => '2025-01-14 18:00', 'end' => '2025-01-14 19:30', 'attendance_taken_at' => '2025-01-14 20:00',
        ]);

        Livewire::actingAs($committee)
            ->test('pages::club-events.trainings.staff')
            ->assertSee('Anne Arnould')
            ->assertDontSee('Victor Ancien')
            ->set('viewSeasonId', $past->id)
            ->assertSee('Victor Ancien')
            ->assertDontSee('Anne Arnould');
    })->group('training', 'staff');

    it('narrows by month and by pack, each removable from its chip', function (): void {
        $committee = User::factory()->isCommitteeMember()->create();
        $pack = TrainingPack::where('name', 'Mardi Confirmés')->firstOrFail();

        $screen = Livewire::actingAs($committee)
            ->test('pages::club-events.trainings.staff')
            ->set('monthFilter', '2026-03')
            ->assertDontSee('Bruno Bastin')
            ->assertSee('Anne Arnould')
            ->set('packFilter', $pack->id);

        expect(collect($screen->instance()->getFilterChips())->pluck('key')->all())->toBe(['monthFilter', 'packFilter']);

        $screen->call('removeFilter', 'monthFilter')
            ->assertSet('monthFilter', '')
            ->assertSee('Bruno Bastin');
    })->group('training', 'staff');

    it('drops the filters when the season changes', function (): void {
        $committee = User::factory()->isCommitteeMember()->create();
        $next = Season::factory()->create(['name' => '2026-2027', 'start_at' => '2026-09-01', 'end_at' => '2027-06-30']);
        $pack = TrainingPack::where('name', 'Mardi Confirmés')->firstOrFail();

        Livewire::actingAs($committee)
            ->test('pages::club-events.trainings.staff')
            ->set('monthFilter', '2026-02')
            ->set('packFilter', $pack->id)
            ->set('viewSeasonId', $next->id)
            ->assertSet('monthFilter', '')
            ->assertSet('packFilter', 0);
    })->group('training', 'staff');

    it('shows one person session by session in a read-only drawer', function (): void {
        $committee = User::factory()->isCommitteeMember()->create();

        Livewire::actingAs($committee)
            ->test('pages::club-events.trainings.staff')
            ->call('showDetail', $this->sparring->id)
            ->assertSet('detailDrawer', true)
            ->assertSee('03/02/2026');
    })->group('training', 'staff');

    it('keeps a coach out: the screen prepares what people get paid', function (): void {
        $coach = User::factory()->isCoach()->create();

        $this->actingAs($coach)->get(route('admin.trainings.staff'))->assertForbidden();
    })->group('training', 'staff', 'security');

    it('exports what is on screen, and says so in the file name and the audit', function (): void {
        $committee = User::factory()->isCommitteeMember()->create();
        $pack = TrainingPack::where('name', 'Mardi Confirmés')->firstOrFail();

        Livewire::actingAs($committee)
            ->test('pages::club-events.trainings.staff')
            ->set('monthFilter', '2026-02')
            ->set('packFilter', $pack->id)
            ->call('export', 'csv')
            ->assertFileDownloaded('encadrement-2025-2026-fevrier-2026-mardi-confirmes.csv');

        expect(Activity::query()->where('event', 'training_staff_exported')->latest('id')->first()->properties->all())
            ->toMatchArray(['season' => '2025-2026', 'month' => '2026-02', 'pack_id' => $pack->id, 'format' => 'csv']);
    })->group('training', 'staff');

    it('no longer lights up the training packs in the menu', function (): void {
        $committee = User::factory()->isCommitteeMember()->create();

        $html = $this->actingAs($committee)->get(route('admin.trainings.staff'))->assertOk()->getContent();

        // Mary marque un lien actif dès que l'adresse commence par lui : les packs
        // (…/trainings) s'allumaient sur l'encadrement (…/trainings/staff).
        preg_match('#<a[^>]*href="' . preg_quote(route('admin.trainings.index'), '#') . '"[^>]*>#', $html, $packsLink);

        expect($packsLink[0] ?? '')->not->toContain('mary-active-menu');
    })->group('training', 'staff');
});
