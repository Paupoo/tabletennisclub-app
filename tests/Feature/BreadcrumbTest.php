<?php

use App\Support\Breadcrumb;
use Illuminate\Support\Facades\Route;

describe('Breadcrumb', function () {
    it('can be instantiated using make method', function () {
        $breadcrumb = Breadcrumb::make();
        
        expect($breadcrumb)->toBeInstanceOf(Breadcrumb::class);
    });

    it('starts with empty items array', function () {
        $breadcrumb = Breadcrumb::make();
        
        expect($breadcrumb->toArray())->toBe([]);
    });

    describe('add method', function () {
        it('can add a basic item with title only', function () {
            $breadcrumb = Breadcrumb::make()->add('Test Title');
            
            expect($breadcrumb->toArray())->toBe([
                ['title' => 'Test Title', 'url' => null, 'icon' => null]
            ]);
        });

        it('can add an item with title and url', function () {
            $breadcrumb = Breadcrumb::make()->add('Test Title', '/test-url');
            
            expect($breadcrumb->toArray())->toBe([
                ['title' => 'Test Title', 'url' => '/test-url', 'icon' => null]
            ]);
        });

        it('can add an item with title, url, and icon', function () {
            $breadcrumb = Breadcrumb::make()->add('Test Title', '/test-url', 'test-icon');
            
            expect($breadcrumb->toArray())->toBe([
                ['title' => 'Test Title', 'url' => '/test-url', 'icon' => 'test-icon']
            ]);
        });

        it('returns self for method chaining', function () {
            $breadcrumb = Breadcrumb::make();
            $result = $breadcrumb->add('Test');
            
            expect($result)->toBe($breadcrumb);
        });

        it('can chain multiple add calls', function () {
            $breadcrumb = Breadcrumb::make()
                ->add('First', '/first')
                ->add('Second', '/second');
            
            expect($breadcrumb->toArray())->toBe([
                ['title' => 'First', 'url' => '/first', 'icon' => null],
                ['title' => 'Second', 'url' => '/second', 'icon' => null]
            ]);
        });
    });

    describe('home method', function () {
        it('adds home breadcrumb with default route', function () {
            Route::get('/dashboard', fn() => 'dashboard')->name('dashboard');
            
            $breadcrumb = Breadcrumb::make()->home();
            $items = $breadcrumb->toArray();
            
            expect($items)->toHaveCount(1)
                ->and($items[0]['title'])->toBe('Admin')
                ->and($items[0]['icon'])->toBe('home')
                ->and($items[0]['url'])->toContain('/dashboard');
        });

        it('adds home breadcrumb with custom url', function () {
            $breadcrumb = Breadcrumb::make()->home('/custom-home');
            
            expect($breadcrumb->toArray())->toBe([
                ['title' => 'Admin', 'url' => '/custom-home', 'icon' => 'home']
            ]);
        });

        it('returns self for method chaining', function () {
            $breadcrumb = Breadcrumb::make();
            $result = $breadcrumb->home();
            
            expect($result)->toBe($breadcrumb);
        });
    });

    describe('tournaments method', function () {
        it('adds tournaments breadcrumb with default route', function () {
            Route::get('/tournaments', fn() => 'tournaments')->name('tournaments.index');
            
            $breadcrumb = Breadcrumb::make()->tournaments();
            $items = $breadcrumb->toArray();
            
            expect($items)->toHaveCount(1)
                ->and($items[0]['title'])->toBe('Tournaments')
                ->and($items[0]['icon'])->toBe(null)
                ->and($items[0]['url'])->toContain('/tournaments');
        });

        it('adds tournaments breadcrumb with custom url', function () {
            $breadcrumb = Breadcrumb::make()->tournaments('/custom-tournaments');
            
            expect($breadcrumb->toArray())->toBe([
                ['title' => 'Tournaments', 'url' => '/custom-tournaments', 'icon' => null]
            ]);
        });

        it('returns self for method chaining', function () {
            $breadcrumb = Breadcrumb::make();
            $result = $breadcrumb->tournaments();
            
            expect($result)->toBe($breadcrumb);
        });
    });

    describe('users method', function () {
        it('adds users breadcrumb with default route', function () {
            Route::get('/users', fn() => 'users')->name('users.index');
            
            $breadcrumb = Breadcrumb::make()->users();
            $items = $breadcrumb->toArray();
            
            expect($items)->toHaveCount(1)
                ->and($items[0]['title'])->toBe('Users')
                ->and($items[0]['icon'])->toBe(null)
                ->and($items[0]['url'])->toContain('/users');
        });

        it('adds users breadcrumb with custom url', function () {
            $breadcrumb = Breadcrumb::make()->users('/custom-users');
            
            expect($breadcrumb->toArray())->toBe([
                ['title' => 'Users', 'url' => '/custom-users', 'icon' => null]
            ]);
        });

        it('returns self for method chaining', function () {
            $breadcrumb = Breadcrumb::make();
            $result = $breadcrumb->users();
            
            expect($result)->toBe($breadcrumb);
        });
    });

    describe('tournament method', function () {
        it('adds tournament breadcrumb with tournament object', function () {
            Route::get('/tournaments/{tournament}', fn($tournament) => 'tournament')->name('tournaments.show');
            
            // Create a mock tournament that behaves like an Eloquent model
            $tournament = new class {
                public $id = 1;
                public $name = 'Championship 2024';
                
                public function getRouteKey()
                {
                    return $this->id;
                }
                
                public function __toString()
                {
                    return (string) $this->id;
                }
            };
            
            $breadcrumb = Breadcrumb::make()->tournament($tournament);
            $items = $breadcrumb->toArray();
            
            expect($items)->toHaveCount(1)
                ->and($items[0]['title'])->toBe('Championship 2024')
                ->and($items[0]['icon'])->toBe(null)
                ->and($items[0]['url'])->toContain('/tournaments/1');
        });

        it('returns self for method chaining', function () {
            Route::get('/tournaments/{tournament}', fn($tournament) => 'tournament')->name('tournaments.show');
            
            $tournament = new class {
                public $id = 1;
                public $name = 'Test Tournament';
                
                public function getRouteKey()
                {
                    return $this->id;
                }
                
                public function __toString()
                {
                    return (string) $this->id;
                }
            };
            
            $breadcrumb = Breadcrumb::make();
            $result = $breadcrumb->tournament($tournament);
            
            expect($result)->toBe($breadcrumb);
        });
    });

    describe('current method', function () {
        it('adds current page breadcrumb without url', function () {
            $breadcrumb = Breadcrumb::make()->current('Current Page');
            
            expect($breadcrumb->toArray())->toBe([
                ['title' => 'Current Page', 'url' => null, 'icon' => null]
            ]);
        });

        it('returns self for method chaining', function () {
            $breadcrumb = Breadcrumb::make();
            $result = $breadcrumb->current('Test');
            
            expect($result)->toBe($breadcrumb);
        });
    });

    describe('complex breadcrumb chains', function () {
        it('can build a complete breadcrumb navigation', function () {
            // Define routes
            Route::get('/dashboard', fn() => 'dashboard')->name('dashboard');
            Route::get('/tournaments', fn() => 'tournaments')->name('tournaments.index');
            Route::get('/tournaments/{tournament}', fn($tournament) => 'tournament')->name('tournaments.show');
            
            $tournament = new class {
                public $id = 5;
                public $name = 'World Cup 2024';
                
                public function getRouteKey()
                {
                    return $this->id;
                }
                
                public function __toString()
                {
                    return (string) $this->id;
                }
            };
            
            $breadcrumb = Breadcrumb::make()
                ->home()
                ->tournaments()
                ->tournament($tournament)
                ->current('Edit');
            
            $items = $breadcrumb->toArray();
            
            expect($items)->toHaveCount(4)
                ->and($items[0]['title'])->toBe('Admin')
                ->and($items[0]['icon'])->toBe('home')
                ->and($items[1]['title'])->toBe('Tournaments')
                ->and($items[2]['title'])->toBe('World Cup 2024')
                ->and($items[2]['url'])->toContain('/tournaments/5')
                ->and($items[3]['title'])->toBe('Edit')
                ->and($items[3]['url'])->toBe(null);
        });

        it('can build user management breadcrumb', function () {
            Route::get('/dashboard', fn() => 'dashboard')->name('dashboard');
            Route::get('/users', fn() => 'users')->name('users.index');
            
            $breadcrumb = Breadcrumb::make()
                ->home()
                ->users()
                ->current('Create User');
            
            $items = $breadcrumb->toArray();
            
            expect($items)->toHaveCount(3)
                ->and($items[0]['title'])->toBe('Admin')
                ->and($items[1]['title'])->toBe('Users')
                ->and($items[2]['title'])->toBe('Create User')
                ->and($items[2]['url'])->toBe(null);
        });

        it('can mix predefined and custom breadcrumbs', function () {
            Route::get('/dashboard', fn() => 'dashboard')->name('dashboard');
            
            $breadcrumb = Breadcrumb::make()
                ->home()
                ->add('Settings', '/settings', 'cog')
                ->current('Profile');
            
            $items = $breadcrumb->toArray();
            
            expect($items)->toHaveCount(3)
                ->and($items[0]['title'])->toBe('Admin')
                ->and($items[1]['title'])->toBe('Settings')
                ->and($items[1]['url'])->toBe('/settings')
                ->and($items[1]['icon'])->toBe('cog')
                ->and($items[2]['title'])->toBe('Profile')
                ->and($items[2]['url'])->toBe(null);
        });
    });

    describe('edge cases', function () {
        it('handles empty string title', function () {
            $breadcrumb = Breadcrumb::make()->add('');
            
            expect($breadcrumb->toArray())->toBe([
                ['title' => '', 'url' => null, 'icon' => null]
            ]);
        });

        it('handles null values properly', function () {
            $breadcrumb = Breadcrumb::make()->add('Test', null, null);
            
            expect($breadcrumb->toArray())->toBe([
                ['title' => 'Test', 'url' => null, 'icon' => null]
            ]);
        });

        it('handles tournament with special characters in name', function () {
            Route::get('/tournaments/{tournament}', fn($tournament) => 'tournament')->name('tournaments.show');
            
            $tournament = new class {
                public $id = 1;
                public $name = 'Tournament & Championship 2024';
                
                public function getRouteKey()
                {
                    return $this->id;
                }
                
                public function __toString()
                {
                    return (string) $this->id;
                }
            };
            
            $breadcrumb = Breadcrumb::make()->tournament($tournament);
            $items = $breadcrumb->toArray();
            
            expect($items[0]['title'])->toBe('Tournament & Championship 2024')
                ->and($items[0]['url'])->toContain('/tournaments/1');
        });
    });

    describe('toArray method', function () {
        it('returns array representation of breadcrumbs', function () {
            $breadcrumb = Breadcrumb::make()
                ->add('First')
                ->add('Second', '/second');
            
            $result = $breadcrumb->toArray();
            
            expect($result)->toBeArray()
                ->and($result)->toHaveCount(2);
        });

        it('returns empty array when no items added', function () {
            $breadcrumb = Breadcrumb::make();
            
            expect($breadcrumb->toArray())->toBe([]);
        });
    });
});