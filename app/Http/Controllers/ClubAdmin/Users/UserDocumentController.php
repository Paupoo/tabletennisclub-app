<?php

declare(strict_types=1);

namespace App\Http\Controllers\ClubAdmin\Users;

use App\Actions\User\StoreUserDocumentAction;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserDocumentController extends Controller
{
    /**
     * Serves a member document (medical certificate, parental consent) from
     * the private local disk. Restricted to the member themselves, admins,
     * committee members (they verify certificates) and the member's guardians.
     */
    public function download(Request $request, User $user, string $type): StreamedResponse
    {
        abort_unless(array_key_exists($type, StoreUserDocumentAction::COLUMNS), 404);

        $actor = $request->user();

        abort_unless(
            $actor->is($user)
                || $actor->is_admin
                || $actor->is_committee_member
                || $user->guardians()->where('guardians.user_id', $actor->id)->exists(),
            403
        );

        $path = $user->{StoreUserDocumentAction::COLUMNS[$type]};

        abort_unless($path !== null && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }
}
