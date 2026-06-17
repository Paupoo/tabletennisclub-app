<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Trainings\Models\TrainingPack;

beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->isCommitteeMember()->create();
    $this->season = makeActiveSeason();
});

it('training index page loads without JS errors', function (): void {
    $this->actingAs($this->admin);

    visit(route('admin.trainings.index'))
        ->assertNoJavaScriptErrors()
        ->assertSee('Entraînements');
});

it('training pack appears in admin training list', function (): void {
    TrainingPack::factory()->create([
        'name' => 'Pack Débutants Test 9Xyz',
        'season_id' => $this->season->id,
    ]);

    $this->actingAs($this->admin);

    visit(route('admin.trainings.index'))
        ->assertSee('Pack Débutants Test 9Xyz');
});

it('coach trainings page loads without JS errors', function (): void {
    $this->actingAs($this->admin);

    visit(route('coach.trainings'))
        ->assertNoJavaScriptErrors();
});
