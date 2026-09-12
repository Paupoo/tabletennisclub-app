<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\ClubAdmin\Attestations\InstallAttestationTemplate;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Models\AttestationSetting;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Pdf\PdfNormaliser;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Domains\Shared\Enums\Mutuality;
use Illuminate\Database\Seeder;

/**
 * Puts the attestations domain in a usable state for a demo install.
 *
 * Runs from {@see DatabaseSeeder} only, which is the development seeder: the
 * seal and the signature it installs are watermarked SPECIMEN — NE PAS
 * UTILISER, and they must never reach a production seed path, where they would
 * become a real club seal on a real document.
 *
 * The five published forms are installed the same way the admin screen does it,
 * so a developer sees exactly what the secretary will see, unresolved labels
 * included.
 */
class AttestationDemoSeeder extends Seeder
{
    public function run(): void
    {
        $specimens = database_path('seeders/Data/attestation-specimens');

        AttestationSetting::current()->update([
            'signatory_user_id' => User::where('committee_role', CommitteeRolesEnum::SECRETARY->value)->value('id')
                ?? User::query()->value('id'),
            'seal_path' => $specimens . '/specimen-seal.png',
            'signature_path' => $specimens . '/specimen-signature.png',
        ]);

        // Without Ghostscript the two forms published in PDF 1.7 cannot be
        // converted, and a demo install is no place to fail over it.
        if (! app(PdfNormaliser::class)->isAvailable()) {
            $this->command?->warn('Ghostscript introuvable : les formulaires mutuelle ne sont pas installés.');

            return;
        }

        $install = app(InstallAttestationTemplate::class);

        foreach (Mutuality::withOfficialForm() as $mutuality) {
            $source = database_path('seeders/Data/attestation-templates/' . $mutuality->value . '.pdf');

            if (! is_file($source)) {
                continue;
            }

            $template = $install($mutuality, $source, basename($source));

            if (! $template->isUsable()) {
                $this->command?->warn(sprintf(
                    '%s : étiquettes introuvables — %s',
                    $mutuality->label(),
                    implode(', ', $template->unresolved_fields),
                ));
            }
        }
    }
}
