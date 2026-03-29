<?php

declare(strict_types=1);

use App\Models\ClubEvents\Tournament\Tournament;
use App\Support\Breadcrumb;
use Illuminate\Support\Facades\Route;

dataset('simple_methods', [
    ['articles',      'Articles',       'clubPosts.newsPosts.index',            '/custom-articles',      's-home'],
    ['contacts',      'Contacts',       'clubAdmin.contacts.index',             '/custom-contacts',      null],
    ['events',        'Events',         'clubPosts.eventPosts.index',           '/custom-events',        's-home'],
    ['matches',       'Matches',        'interclubs.index',                     '/custom-matches',       's-home'],
    ['profile',       'Profile',        'profile.edit',                         '/custom-profile',       null],
    ['rooms',         'Rooms',          'rooms.index',                          '/custom-rooms',         null],
    ['seasons',       'Seasons',        'clubEvents.interclubs.seasons.index',  '/custom-seasons',       'o-calendar'],
    ['subscriptions', 'Subscriptions',  'clubAdmin.subscriptions.index',        '/custom-subscriptions', 'o-calendar'],
    ['tables',        'Tables',         'tables.index',                         '/custom-tables',         null],
    ['teams',         'Teams',          'teams.index',                          '/custom-teams',          null],
    ['trainingPacks', 'Training Packs', 'admin.trainingpacks.index',            '/custom-training-packs', null],
    ['trainings',     'Trainings',      'trainings.index',                      '/custom-trainings',      null],
]);

describe('Breadcrumb', function (): void {
    it('can be instantiated using make method', function (): void {
        $breadcrumb = Breadcrumb::make();

        expect($breadcrumb)->toBeInstanceOf(Breadcrumb::class);
    });

    it('starts with empty items array', function (): void {
        $breadcrumb = Breadcrumb::make();

        expect($breadcrumb->toArray())->toBe([]);
    });

    describe('add method', function (): void {
        it('can add a basic item with title only', function (): void {
            $breadcrumb = Breadcrumb::make()->add('Test Title');

            expect($breadcrumb->toArray())->toBe([
                ['label' => 'Test Title', 'link' => null, 'icon' => null],
            ]);
        });

        it('can add an item with title and url', function (): void {
            $breadcrumb = Breadcrumb::make()->add('Test Title', '/test-url');

            expect($breadcrumb->toArray())->toBe([
                ['label' => 'Test Title', 'link' => '/test-url', 'icon' => null],
            ]);
        });

        it('can add an item with title, url, and icon', function (): void {
            $breadcrumb = Breadcrumb::make()->add('Test Title', '/test-url', 'test-icon');

            expect($breadcrumb->toArray())->toBe([
                ['label' => 'Test Title', 'link' => '/test-url', 'icon' => 'test-icon'],
            ]);
        });

        it('returns self for method chaining', function (): void {
            $breadcrumb = Breadcrumb::make();
            $result = $breadcrumb->add('Test');

            expect($result)->toBe($breadcrumb);
        });

        it('can chain multiple add calls', function (): void {
            $breadcrumb = Breadcrumb::make()
                ->add('First', '/first')
                ->add('Second', '/second');

            expect($breadcrumb->toArray())->toBe([
                ['label' => 'First', 'link' => '/first', 'icon' => null],
                ['label' => 'Second', 'link' => '/second', 'icon' => null],
            ]);
        });
    });

    describe('current method', function (): void {
        it('adds current page breadcrumb without url', function (): void {
            $breadcrumb = Breadcrumb::make()->current('Current Page');

            expect($breadcrumb->toArray())->toBe([
                ['label' => 'Current Page', 'link' => null, 'icon' => null],
            ]);
        });

        it('returns self for method chaining', function (): void {
            $breadcrumb = Breadcrumb::make();
            $result = $breadcrumb->current('Test');

            expect($result)->toBe($breadcrumb);
        });
    });

    describe('complex breadcrumb chains', function (): void {
        it('can build a complete breadcrumb navigation', function (): void {
            // Define routes
            Route::get('/dashboard', fn () => 'dashboard')->name('dashboard');
            Route::get('/tournaments', fn () => 'tournaments')->name('tournaments.index');
            Route::get('/tournaments/{tournament}', fn ($tournament) => 'tournament')->name('tournaments.show');

            $tournament = Tournament::factory()->create(['name' => 'World Cup 2024']);

            $breadcrumb = Breadcrumb::make()
                ->home()
                ->tournaments()
                ->tournament($tournament)
                ->current('Edit');

            $items = $breadcrumb->toArray();

            expect($items)->toHaveCount(4)
                ->and($items[0]['label'])->toBe('Admin Pannel')
                ->and($items[0]['icon'])->toBe('s-home')
                ->and($items[1]['label'])->toBe('Tournaments')
                ->and($items[2]['label'])->toBe('World Cup 2024')
                ->and($items[2]['link'])->toContain('/tournament/' . $tournament->id)
                ->and($items[3]['label'])->toBe('Edit')
                ->and($items[3]['link'])->toBe(null);
        });

        it('can build user management breadcrumb', function (): void {
            Route::get('/dashboard', fn () => 'dashboard')->name('dashboard');
            Route::get('/users', fn () => 'users')->name('users.index');

            $breadcrumb = Breadcrumb::make()
                ->home()
                ->users()
                ->current('Create User');

            $items = $breadcrumb->toArray();

            expect($items)->toHaveCount(3)
                ->and($items[0]['label'])->toBe('Admin Pannel')
                ->and($items[1]['label'])->toBe('Users')
                ->and($items[2]['label'])->toBe('Create User')
                ->and($items[2]['link'])->toBe(null);
        });

        it('can mix predefined and custom breadcrumbs', function (): void {
            Route::get('/dashboard', fn () => 'dashboard')->name('dashboard');

            $breadcrumb = Breadcrumb::make()
                ->home()
                ->add('Settings', '/settings', 'cog')
                ->current('Profile');

            $items = $breadcrumb->toArray();

            expect($items)->toHaveCount(3)
                ->and($items[0]['label'])->toBe('Admin Pannel')
                ->and($items[1]['label'])->toBe('Settings')
                ->and($items[1]['link'])->toBe('/settings')
                ->and($items[1]['icon'])->toBe('cog')
                ->and($items[2]['label'])->toBe('Profile')
                ->and($items[2]['link'])->toBe(null);
        });
    });

    describe('edge cases', function (): void {
        it('handles empty string title', function (): void {
            $breadcrumb = Breadcrumb::make()->add('');

            expect($breadcrumb->toArray())->toBe([
                ['label' => '', 'link' => null, 'icon' => null],
            ]);
        });

        it('handles null values properly', function (): void {
            $breadcrumb = Breadcrumb::make()->add('Test', null, null);

            expect($breadcrumb->toArray())->toBe([
                ['label' => 'Test', 'link' => null, 'icon' => null],
            ]);
        });

        it('handles tournament with special characters in name', function (): void {
            Route::get('/tournaments/{tournament}', fn ($tournament) => 'tournament')->name('tournaments.show');

            $tournament = Tournament::factory()->create(['name' => 'Tournament & Championship 2024']);

            $breadcrumb = Breadcrumb::make()->tournament($tournament);
            $items = $breadcrumb->toArray();

            expect($items[0]['label'])->toBe('Tournament & Championship 2024')
                ->and($items[0]['link'])->toContain('/tournament/' . $tournament->id);
        });
    });

    describe('toArray method', function (): void {
        it('returns array representation of breadcrumbs', function (): void {
            $breadcrumb = Breadcrumb::make()
                ->add('First')
                ->add('Second', '/second');

            $result = $breadcrumb->toArray();

            expect($result)->toBeArray()
                ->and($result)->toHaveCount(2);
        });

        it('returns empty array when no items added', function (): void {
            $breadcrumb = Breadcrumb::make();

            expect($breadcrumb->toArray())->toBe([]);
        });
    });

    describe('can generate all breadcrumbs types', function (): void {
        it('adds breadcrumb with correct icon', function (string $method, string $label, string $routeName, string $customUrl, ?string $icon): void {
            Route::get('/' . $method, fn () => '')->name($routeName);

            $items = Breadcrumb::make()->{$method}()->toArray();

            expect($items[0]['icon'])->toBe($icon);

        })->with('simple_methods');

    });
})->group('Breadcrumbs');
