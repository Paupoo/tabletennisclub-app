<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\ClubAdmin\Subscriptions\Attestations\Models\MutualAttestation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes attestation files once the club has no reason left to hold them.
 *
 * The PDF carries the member's national register number — the one identifier
 * this application deliberately never stores in a column — so keeping it for
 * ever would put back through the storage door what the form was careful to
 * keep out.
 *
 * Twelve months covers the whole season plus a wide margin for an insurer that
 * loses the envelope. The row itself is never touched: the verification page
 * has to keep answering, and the club keeps the trace of what it certified.
 */
class PurgeAttestationFilesCommand extends Command
{
    protected $description = 'Delete attestation PDFs older than a year, keeping their record';

    protected $signature = 'attestations:purge {--months=12 : How long a file is kept}';

    public function handle(): int
    {
        $months = max(1, (int) $this->option('months'));
        $cutoff = now()->subMonths($months);
        $purged = 0;

        MutualAttestation::query()
            ->whereNotNull('path')
            ->where('issued_at', '<', $cutoff)
            ->orderBy('id')
            ->each(function (MutualAttestation $attestation) use (&$purged): void {
                Storage::disk('local')->delete((string) $attestation->path);

                $attestation->update(['path' => null, 'purged_at' => now()]);
                $purged++;
            });

        $this->components->info(trans_choice(
            '{0}No attestation file to purge.|[1,*]:count attestation file(s) purged.',
            $purged,
            ['count' => $purged],
        ));

        return self::SUCCESS;
    }
}
