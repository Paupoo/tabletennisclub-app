<?php

declare(strict_types=1);

namespace App\Http\Controllers\Attestations;

use App\Domains\ClubAdmin\Subscriptions\Attestations\Models\MutualAttestation;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Hands a member their own certificate back.
 *
 * The file lives outside the web root — it carries a national register number
 * — so it is streamed through here, after the policy has spoken. A guardian
 * acting under proxy is authenticated as the ward and is served exactly as the
 * ward would be.
 */
class AttestationDownloadController extends Controller
{
    public function download(MutualAttestation $attestation): StreamedResponse
    {
        $this->authorize('download', $attestation);

        abort_if($attestation->path === null, 404);
        abort_unless(Storage::disk('local')->exists($attestation->path), 404);

        return Storage::disk('local')->download(
            $attestation->path,
            $attestation->reference . '.pdf',
        );
    }
}
