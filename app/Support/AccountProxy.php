<?php

declare(strict_types=1);

namespace App\Support;

use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * The proxy a guardian holds over a managed account.
 *
 * A managed account — `users.email IS NULL`, the decision of issue #56 — has no
 * address, therefore no login of its own. Somebody has to act for it, and that
 * somebody is a guardian recorded in `guardian_user`.
 *
 * The proxy is deliberately expressed at the *authentication* layer rather than
 * the authorization one: while it is held, the authenticated user simply is the
 * ward, so all 119 call sites of `Auth::user()` — and every screen written from
 * now on — obey it without knowing it exists. The alternative, teaching each
 * page to accept a subject, would have to be remembered forever, once per
 * feature.
 *
 * Two consequences of that choice are handled here rather than left implicit:
 * the real person is kept in the session so the audit trail can name them —
 * AppServiceProvider hands that session value to Spatie's causer resolver — and
 * the short list of gestures a proxy may never make guards itself with
 * {@see denyWhenActing()}.
 */
final class AccountProxy
{
    /** Where the id of the person who actually signed in is kept. */
    public const string ORIGIN_KEY = 'account_proxy.origin_user_id';

    /**
     * Refuse a gesture that only an account's own holder may make.
     *
     * The deny list is short and lives at the call sites rather than in a route
     * middleware, because it is about single actions inside otherwise delegable
     * screens: a guardian sets a ward's notification preferences, but never
     * their password, and never asks for their erasure.
     */
    public static function denyWhenActing(): void
    {
        abort_if(self::isActing(), 403, __('This cannot be done on behalf of another member.'));
    }

    /** Whether the current session is being run on somebody else's behalf. */
    public static function isActing(): bool
    {
        return self::origin() instanceof User;
    }

    /**
     * The person who actually signed in, or null when nobody is acting.
     *
     * Resolved from the session on every call: a request that starts or stops a
     * proxy changes the answer halfway through, so caching it would lie. A
     * context with no session of its own — a queued job, a console command —
     * simply reads null.
     */
    public static function origin(): ?User
    {
        $originId = session(self::ORIGIN_KEY);

        if (! is_int($originId)) {
            return null;
        }

        return User::find($originId);
    }

    /**
     * Take the ward's seat, keeping the real identity in the session.
     *
     * Proxies never nest: switching from one ward to another is always
     * authorized against the person who signed in, never against the ward
     * currently being acted for.
     */
    public static function start(User $ward): void
    {
        $actor = self::origin() ?? Auth::user();

        abort_unless($actor instanceof User && $actor->mayActFor($ward), 403);

        // `Auth::login()` migrates the session id (and carries its data over),
        // so the origin has to be written after the switch, not before.
        Auth::login($ward);

        session()->put(self::ORIGIN_KEY, $actor->id);
    }

    /**
     * Give the seat back.
     *
     * @return User|null The person restored, or null when nobody was acting.
     */
    public static function stop(): ?User
    {
        $origin = self::origin();

        if (! $origin instanceof User) {
            return null;
        }

        Auth::login($origin);

        session()->forget(self::ORIGIN_KEY);

        return $origin;
    }
}
