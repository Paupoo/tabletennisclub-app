<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Subscriptions\Attestations\Pdf;

use App\Data\Attestation\AttestationData;
use App\Data\Attestation\MemberIdentifiers;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Models\AttestationSetting;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\View;
use Mpdf\Mpdf;

/**
 * The club's own certificate, for the insurers that accept one.
 *
 * MC and Mutualité Neutre both say in writing that a club-written attestation
 * is enough, and it is the only answer the club has for an insurer it holds no
 * form for. Written from a Blade view rather than laid over a template: there
 * is nothing to lay it over.
 */
final readonly class ClubAttestationRenderer
{
    public function render(
        AttestationData $data,
        MemberIdentifiers $identifiers,
        AttestationSetting $settings,
        string $reference,
        string $verificationUrl,
    ): string {
        $html = View::make('attestations.club-attestation', [
            'data' => $data,
            'identifiers' => $identifiers,
            'reference' => $reference,
            'verificationUrl' => $verificationUrl,
            'sealSrc' => $this->embed($settings->seal_path),
            'sealWidth' => $settings->seal_width_mm,
            'signatureSrc' => $this->embed($settings->signature_path),
            'signatureWidth' => $settings->signature_width_mm,
            'qrSrc' => $this->qr($verificationUrl),
        ])->render();

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 18,
            'margin_right' => 18,
            'margin_top' => 18,
            'margin_bottom' => 18,
            'tempDir' => storage_path('app/mpdf'),
        ]);

        $mpdf->SetTitle(__('Attestation of sporting affiliation'));
        $mpdf->WriteHTML($html);

        return (string) $mpdf->Output('', 'S');
    }

    /**
     * mPDF reads `data:` sources happily, and inlining keeps the renderer free
     * of any assumption about where the images are served from.
     */
    private function embed(?string $path): ?string
    {
        if ($path === null || ! is_file($path)) {
            return null;
        }

        return 'data:image/png;base64,' . base64_encode((string) file_get_contents($path));
    }

    private function qr(string $url): string
    {
        $result = (new Builder(
            writer: new PngWriter,
            data: $url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 300,
            margin: 4,
        ))->build();

        return 'data:image/png;base64,' . base64_encode($result->getString());
    }
}
