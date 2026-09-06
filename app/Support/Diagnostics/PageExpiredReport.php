<?php

declare(strict_types=1);

namespace App\Support\Diagnostics;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Throwable;

/**
 * What a 419 knew about itself, written down so the next one can be explained.
 *
 * Approving an affiliation and then clicking "send by email" answers "This page
 * has expired" on the production host and nowhere else (issue #36). The dialog
 * is Livewire's own handler for an HTTP 419, so the CSRF check failed on the
 * server — but a 419 says nothing about *why*, and the bug has never been
 * reproduced locally, which is exactly the situation where guessing is
 * expensive and one good log line is cheap.
 *
 * The three candidate causes leave different fingerprints, and the point of
 * this class is to tell them apart on the next occurrence:
 *
 * - the session simply expired while the modal sat open — the cookie arrives,
 *   and the server reads nothing back under that id;
 * - a reverse proxy or cookie setting drops the cookie — nothing arrives even
 *   though the page was just rendered;
 * - a deployment rotated APP_KEY under the open page — the cookie arrives and
 *   cannot be decrypted, which the framework turns into a null value while
 *   keeping the key present.
 *
 * Neither the session id nor the CSRF token is written down, in any form: only
 * what can be concluded from them. A log line is the wrong place to hand
 * somebody a live session, and the answers here need no more than a label.
 */
final class PageExpiredReport
{
    /**
     * Everything worth knowing about one rejected request.
     *
     * Wrapped whole: a diagnostic that throws would turn a 419 the user can
     * recover from into a 500 they cannot, which would be a worse bug than the
     * one being chased.
     *
     * @return array<string, mixed>
     */
    public static function context(Request $request): array
    {
        try {
            return [
                'path' => $request->path(),
                'method' => $request->method(),
                'cookie' => self::cookieState($request),
                'session' => self::sessionState($request),
                'session_keys' => $request->hasSession() ? self::carriedKeys($request) : null,
                'token' => self::tokenState($request),
                'body_bytes' => strlen($request->getContent()),
                'content_length' => $request->header('Content-Length'),
                'livewire_call' => self::livewireCall($request),
                'user_id' => $request->user()?->id,
                'session_driver' => config('session.driver'),
                'session_lifetime_minutes' => (int) config('session.lifetime'),
                'referer' => $request->header('Referer'),
            ];
        } catch (Throwable $e) {
            return ['diagnostic_failed' => $e->getMessage()];
        }
    }

    /**
     * How much the session held, ignoring the framework's own bookkeeping.
     *
     * `_token` is regenerated on every start, so a session read back as
     * nothing still has one; counting it would make every dead session look
     * alive. `_previous` and `_flash` are written on the way out, not read on
     * the way in, but they are excluded for the same reason.
     */
    private static function carriedKeys(Request $request): int
    {
        return count(Arr::except($request->session()->all(), ['_token', '_previous', '_flash']));
    }

    /**
     * Whether the session cookie arrived, and whether it could be read.
     *
     * EncryptCookies keeps the key and nulls the value when decryption fails,
     * so "sent but unreadable" is distinguishable from "not sent" — and that is
     * the whole signature of an APP_KEY rotated under an open page.
     */
    private static function cookieState(Request $request): string
    {
        $name = (string) config('session.cookie');

        if (! $request->cookies->has($name)) {
            return 'absent';
        }

        return $request->cookies->get($name) === null ? 'unreadable' : 'present';
    }

    /**
     * The Livewire action that was refused, when the request is one.
     *
     * Issue #36 names a single button, so knowing whether every 419 lands on
     * that one call or spreads across the back office splits "this action is
     * special" from "this browser tab went stale".
     */
    private static function livewireCall(Request $request): ?string
    {
        if (! $request->hasHeader('X-Livewire')) {
            return null;
        }

        $name = $request->input('components.0.snapshot.memo.name');
        $method = $request->input('components.0.calls.0.method');

        return is_string($name) || is_string($method)
            ? trim((is_string($name) ? $name : '?') . '::' . (is_string($method) ? $method : '?'))
            : 'unknown';
    }

    /**
     * Whether the server still held the session the cookie pointed at.
     *
     * `empty` is the telling one, and it is why this cannot be answered by
     * comparing ids. StartSession does `setId($cookieValue)` before starting,
     * and Store::readFromHandler() returns an empty array for a session file
     * that expired or was swept — *without touching the id*. So a dead session
     * comes back wearing the browser's own id, and an id comparison would call
     * it alive. What actually distinguishes the two is whether anything was
     * read back.
     *
     * `alive` therefore means the session carried real data and the token
     * itself was wrong — a stale page, a duplicate submit, a cached snapshot.
     * `empty` means the browser named a session the server no longer had:
     * expired, garbage-collected, or living on another application instance.
     */
    private static function sessionState(Request $request): string
    {
        if (! $request->hasSession()) {
            return 'none';
        }

        $fromCookie = $request->cookies->get((string) config('session.cookie'));

        if (! is_string($fromCookie) || $fromCookie === '') {
            return 'no_cookie';
        }

        return self::carriedKeys($request) === 0 ? 'empty' : 'alive';
    }

    /**
     * Where the CSRF token came from, or that none was supplied at all.
     *
     * `none` on a POST that carries a body is the fingerprint of a request
     * whose body never reached PHP — a `post_max_size` overrun, say — and it
     * would otherwise be indistinguishable from an expired session.
     */
    private static function tokenState(Request $request): string
    {
        return match (true) {
            filled($request->input('_token')) => 'field',
            filled($request->header('X-CSRF-TOKEN')) => 'header_csrf',
            filled($request->header('X-XSRF-TOKEN')) => 'header_xsrf',
            default => 'none',
        };
    }
}
