<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Stores a member document (medical certificate, parental consent) on the
 * private local disk. These files hold health/minor data and must never be
 * web-accessible: they are served through the authenticated download route
 * (admin.user.documents.download) only.
 */
class StoreUserDocumentAction
{
    /** @var array<string, string> Maps document type to its users path column. */
    public const array COLUMNS = [
        'medical' => 'medical_certificate_path',
        'parental_consent' => 'parental_consent_path',
    ];

    /**
     * Removes the stored file for the given document type, wherever it lives:
     * legacy "/storage/..." paths sit on the public disk, current relative
     * paths on the private local disk.
     */
    public static function deleteExisting(User $user, string $type): void
    {
        $path = $user->{self::COLUMNS[$type]};

        if ($path === null) {
            return;
        }

        if (str_starts_with($path, '/storage/')) {
            Storage::disk('public')->delete(substr($path, strlen('/storage/')));
        } else {
            Storage::disk('local')->delete($path);
        }
    }

    public static function handle(User $user, UploadedFile $file, string $type): string
    {
        self::deleteExisting($user, $type);

        $path = $file->storeAs(
            "documents/{$user->id}",
            "{$type}.{$file->getClientOriginalExtension()}",
            'local'
        );

        $user->update([self::COLUMNS[$type] => $path]);

        return $path;
    }
}
