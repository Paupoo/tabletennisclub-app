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
     * Make a value read as plain text once dropped into markdown.
     *
     * A contact's name substituted into a template must stay a name: without
     * this, a visitor typing `[click](https://…)` in the contact form would put
     * a link in the club's reply. CommonMark lets any ASCII punctuation be
     * backslash-escaped, so escaping all of it is always safe.
     */
    public static function escape(string $text): string
    {
        return (string) preg_replace('/[!-\/:-@\[-`{-~]/', '\\\\$0', $text);
    }

    /**
     * Turn text typed in a plain textarea into markdown that renders the same.
     *
     * Used once, when a field moved from a textarea to the markdown editor: a
     * blank line still separates paragraphs, a single line break becomes a hard
     * break (markdown would fold it into the paragraph), and characters that
     * would start a list, a heading or emphasis are escaped. `{{placeholder}}`
     * template variables are left untouched, `_` included.
     */
    public static function fromPlainText(string $text): string
    {
        $text = trim(str_replace(["\r\n", "\r"], "\n", $text));

        $paragraphs = preg_split('/\n\s*\n/', $text) ?: [];

        return implode("\n\n", array_map(
            static fn (string $paragraph): string => implode("\\\n", array_map(
                self::escapeLine(...),
                explode("\n", $paragraph),
            )),
            $paragraphs,
        ));
    }

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

    /**
     * Reduce markdown to readable plain text, for a place that shows no HTML
     * (a calendar file's description).
     */
    public static function toPlainText(string $markdown): string
    {
        // CommonMark already ends every tag with a line break; a block gets one
        // more, so paragraphs stay apart and list items stay together.
        $html = (string) preg_replace('#</(p|h[1-6]|ul|ol|blockquote|table|pre)>#i', "$0\n", self::safe($markdown));

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);

        return trim((string) preg_replace("/\n{3,}/", "\n\n", $text));
    }

    /**
     * Escape one line of plain text, placeholders excepted.
     *
     * Only what would format is escaped — emphasis, links, code, raw HTML,
     * entities, and a block marker opening the line — so a URL keeps its `:`
     * and `/` and is still turned into a link.
     */
    private static function escapeLine(string $line): string
    {
        $parts = preg_split('/(\{\{\s*\w+\s*\}\})/', trim($line), flags: PREG_SPLIT_DELIM_CAPTURE) ?: [];

        $escaped = implode('', array_map(
            static fn (string $part, int $index): string => $index % 2 === 1
                ? $part
                : (string) preg_replace('/[\\\\`*_\[\]<>|~&]/', '\\\\$0', $part),
            $parts,
            array_keys($parts),
        ));

        // A line opening with `#`, `>`, `-`, `+`, `=` or `1.` would become a
        // heading, a quote, a list or a setext underline.
        return (string) preg_replace_callback(
            '/^(?:([#>+\-=])|(\d+)([.)]))/',
            static fn (array $marker): string => $marker[1] !== ''
                ? '\\' . $marker[1]
                : $marker[2] . '\\' . $marker[3],
            $escaped,
        );
    }
}
