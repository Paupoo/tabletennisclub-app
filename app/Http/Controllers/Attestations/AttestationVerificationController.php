<?php

declare(strict_types=1);

namespace App\Http\Controllers\Attestations;

use App\Domains\ClubAdmin\Subscriptions\Attestations\Models\MutualAttestation;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Confirms, to whoever holds the document, that the club issued it.
 *
 * Public, because the person checking works at a mutual insurer's desk and has
 * no account here. That gives away nothing: the address is an unguessable token
 * printed on the document itself, so anyone who can reach this page is already
 * holding every fact it repeats.
 *
 * What it adds is the one fact the paper cannot carry — whether the certificate
 * still stands. A reissued attestation leaves the old one revoked, and this is
 * where that shows.
 */
class AttestationVerificationController extends Controller
{
    public function show(string $token): View
    {
        $attestation = MutualAttestation::with(['user', 'season'])
            ->where('token', $token)
            ->firstOrFail();

        return view('attestations.verify', ['attestation' => $attestation]);
    }
}
