<?php

declare(strict_types=1);

use App\Models\ClubAdmin\Users\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

pest()->group('club-admin', 'users');

const USER_FORM_COMPONENT = 'pages::club-admin.users.form';

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true, 'is_coach' => false]);
    actingAs($this->admin);
});

describe('ranking — recreational users', function () {
    it('saves NA when updating a recreational user', function () {
        $user = User::factory()->create([
            'is_competitor' => false,
            'is_coach' => false,
            'ranking' => 'NA',
        ]);

        Livewire::test(USER_FORM_COMPONENT, ['user' => $user])
            ->set('licence_type', 'recreative')
            ->set('password', '')
            ->call('save');

        expect($user->fresh()->ranking)->toBe('NA');
    });

    it('does not throw a QueryException (no DB truncation) when saving a recreational user', function () {
        $user = User::factory()->create([
            'is_competitor' => false,
            'is_coach' => false,
        ]);

        expect(fn () => Livewire::test(USER_FORM_COMPONENT, ['user' => $user])
            ->set('licence_type', 'recreative')
            ->set('password', '')
            ->call('save')
        )->not->toThrow(QueryException::class);
    });

    it('initialises ranking to a valid enum value (never N/A with slash) when mounting', function () {
        $user = User::factory()->create([
            'is_competitor' => false,
            'is_coach' => false,
            'ranking' => 'NA',
        ]);

        $component = Livewire::test(USER_FORM_COMPONENT, ['user' => $user]);

        expect($component->get('ranking'))->not->toBe('N/A');
    });
});
