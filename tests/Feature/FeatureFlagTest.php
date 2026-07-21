<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\ClubPosts\Models\EventPost;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Shared\Enums\EventPostStatusEnum;
use App\Domains\Shared\Enums\Feature;
use App\Http\Middleware\EnsureFeatureIsEnabled;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

/*
| A half-disabled feature is worse than an enabled one: a member clicks a menu
| entry and lands on a 404, or keeps receiving mails about a domain that no
| longer exists for them. So each flag is checked on all four surfaces — routes,
| navigation, scheduled tasks, public calendar.
*/

function disableFeature(Feature $feature): void
{
    config(["features.{$feature->value}" => false]);
}

describe('the flags themselves', function (): void {
    it('defaults every domain to enabled', function (Feature $feature): void {
        expect($feature->enabled())->toBeTrue();
    })->with(Feature::cases());

    it('declares exactly the keys the config file holds', function (): void {
        $enumKeys = array_map(static fn (Feature $f): string => $f->value, Feature::cases());
        $configKeys = array_keys(config('features'));

        sort($enumKeys);
        sort($configKeys);

        expect($configKeys)->toBe($enumKeys);
    });

    it('reads the flag from config', function (): void {
        disableFeature(Feature::Bar);

        expect(Feature::Bar->enabled())->toBeFalse()
            ->and(Feature::Bar->disabled())->toBeTrue();
    });
});

describe('surface 1 — routes', function (): void {
    it('answers 404 rather than 403 on a disabled domain', function (): void {
        disableFeature(Feature::Meetings);

        $this->actingAs(User::factory()->isAdmin()->isCommitteeMember()->create())
            ->get(route('admin.meetings.index'))
            ->assertNotFound();
    });

    it('still serves the domain when the flag is on', function (): void {
        $this->actingAs(User::factory()->isAdmin()->isCommitteeMember()->create())
            ->get(route('admin.meetings.index'))
            ->assertOk();
    });

    it('hides a disabled domain even from an administrator', function (): void {
        disableFeature(Feature::Tournaments);

        $this->actingAs(User::factory()->isAdmin()->isCommitteeMember()->create())
            ->get(route('admin.tournaments.index'))
            ->assertNotFound();
    });

    it('leaves the other domains untouched', function (): void {
        disableFeature(Feature::Tournaments);

        $this->actingAs(User::factory()->isAdmin()->isCommitteeMember()->create())
            ->get(route('admin.meetings.index'))
            ->assertOk();
    });

    it('refuses to boot on an unknown flag name', function (): void {
        $middleware = new EnsureFeatureIsEnabled;

        $middleware->handle(
            request(),
            fn () => response('ok'),
            'domaine-inexistant',
        );
    })->throws(HttpException::class);
});

describe('surface 2 — navigation', function (): void {
    it('drops the menu entry of a disabled domain', function (): void {
        $user = User::factory()->isAdmin()->isCommitteeMember()->create();
        $this->actingAs($user);

        // The newlines matter: Blade only recognises a directive at a non-word
        // boundary, so `texte@endfeature` is left uncompiled — as in real templates,
        // the directive sits on its own line.
        $template = "@feature('meetings')\nvisible\n@endfeature";

        expect(Blade::render($template))->toContain('visible');

        disableFeature(Feature::Meetings);

        expect(Blade::render($template))->not->toContain('visible');
    });

    it('keeps a grouping menu as soon as one of its domains is on', function (): void {
        disableFeature(Feature::Meetings);

        expect(Blade::render("@feature('meetings', 'tournaments')\ngroupe\n@endfeature"))
            ->toContain('groupe');
    });

    it('drops the grouping menu once every domain in it is off', function (): void {
        disableFeature(Feature::Meetings);
        disableFeature(Feature::Tournaments);

        expect(Blade::render("@feature('meetings', 'tournaments')\ngroupe\n@endfeature"))
            ->not->toContain('groupe');
    });

    it('never leaves a link to a disabled domain in the sidebar', function (): void {
        disableFeature(Feature::Tournaments);
        disableFeature(Feature::Meetings);

        $this->actingAs(User::factory()->isAdmin()->isCommitteeMember()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('admin.tournaments.index'))
            ->assertDontSee(route('admin.meetings.index'));
    });
});

describe('surface 3 — scheduled tasks', function (): void {
    it('stops scheduling the commands of a disabled domain', function (): void {
        disableFeature(Feature::Tournaments);

        $due = collect(app(Schedule::class)->events())
            ->filter(fn ($event): bool => $event->filtersPass(app()))
            ->map(fn ($event): string => (string) $event->command);

        expect($due->filter(fn (string $c): bool => str_contains($c, 'tournament:')))
            ->toBeEmpty();
    });

    it('keeps scheduling the commands of the domains still on', function (): void {
        disableFeature(Feature::Tournaments);

        $due = collect(app(Schedule::class)->events())
            ->filter(fn ($event): bool => $event->filtersPass(app()))
            ->map(fn ($event): string => (string) $event->command);

        expect($due->filter(fn (string $c): bool => str_contains($c, 'training:')))
            ->not->toBeEmpty();
    });
});

describe('surface 4 — public calendar', function (): void {
    it('hides the public entries of a disabled domain', function (): void {
        $tournament = Tournament::factory()->create();
        EventPost::factory()->create([
            'status' => EventPostStatusEnum::PUBLISHED,
            'eventable_type' => Tournament::class,
            'eventable_id' => $tournament->id,
        ]);

        expect(EventPost::published()->count())->toBe(1);

        disableFeature(Feature::Tournaments);

        expect(EventPost::published()->count())->toBe(0);
    });

    it('keeps standalone community events, which belong to no domain', function (): void {
        EventPost::factory()->create([
            'status' => EventPostStatusEnum::PUBLISHED,
            'eventable_type' => null,
            'eventable_id' => null,
        ]);

        disableFeature(Feature::Tournaments);
        disableFeature(Feature::Meetings);

        expect(EventPost::published()->count())->toBe(1);
    });
});
