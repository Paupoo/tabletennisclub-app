<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Trainings\Models\Training;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Services\TrainingAttendanceReport;
use App\Domains\Trainings\Services\TrainingAttendanceService;

it('rates a member on the sessions that were actually counted', function (): void {
    $pack = TrainingPack::factory()->create(['max_participants' => 5, 'price' => 90]);
    $coach = User::factory()->create();
    $member = User::factory()->create();

    $service = app(TrainingAttendanceService::class);

    $came = Training::factory()->past(21)->for($pack, 'trainingPack')->create();
    $service->record($came, $member, 'present');
    $service->validate($came, $coach);

    $skipped = Training::factory()->past(14)->for($pack, 'trainingPack')->create();
    $service->record($skipped, $member, 'absent');
    $service->validate($skipped, $coach);

    // Ni l'une ni l'autre ne doit peser : la séance annulée n'attendait
    // personne, et la séance jamais pointée ne dit rien de qui est venu.
    Training::factory()->past(7)->cancelledFree()->for($pack, 'trainingPack')->create();
    Training::factory()->past(3)->for($pack, 'trainingPack')->create();

    expect(app(TrainingAttendanceReport::class)->memberRate($pack, $member->id))->toBe(50);
})->group('training', 'attendance');

it('says nothing rather than zero when no session was counted', function (): void {
    $pack = TrainingPack::factory()->create(['max_participants' => 5, 'price' => 90]);
    $member = User::factory()->create();

    Training::factory()->past(7)->for($pack, 'trainingPack')->create();

    // 0 % se lirait « il ne vient jamais ». La vérité est « on n'en sait rien ».
    expect(app(TrainingAttendanceReport::class)->memberRate($pack, $member->id))->toBeNull();
})->group('training', 'attendance');

/**
 * Inscrit un membre au pack et renvoie l'utilisateur.
 */
function enrolledMemberOf(TrainingPack $pack): User
{
    $subscription = Subscription::factory()
        ->for($pack->season, 'season')
        ->for(User::factory(), 'user')
        ->create();

    $subscription->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);

    return $subscription->user;
}

describe('the attendance matrix', function (): void {
    it('lays the counted sessions against the enrolled members', function (): void {
        $pack = TrainingPack::factory()->create(['max_participants' => 5, 'price' => 90]);
        $coach = User::factory()->create();

        $subscription = Subscription::factory()->for($pack->season, 'season')->for(User::factory(), 'user')->create();
        $subscription->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);
        $member = $subscription->user;

        $service = app(TrainingAttendanceService::class);
        $session = Training::factory()->past(7)->for($pack, 'trainingPack')->create();
        $service->record($session, $member, 'present');
        $service->validate($session, $coach);

        $matrix = app(TrainingAttendanceReport::class)->matrix($pack);

        expect($matrix['sessions'])->toHaveCount(1)
            ->and($matrix['sessions'][0]['id'])->toBe($session->id)
            ->and($matrix['sessions'][0]['counted'])->toBeTrue()
            ->and($matrix['members'])->toHaveCount(1)
            ->and($matrix['members'][0]['id'])->toBe($member->id)
            ->and($matrix['members'][0]['cells'][$session->id])->toBe('present');
    })->group('training', 'attendance');

    it('tells an absence apart from a session nobody counted', function (): void {
        $pack = TrainingPack::factory()->create(['max_participants' => 5, 'price' => 90]);
        $coach = User::factory()->create();

        $subscription = Subscription::factory()->for($pack->season, 'season')->for(User::factory(), 'user')->create();
        $subscription->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);
        $member = $subscription->user;

        $service = app(TrainingAttendanceService::class);

        $missed = Training::factory()->past(21)->for($pack, 'trainingPack')->create();
        $service->validate($missed, $coach);

        $forgotten = Training::factory()->past(14)->for($pack, 'trainingPack')->create();
        $cancelled = Training::factory()->past(7)->cancelledFree()->for($pack, 'trainingPack')->create();

        $matrix = app(TrainingAttendanceReport::class)->matrix($pack);
        $cells = $matrix['members'][0]['cells'];
        $flags = collect($matrix['sessions'])->keyBy('id');

        // `absent` est écrit, `null` ne l'est pas : c'est cette différence que la
        // grille rend visible, et sans laquelle un oubli se lit comme une absence.
        expect($cells[$missed->id])->toBe('absent')
            ->and($cells[$forgotten->id])->toBeNull()
            ->and($cells[$cancelled->id])->toBeNull()
            ->and($flags[$forgotten->id]['counted'])->toBeFalse()
            ->and($flags[$cancelled->id]['cancelled'])->toBeTrue()
            // Une annulée reste affichée : la masquer ferait croire à un trou
            // dans le calendrier plutôt qu'à une séance qui n'a pas eu lieu.
            ->and($matrix['sessions'])->toHaveCount(3);
    })->group('training', 'attendance');

    it('carries a rate down each column and along each row', function (): void {
        $pack = TrainingPack::factory()->create(['max_participants' => 5, 'price' => 90]);
        $coach = User::factory()->create();

        $regular = enrolledMemberOf($pack);
        $ghost = enrolledMemberOf($pack);

        $service = app(TrainingAttendanceService::class);

        foreach ([21, 14] as $daysAgo) {
            $session = Training::factory()->past($daysAgo)->for($pack, 'trainingPack')->create();
            $service->record($session, $regular, 'present');
            $service->validate($session, $coach);
        }

        $matrix = app(TrainingAttendanceReport::class)->matrix($pack);
        $rates = collect($matrix['members'])->pluck('rate', 'id');

        // Colonne : la séance a réuni un inscrit sur deux.
        // Ligne : l'un vient toujours, l'autre jamais — les deux décisions que
        // le comité doit pouvoir prendre d'un coup d'œil.
        expect($matrix['sessions'][0]['rate'])->toBe(50)
            ->and($rates[$regular->id])->toBe(100)
            ->and($rates[$ghost->id])->toBe(0);
    })->group('training', 'attendance');

    it('leaves a column without a rate when the session was never counted', function (): void {
        $pack = TrainingPack::factory()->create(['max_participants' => 5, 'price' => 90]);
        enrolledMemberOf($pack);

        Training::factory()->past(7)->for($pack, 'trainingPack')->create();

        $matrix = app(TrainingAttendanceReport::class)->matrix($pack);

        expect($matrix['sessions'][0]['rate'])->toBeNull();
    })->group('training', 'attendance');

    it('lists whoever turned up without being enrolled, below the grid', function (): void {
        $pack = TrainingPack::factory()->create(['max_participants' => 5, 'price' => 90]);
        $coach = User::factory()->create();

        $enrolled = enrolledMemberOf($pack);
        $walkIn = User::factory()->create();

        $service = app(TrainingAttendanceService::class);
        $session = Training::factory()->past(7)->for($pack, 'trainingPack')->create();
        $service->record($session, $enrolled, 'present');
        $service->record($session, $walkIn, 'present');
        $service->validate($session, $coach);

        $matrix = app(TrainingAttendanceReport::class)->matrix($pack);

        // Séparés de la grille : ils ne sont pas dans le pack, les mêler aux
        // inscrits fausserait le taux de la colonne.
        expect(collect($matrix['members'])->pluck('id'))->not->toContain($walkIn->id)
            ->and($matrix['walkIns'])->toHaveCount(1)
            ->and($matrix['walkIns'][0]['id'])->toBe($walkIn->id)
            ->and($matrix['walkIns'][0]['cells'][$session->id])->toBe('present')
            ->and($matrix['sessions'][0]['rate'])->toBe(100);
    })->group('training', 'attendance');
});
