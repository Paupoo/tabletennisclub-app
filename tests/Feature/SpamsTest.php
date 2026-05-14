<?php

declare(strict_types=1);

use App\Models\ClubAdmin\Contact\Spam;
use App\Models\ClubAdmin\Users\User;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->admin = User::factory()->create(['is_admin' => true]);
});

describe('Spams index', function (): void {
    it('redirects guests to login', function (): void {
        $this->get(route('admin.website.spams.index'))
            ->assertRedirect(route('login'));
    });

    it('is accessible to admins', function (): void {
        $this->actingAs($this->admin)
            ->get(route('admin.website.spams.index'))
            ->assertOk();
    });

    it('lists spam entries', function (): void {
        Spam::factory()->create(['ip' => '10.0.0.1']);

        Livewire::actingAs($this->admin)
            ->test('pages::website.spams.index')
            ->assertSee('10.0.0.1');
    });

    it('filters by search (IP)', function (): void {
        Spam::factory()->create(['ip' => '192.168.1.1']);
        Spam::factory()->create(['ip' => '10.20.30.40']);

        Livewire::actingAs($this->admin)
            ->test('pages::website.spams.index')
            ->set('search', '192.168')
            ->assertSee('192.168.1.1')
            ->assertDontSee('10.20.30.40');
    });

    it('filters by period today', function (): void {
        Spam::factory()->create(['ip' => '1.1.1.1', 'created_at' => now()]);
        Spam::factory()->create(['ip' => '2.2.2.2', 'created_at' => now()->subMonth()]);

        Livewire::actingAs($this->admin)
            ->test('pages::website.spams.index')
            ->set('period', 'today')
            ->assertSee('1.1.1.1')
            ->assertDontSee('2.2.2.2');
    });

    it('deletes a spam entry', function (): void {
        $spam = Spam::factory()->create();

        Livewire::actingAs($this->admin)
            ->test('pages::website.spams.index')
            ->call('confirmDelete', $spam->id)
            ->call('delete');

        expect(Spam::find($spam->id))->toBeNull();
    });

    it('bulk deletes selected spam entries', function (): void {
        $spams = Spam::factory()->count(3)->create();
        $ids = $spams->pluck('id')->toArray();

        Livewire::actingAs($this->admin)
            ->test('pages::website.spams.index')
            ->set('selectedItems', $ids)
            ->set('bulkDeleteModal', true)
            ->call('bulkDelete');

        expect(Spam::whereIn('id', $ids)->count())->toBe(0);
    });

    it('shows stats reflecting database', function (): void {
        Spam::factory()->count(5)->create();

        Livewire::actingAs($this->admin)
            ->test('pages::website.spams.index')
            ->assertSeeHtml('5');
    });
});
