<?php

declare(strict_types=1);

use App\Enums\NewsPostStatusEnum;
use App\Enums\TournamentStatusEnum;
use App\Mail\TournamentResultsMail;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Tournament\Tournament;
use App\Models\ClubEvents\Tournament\TournamentMatch;
use App\Models\ClubPosts\NewsPost;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

// ── Helpers ───────────────────────────────────────────────────────────────────

function closureTournament(array $overrides = []): Tournament
{
    return Tournament::factory()->create(array_merge([
        'status' => TournamentStatusEnum::PENDING,
        'sets_to_win' => 3,
        'nb_pools' => 2,
        'nb_qualifiers_per_pool' => 2,
        'has_handicap_points' => false,
        'deuce_enabled' => false,
        'price' => 0,
    ], $overrides));
}

function mountLiveCenter(User $admin, Tournament $tournament)
{
    return Livewire::actingAs($admin)
        ->test('pages::club-events.tournaments.live-center', ['tournament' => $tournament]);
}

// ── Guard: allMatchesComplete ─────────────────────────────────────────────────

describe('closeTournament — guard: matches must be complete', function () {

    it('emits an error toast when matches are still pending', function () {
        $admin = User::factory()->isAdmin()->create();
        $tournament = closureTournament();

        $p1 = User::factory()->create();
        $p2 = User::factory()->create();
        $pool = $tournament->pools()->create(['name' => 'A']);

        TournamentMatch::create([
            'tournament_id' => $tournament->id,
            'pool_id' => $pool->id,
            'player1_id' => $p1->id,
            'player2_id' => $p2->id,
            'player1_handicap_points' => 0,
            'player2_handicap_points' => 0,
            'status' => 'scheduled',
            'match_order' => 1,
        ]);

        mountLiveCenter($admin, $tournament)
            ->call('closeTournament');

        // Guard returns early — tournament must remain open
        expect($tournament->fresh()->status)->not->toBe(TournamentStatusEnum::CLOSED);
    })->group('closure', 'guard');

    it('does not close the tournament when matches remain', function () {
        $admin = User::factory()->isAdmin()->create();
        $tournament = closureTournament();

        $p1 = User::factory()->create();
        $p2 = User::factory()->create();
        $pool = $tournament->pools()->create(['name' => 'A']);

        TournamentMatch::create([
            'tournament_id' => $tournament->id,
            'pool_id' => $pool->id,
            'player1_id' => $p1->id,
            'player2_id' => $p2->id,
            'player1_handicap_points' => 0,
            'player2_handicap_points' => 0,
            'status' => 'in_progress',
            'match_order' => 1,
        ]);

        mountLiveCenter($admin, $tournament)
            ->call('closeTournament');

        expect($tournament->fresh()->status)->not->toBe(TournamentStatusEnum::CLOSED);
    })->group('closure', 'guard');

})->group('closure');

// ── closeTournament — happy path ──────────────────────────────────────────────

describe('closeTournament — happy path', function () {

    it('sets tournament status to CLOSED when all matches are complete', function () {
        $admin = User::factory()->isAdmin()->create();
        $tournament = closureTournament();

        mountLiveCenter($admin, $tournament)
            ->set('sendThankYou', false)
            ->set('createNewsPost', false)
            ->call('closeTournament');

        expect($tournament->fresh()->status)->toBe(TournamentStatusEnum::CLOSED);
    })->group('closure', 'status');

    it('can close a tournament that has no matches at all', function () {
        $admin = User::factory()->isAdmin()->create();
        $tournament = closureTournament();

        mountLiveCenter($admin, $tournament)
            ->set('sendThankYou', false)
            ->set('createNewsPost', false)
            ->call('closeTournament');

        expect($tournament->fresh()->status)->toBe(TournamentStatusEnum::CLOSED);
    })->group('closure', 'status');

})->group('closure');

// ── closeTournament — thank-you email ─────────────────────────────────────────

describe('closeTournament — thank-you email', function () {

    it('queues one email per confirmed participant when sendThankYou is true', function () {
        Mail::fake();
        $admin = User::factory()->isAdmin()->create();
        $tournament = closureTournament();
        $participants = User::factory(3)->create();

        $tournament->users()->attach($participants->pluck('id'), [
            'registration_status' => 'confirmed',
        ]);

        mountLiveCenter($admin, $tournament)
            ->set('sendThankYou', true)
            ->set('thankYouSubject', 'Résultats — Open Printemps')
            ->set('thankYouBody', 'Merci à tous !')
            ->set('createNewsPost', false)
            ->call('closeTournament');

        Mail::assertQueued(TournamentResultsMail::class, 3);
    })->group('closure', 'email');

    it('uses the edited subject when queueing emails', function () {
        Mail::fake();
        $admin = User::factory()->isAdmin()->create();
        $tournament = closureTournament();
        $user = User::factory()->create();
        $tournament->users()->attach($user->id, ['registration_status' => 'confirmed']);

        mountLiveCenter($admin, $tournament)
            ->set('sendThankYou', true)
            ->set('thankYouSubject', 'Mon sujet personnalisé')
            ->set('thankYouBody', 'Corps du message')
            ->set('createNewsPost', false)
            ->call('closeTournament');

        Mail::assertQueued(TournamentResultsMail::class, fn ($m) => $m->emailSubject === 'Mon sujet personnalisé'
        );
    })->group('closure', 'email');

    it('does not queue any email when sendThankYou is false', function () {
        Mail::fake();
        $admin = User::factory()->isAdmin()->create();
        $tournament = closureTournament();
        $user = User::factory()->create();
        $tournament->users()->attach($user->id, ['registration_status' => 'confirmed']);

        mountLiveCenter($admin, $tournament)
            ->set('sendThankYou', false)
            ->set('createNewsPost', false)
            ->call('closeTournament');

        Mail::assertNothingQueued();
    })->group('closure', 'email');

    it('does not queue emails when subject or body is empty', function () {
        Mail::fake();
        $admin = User::factory()->isAdmin()->create();
        $tournament = closureTournament();
        $user = User::factory()->create();
        $tournament->users()->attach($user->id, ['registration_status' => 'confirmed']);

        mountLiveCenter($admin, $tournament)
            ->set('sendThankYou', true)
            ->set('thankYouSubject', '')
            ->set('thankYouBody', '')
            ->set('createNewsPost', false)
            ->call('closeTournament');

        Mail::assertNothingQueued();
    })->group('closure', 'email');

    it('does not email waitlisted or cancelled participants', function () {
        Mail::fake();
        $admin = User::factory()->isAdmin()->create();
        $tournament = closureTournament();

        $confirmed = User::factory()->create();
        $cancelled = User::factory()->create();
        $waiting = User::factory()->create();

        $tournament->users()->attach($confirmed->id, ['registration_status' => 'confirmed']);
        $tournament->users()->attach($cancelled->id, ['registration_status' => 'cancelled']);
        $tournament->users()->attach($waiting->id, [
            'registration_status' => 'waiting',
            'waitlist_position' => 1,
        ]);

        mountLiveCenter($admin, $tournament)
            ->set('sendThankYou', true)
            ->set('thankYouSubject', 'Résultats')
            ->set('thankYouBody', 'Merci')
            ->set('createNewsPost', false)
            ->call('closeTournament');

        Mail::assertQueued(TournamentResultsMail::class, 1);
    })->group('closure', 'email');

})->group('closure');

// ── closeTournament — news post ───────────────────────────────────────────────

describe('closeTournament — news post creation', function () {

    it('creates a draft news post when createNewsPost is true', function () {
        Storage::fake('public');
        $admin = User::factory()->isAdmin()->create();
        $tournament = closureTournament();

        mountLiveCenter($admin, $tournament)
            ->set('sendThankYou', false)
            ->set('createNewsPost', true)
            ->set('newsPostTitle', 'Résultats — Open Printemps')
            ->set('newsPostContent', '## Podium\n\n1. Alice\n2. Bob')
            ->call('closeTournament');

        $post = NewsPost::latest()->first();
        expect($post)->not->toBeNull()
            ->and($post->title)->toBe('Résultats — Open Printemps')
            ->and($post->status)->toBe(NewsPostStatusEnum::DRAFT);
    })->group('closure', 'newspost');

    it('links the news post to the tournament', function () {
        Storage::fake('public');
        $admin = User::factory()->isAdmin()->create();
        $tournament = closureTournament();

        mountLiveCenter($admin, $tournament)
            ->set('sendThankYou', false)
            ->set('createNewsPost', true)
            ->set('newsPostTitle', 'Résultats — Open Printemps')
            ->set('newsPostContent', '## Podium\n\n1. Alice')
            ->call('closeTournament');

        $tournament->refresh();
        expect($tournament->news_post_id)->not->toBeNull();

        $post = NewsPost::find($tournament->news_post_id);
        expect($post)->not->toBeNull()
            ->and($post->title)->toBe('Résultats — Open Printemps');
    })->group('closure', 'newspost');

    it('creates the news post as DRAFT not published', function () {
        Storage::fake('public');
        $admin = User::factory()->isAdmin()->create();
        $tournament = closureTournament();

        mountLiveCenter($admin, $tournament)
            ->set('sendThankYou', false)
            ->set('createNewsPost', true)
            ->set('newsPostTitle', 'Mon article')
            ->set('newsPostContent', 'Contenu de test')
            ->call('closeTournament');

        $post = NewsPost::latest()->first();
        expect($post->status)->toBe(NewsPostStatusEnum::DRAFT);
    })->group('closure', 'newspost');

    it('does not create a news post when createNewsPost is false', function () {
        $admin = User::factory()->isAdmin()->create();
        $tournament = closureTournament();
        $countBefore = NewsPost::count();

        mountLiveCenter($admin, $tournament)
            ->set('sendThankYou', false)
            ->set('createNewsPost', false)
            ->call('closeTournament');

        expect(NewsPost::count())->toBe($countBefore);
    })->group('closure', 'newspost');

    it('does not create a news post when title is empty', function () {
        $admin = User::factory()->isAdmin()->create();
        $tournament = closureTournament();
        $countBefore = NewsPost::count();

        mountLiveCenter($admin, $tournament)
            ->set('sendThankYou', false)
            ->set('createNewsPost', true)
            ->set('newsPostTitle', '')
            ->set('newsPostContent', 'Some content')
            ->call('closeTournament');

        expect(NewsPost::count())->toBe($countBefore);
    })->group('closure', 'newspost');

})->group('closure');

// ── fillClosureFromRankings ────────────────────────────────────────────────────

describe('fillClosureFromRankings', function () {

    it('populates thankYouBody with top 3 players from bracket results', function () {
        $admin = User::factory()->isAdmin()->create();
        $tournament = closureTournament();

        $champion = User::factory()->create(['first_name' => 'Alice', 'last_name' => 'Winner']);
        $runnerup = User::factory()->create(['first_name' => 'Bob', 'last_name' => 'Second']);

        // Create a completed final
        TournamentMatch::create([
            'tournament_id' => $tournament->id,
            'player1_id' => $champion->id,
            'player2_id' => $runnerup->id,
            'player1_handicap_points' => 0,
            'player2_handicap_points' => 0,
            'round' => 'final',
            'status' => 'completed',
            'winner_id' => $champion->id,
            'match_order' => 1,
        ]);

        $component = mountLiveCenter($admin, $tournament)
            ->call('fillClosureFromRankings');

        $body = $component->get('thankYouBody');
        expect($body)->toContain('Alice')
            ->and($body)->toContain('Bob');
    })->group('closure', 'rankings');

    it('populates newsPostContent with podium markdown', function () {
        $admin = User::factory()->isAdmin()->create();
        $tournament = closureTournament();

        $champion = User::factory()->create(['first_name' => 'Alice', 'last_name' => 'Winner']);
        $runnerup = User::factory()->create(['first_name' => 'Bob', 'last_name' => 'Second']);

        TournamentMatch::create([
            'tournament_id' => $tournament->id,
            'player1_id' => $champion->id,
            'player2_id' => $runnerup->id,
            'player1_handicap_points' => 0,
            'player2_handicap_points' => 0,
            'round' => 'final',
            'status' => 'completed',
            'winner_id' => $champion->id,
            'match_order' => 1,
        ]);

        $component = mountLiveCenter($admin, $tournament)
            ->call('fillClosureFromRankings');

        $content = $component->get('newsPostContent');
        expect($content)->toContain('Alice')
            ->and($content)->toContain('Bob');
    })->group('closure', 'rankings');

})->group('closure');
