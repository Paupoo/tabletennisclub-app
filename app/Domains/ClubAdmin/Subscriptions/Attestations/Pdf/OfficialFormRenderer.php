<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Subscriptions\Attestations\Pdf;

use App\Domains\ClubAdmin\Subscriptions\Attestations\Models\AttestationSetting;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Models\AttestationTemplate;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Templates\AnchorMaps;
use App\Domains\Shared\Enums\Mutuality;
use Mpdf\Mpdf;
use RuntimeException;

/**
 * Lays the club's answers over an insurer's own form.
 *
 * The template is imported page by page and the values are drawn on top. The
 * positions come from {@see AnchorResolver}, read from the very file being
 * stamped — so whatever Ghostscript did to the page on the way in, the text
 * lands where the labels actually are.
 *
 * A value whose label cannot be found is skipped rather than dropped in a
 * corner, and the template that carries it is already barred from use by
 * {@see AttestationTemplate::isUsable()}.
 */
final readonly class OfficialFormRenderer
{
    private const string FONT = 'dejavusans';

    private const float FONT_SIZE = 9.0;

    public function __construct(
        private PdfTextExtractor $extractor,
        private AnchorResolver $resolver,
    ) {}

    /**
     * @param  array<string, string>  $values
     * @return string The finished PDF.
     */
    public function render(string $templatePath, Mutuality $mutuality, array $values, AttestationSetting $settings): string
    {
        if (! is_file($templatePath)) {
            throw new RuntimeException("Missing attestation template: {$templatePath}");
        }

        $layout = $this->extractor->extract($templatePath);
        $anchors = AnchorMaps::for($mutuality);
        $size = $layout->pageSize();

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => [$size['width'], $size['height']],
            'margin_left' => 0,
            'margin_right' => 0,
            'margin_top' => 0,
            'margin_bottom' => 0,
            'tempDir' => storage_path('app/mpdf'),
        ]);

        $pageCount = $mpdf->setSourceFile($templatePath);

        for ($page = 1; $page <= $pageCount; $page++) {
            $pageSize = $layout->pageSize($page);
            $imported = $mpdf->importPage($page);

            $mpdf->AddPage('', '', '', '', '', 0, 0, 0, 0, 0, 0);
            $mpdf->useImportedPage($imported, 0, 0, $pageSize['width'], $pageSize['height']);

            $this->drawPage($mpdf, $layout, $anchors, $values, $settings, $page);
        }

        return (string) $mpdf->Output('', 'S');
    }

    /**
     * @param  array<string, Anchor>  $anchors
     * @param  array<string, string>  $values
     */
    private function drawPage(
        Mpdf $mpdf,
        PdfTextLayout $layout,
        array $anchors,
        array $values,
        AttestationSetting $settings,
        int $page,
    ): void {
        $mpdf->SetFont(self::FONT, '', self::FONT_SIZE);
        $mpdf->SetTextColor(0, 0, 0);

        foreach ($anchors as $field => $anchor) {
            if ($anchor->page !== $page) {
                continue;
            }

            $point = $this->resolver->resolve($layout, $anchor);

            if (! $point instanceof Point) {
                continue;
            }

            match ($field) {
                'seal' => $this->stamp($mpdf, $settings->seal_path, $settings->seal_width_mm * $anchor->scale, $point),
                'signature' => $this->stamp($mpdf, $settings->signature_path, $settings->signature_width_mm * $anchor->scale, $point),
                default => $this->write($mpdf, $values[$field] ?? '', $point),
            };
        }
    }

    private function stamp(Mpdf $mpdf, ?string $path, float $widthMm, Point $point): void
    {
        if ($path === null || ! is_file($path)) {
            return;
        }

        $mpdf->Image($path, $point->x, $point->y, $widthMm, 0);
    }

    private function write(Mpdf $mpdf, string $text, Point $point): void
    {
        if (trim($text) === '') {
            return;
        }

        // mPDF embeds a subset of the font, built from the characters seen by
        // Write()/Cell(). Text() is the only call that positions on a baseline,
        // which is what an overlay needs — and the only one that never registers
        // what it draws. Every accent therefore came out as an empty box:
        // « Aurélien » printed as « Aur□lien » on a document the club signs.
        //
        // Declaring the characters first is what puts them in the subset.
        $mpdf->UTF8StringToArray($text, true);

        $mpdf->Text($point->x, $point->y, $text);
    }
}
