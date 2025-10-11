<?php

declare(strict_types=1);

use App\Livewire\Admin\Spams\Index;
use App\Models\Spam;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->adminUser = User::factory()->create();
    // $this->adminUser->assignRole('admin'); // Si tu utilises Spatie Permission
});

describe('Spams admin page', function () {
    it('is accessible by an admin', function () {
        $this->actingAs($this->adminUser)
            ->get(route('admin.spams.index'))
            ->assertOk()
            ->assertSee('Gestion des spams')
            ->assertSeeLivewire(Index::class);
    });

    it('displays spam list correctly', function () {
        $spams = Spam::factory()->count(3)->create([
            'created_at' => now()->subHours(2),
            'user_agent' => 'TestAgent/1.0',
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(Index::class)
            ->assertSee($spams->first()->ip)
            ->assertSee('TestAgent/1.0');
    });

    it('displays stats correctly', function () {
        Spam::factory()->count(3)->create(['created_at' => now()]);
        Spam::factory()->count(2)->create(['created_at' => now()->subDay()]);

        $this->actingAs($this->adminUser)
            ->get(route('admin.spams.index'))
            ->assertSee('5')
            ->assertSee('3');
    });
});

describe('Search and filters', function () {
    it('can search by ip', function () {
        $spam1 = Spam::factory()->create(['ip' => '192.168.1.100']);
        $spam2 = Spam::factory()->create(['ip' => '10.0.0.50']);

        Livewire::actingAs($this->adminUser)
            ->test(Index::class)
            ->set('search', '192.168')
            ->assertSee($spam1->ip)
            ->assertDontSee($spam2->ip);
    });

    it('can filter by period', function () {
        $todaySpam = Spam::factory()->create(['created_at' => now()]);
        $oldSpam = Spam::factory()->create(['created_at' => now()->subWeek()]);

        Livewire::actingAs($this->adminUser)
            ->test(Index::class)
            ->set('filters.period', 'today')
            ->assertSee($todaySpam->ip)
            ->assertDontSee($oldSpam->ip);
    });

    it('can clear all filters', function () {
        Livewire::actingAs($this->adminUser)
            ->test(Index::class)
            ->set('search', 'test')
            ->set('filters.period', 'today')
            ->set('filters.specificIp', '192.168.1.1')
            ->call('clearFilters')
            ->assertSet('search', '')
            ->assertSet('filters.period', '')
            ->assertSet('filters.specificIp', '');
    });

    it('can search inside json fields', function () {
        $spam = Spam::factory()->create([
            'inputs' => ['email' => 'test@spam.com', 'message' => 'Buy now!'],
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(Index::class)
            ->set('search', 'test@spam.com')
            ->assertSee($spam->ip);
    });
});

describe('Spam deletion', function () {
    it('can delete a single spam', function () {
        $spam = Spam::factory()->create();

        Livewire::actingAs($this->adminUser)
            ->test(Index::class)
            ->call('deleteSpam', $spam->id);

        $this->assertDatabaseMissing('spams', ['id' => $spam->id]);
    });

    it('can bulk delete spams', function () {
        $spams = Spam::factory()->count(3)->create();
        $spamIds = $spams->pluck('id')->toArray();

        Livewire::actingAs($this->adminUser)
            ->test(Index::class)
            ->set('selectedItems', $spamIds)
            ->call('bulkDelete');

        foreach ($spamIds as $id) {
            $this->assertDatabaseMissing('spams', ['id' => $id]);
        }
    });
});

describe('Selection and pagination', function () {
    it('can select all spams on the page', function () {
        Spam::factory()->count(5)->create();

        Livewire::actingAs($this->adminUser)
            ->test(Index::class)
            ->set('selectAll', true)
            ->assertCount('selectedItems', 5);
    });

    it('handles pagination correctly', function () {
        Spam::factory()->count(30)->create();

        $component = Livewire::actingAs($this->adminUser)
            ->test(Index::class);

        $component->assertViewHas('spams', function ($spams) {
            return $spams instanceof \Illuminate\Pagination\LengthAwarePaginator
                && $spams->count() === 25
                && $spams->total() === 30;
        });

        $component->assertViewHas('totalResults', 30);

        $component->set('perPage', '15')
            ->assertViewHas('spams', fn ($spams) => $spams->count() === 15
                && $spams->total() === 30
                && $spams->lastPage() === 2
            );

        $component->set('perPage', '50')
            ->assertViewHas('spams', fn ($spams) => $spams->count() === 30
                && $spams->lastPage() === 1
            );
    });
});
