<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The one size limit for a document a member attaches: a receipt, a medical
 * certificate, a closing photo, an insurer's form.
 *
 * PHP refuses a file above `upload_max_filesize` before Laravel sees it, with
 * nothing better to say than "the upload failed". Every rule and every hint
 * reads this value, and the server is set a little above it (see
 * docs/DEPLOYMENT.md), so the limit a member reads is the one that applies.
 * Photos are shrunk in the browser by <x-document-upload> long before that.
 */
final class UploadLimits
{
    /** In kilobytes, the unit of Laravel's `max` rule on a file. */
    public const int DOCUMENT_KILOBYTES = 5120;

    /** The limit as a member reads it: "5 MB", "5 Mo". */
    public static function documentLabel(): string
    {
        return __(':count MB', ['count' => intdiv(self::DOCUMENT_KILOBYTES, 1024)]);
    }

    /** The `max` validation rule for a document. */
    public static function documentRule(): string
    {
        return 'max:' . self::DOCUMENT_KILOBYTES;
    }
}
