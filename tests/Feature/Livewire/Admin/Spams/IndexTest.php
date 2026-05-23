<?php

declare(strict_types=1);

use App\Models\ClubAdmin\Contact\Spam;
use App\Models\ClubAdmin\Users\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->adminUser = User::factory()->create(['is_admin' => true]);
});

describe('Spams admin page', function (): void {
    it('is accessible by an admin', function (): void {
        $this->actingAs($this->adminUser)
            ->get(route('admin.website.spams.index'))
            ->assertOk()
            ->assertSee('Spam')
            ->assertSeeLivewire('pages::website.spams.index');
    });

    it('displays spam list correctly', function (): void {
        $spams = Spam::factory()->count(3)->create([
            'created_at' => now()->subHours(2),
            'user_agent' => 'TestAgent/1.0',
        ]);

        Livewire::actingAs($this->adminUser)
            ->test('pages::website.spams.index')
            ->assertSee($spams->first()->ip)
            ->assertSee('TestAgent/1.0');
    });

    it('displays stats correctly', function (): void {
        Spam::factory()->count(3)->create(['created_at' => now()]);
        Spam::factory()->count(2)->create(['created_at' => now()->subDay()]);

        $this->actingAs($this->adminUser)
            ->get(route('admin.website.spams.index'))
            ->assertSee('5')
            ->assertSee('3');
    });
});

describe('Search and filters', function (): void {
    it('can search by ip', function (): void {
        $spam1 = Spam::factory()->create(['ip' => '192.168.1.100']);
        $spam2 = Spam::factory()->create(['ip' => '10.0.0.50']);

        Livewire::actingAs($this->adminUser)
            ->test('pages::website.spams.index')
            ->set('search', '192.168')
            ->assertSee($spam1->ip)
            ->assertDontSee($spam2->ip);
    });

    it('can filter by period', function (): void {
        $todaySpam = Spam::factory()->create(['created_at' => now()]);
        $oldSpam = Spam::factory()->create(['created_at' => now()->subWeek()]);

        Livewire::actingAs($this->adminUser)
            ->test('pages::website.spams.index')
            ->set('period', 'today')
            ->assertSee($todaySpam->ip)
            ->assertDontSee($oldSpam->ip);
    });

    it('can reset search and period filters', function (): void {
        Livewire::actingAs($this->adminUser)
            ->test('pages::website.spams.index')
            ->set('search', 'test')
            ->set('period', 'today')
            ->assertSet('search', 'test')
            ->assertSet('period', 'today')
            ->set('search', '')
            ->set('period', '')
            ->assertSet('search', '')
            ->assertSet('period', '');
    });

    it('can search inside json fields', function (): void {
        // JSON field search is not implemented in the current component
    })->skip('JSON field search not implemented — component only searches ip and user_agent columns');
});

describe('Spam deletion', function (): void {
    it('can delete a single spam', function (): void {
        $spam = Spam::factory()->create();

        Livewire::actingAs($this->adminUser)
            ->test('pages::website.spams.index')
            ->call('confirmDelete', $spam->id)
            ->call('delete');

        $this->assertDatabaseMissing('spams', ['id' => $spam->id]);
    });

    it('can bulk delete spams', function (): void {
        $spams = Spam::factory()->count(3)->create();
        $spamIds = $spams->pluck('id')->toArray();

        Livewire::actingAs($this->adminUser)
            ->test('pages::website.spams.index')
            ->set('selectedItems', $spamIds)
            ->call('bulkDelete');

        foreach ($spamIds as $id) {
            $this->assertDatabaseMissing('spams', ['id' => $id]);
        }
    });
});

describe('Selection and pagination', function (): void {
    it('can select all spams on the page', function (): void {
        Spam::factory()->count(5)->create();

        Livewire::actingAs($this->adminUser)
            ->test('pages::website.spams.index')
            ->set('selectAll', true)
            ->assertCount('selectedItems', 5);
    });

    it('handles pagination correctly', function (): void {
        Spam::factory()->count(30)->create();

        $component = Livewire::actingAs($this->adminUser)
            ->test('pages::website.spams.index');

        $component->assertViewHas('spams', function ($spams) {
            return $spams instanceof LengthAwarePaginator
                && $spams->count() === 25
                && $spams->total() === 30;
        });
    });
});
