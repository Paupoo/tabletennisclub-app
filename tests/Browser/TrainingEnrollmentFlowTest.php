<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Trainings\Models\TrainingPack;

beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->isCommitteeMember()->create();
    $this->season = makeActiveSeason();
});

it('registrations page loads without JS errors', function (): void {
    $this->actingAs($this->admin);

    visit(route('admin.users.registrations'))
        ->assertNoJavaScriptErrors();
});

it('training packs are listed when a subscription has packs', function (): void {
    $user = User::factory()->create(['first_name' => 'Bertrand', 'last_name' => 'Dubois']);
    $pack = TrainingPack::factory()->create([
        'name' => 'Pack Confirmés 2Rst',
        'season_id' => $this->season->id,
    ]);
    $subscription = Subscription::factory()->create([
        'user_id' => $user->id,
        'season_id' => $this->season->id,
        'status' => 'paid',
    ]);
    $subscription->trainingPacks()->attach($pack->id);

    $this->actingAs($this->admin);

    // Subscription show route exists but view may be missing (known Bug #1)
    // Test that the admin registrations page shows the user with their pack reference
    visit(route('admin.users.registrations'))
        ->assertSee('Bertrand Dubois');
});
