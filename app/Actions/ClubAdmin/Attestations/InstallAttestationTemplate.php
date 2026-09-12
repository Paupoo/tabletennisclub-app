<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Attestations;

use App\Domains\ClubAdmin\Subscriptions\Attestations\Models\AttestationTemplate;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Pdf\AnchorResolver;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Pdf\PdfNormaliser;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Pdf\PdfTextExtractor;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Templates\AnchorMaps;
use App\Domains\Shared\Enums\Mutuality;
use Illuminate\Support\Facades\Storage;

/**
 * Takes in an insurer's form and works out whether the club can fill it.
 *
 * Three things happen, in this order and for a reason: the file is rewritten
 * as PDF 1.4 so FPDI can open it at all; the anchors are re-derived against
 * *that* file, so the positions describe what will actually be stamped; and
 * whatever could not be found is recorded on the row. A form that lost a label
 * is not silently half-filled — it stops being offered.
 */
final readonly class InstallAttestationTemplate
{
    public function __construct(
        private PdfNormaliser $normaliser,
        private PdfTextExtractor $extractor,
        private AnchorResolver $resolver,
    ) {}

    public function __invoke(
        Mutuality $mutuality,
        string $sourcePath,
        string $originalName,
        ?int $uploadedByUserId = null,
    ): AttestationTemplate {
        $relative = 'attestation-templates/' . $mutuality->value . '.pdf';
        $disk = Storage::disk('local');
        $disk->makeDirectory('attestation-templates');

        $this->normaliser->toPdf14($sourcePath, $disk->path($relative));

        $layout = $this->extractor->extract($disk->path($relative));

        return AttestationTemplate::updateOrCreate(
            ['mutuality' => $mutuality->value],
            [
                'path' => $relative,
                'original_name' => $originalName,
                'page_count' => $layout->pageCount(),
                'unresolved_fields' => $this->resolver->unresolved($layout, AnchorMaps::for($mutuality)),
                'uploaded_by_user_id' => $uploadedByUserId,
            ],
        );
    }
}
