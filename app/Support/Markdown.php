<?php

declare(strict_types=1);

namespace App\Support;

use App\Support\Help\HelpArticle;
use Illuminate\Support\Str;

/**
 * Markdown rendering for content that came from a form.
 *
 * `Str::markdown()` inherits the CommonMark defaults, which are permissive on
 * purpose: `html_input: allow` passes raw HTML through untouched, and
 * `allow_unsafe_links: true` keeps `javascript:` URLs. Both are fine for
 * markdown that ships in the repository, and both are a stored XSS as soon as
 * the markdown is typed by a member and echoed with `{!! !!}`.
 *
 * Anything rendering an article, a tournament closing note or a live preview
 * goes through here. {@see HelpArticle::html()} deliberately
 * does not: its source is a .md file under resources/help, and escaping would
 * break the HTML the help pages use on purpose.
 */
final class Markdown
{
    /**
     * Render member-authored markdown with raw HTML escaped and unsafe link
     * schemes dropped.
     */
    public static function safe(string $markdown): string
    {
        return Str::markdown($markdown, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);
    }
}
