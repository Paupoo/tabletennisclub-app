<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * Free text typed in a textarea, placed in a notification e-mail.
 *
 * `MailMessage::line()` joins every line of a plain string with a space, so a
 * cancellation reason written on three lines reached the member as one block.
 * An `Htmlable` line is left untouched: the text is escaped here, and its line
 * breaks become the only HTML it carries.
 */
final class MailText
{
    /**
     * Escape member-typed text and keep its line breaks for a mail line.
     */
    public static function keepingLineBreaks(string $text): HtmlString
    {
        return new HtmlString(nl2br(e($text), false));
    }
}
