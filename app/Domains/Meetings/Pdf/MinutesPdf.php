<?php

declare(strict_types=1);

namespace App\Domains\Meetings\Pdf;

use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Meetings\Services\MinutesReport;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Mpdf\Mpdf;

/**
 * Published minutes as an A4 document: the one attached to the minutes mail
 * and the one downloaded from the reading page.
 *
 * It is rendered on demand and never stored: its footer says when, and the
 * reading page stays the version of record.
 */
final class MinutesPdf
{
    public function render(MinutesReport $report): string
    {
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 16,
            'margin_right' => 16,
            'margin_top' => 16,
            'margin_bottom' => 18,
            'margin_footer' => 8,
            'tempDir' => storage_path('app/mpdf'),
        ]);

        $mpdf->SetTitle(__('Minutes') . ' — ' . $report->meeting->title);
        $mpdf->SetHTMLFooter(View::make('meetings.minutes-pdf-footer', ['report' => $report])->render());
        $mpdf->WriteHTML($this->withLocalImages(View::make('meetings.minutes-pdf', [
            'report' => $report,
            'club' => Club::own(),
            'logo' => is_file(public_path('images/logo-club-email.png')) ? public_path('images/logo-club-email.png') : null,
        ])->render()));

        return (string) $mpdf->Output('', 'S');
    }

    /**
     * Point the body's images at the files on disk, and drop the others.
     *
     * The logo, a file under public/ put there by the template, stays.
     *
     * An image inserted in the editor is stored as `/storage/…`, a path mPDF
     * cannot open. Any other source would make the server fetch whatever
     * address a note taker pasted: it is replaced by its description instead.
     */
    private function withLocalImages(string $html): string
    {
        return (string) preg_replace_callback(
            '#<img\b[^>]*\bsrc="([^"]*)"[^>]*>#i',
            function (array $image): string {
                // The club's logo, placed by the template itself from public/.
                if (str_starts_with($image[1], public_path() . DIRECTORY_SEPARATOR) && is_file($image[1])) {
                    return $image[0];
                }

                $path = parse_url(html_entity_decode($image[1]), PHP_URL_PATH) ?: '';
                $local = str_starts_with($path, '/storage/') && ! str_contains($path, '..')
                    ? Storage::disk('public')->path(substr($path, strlen('/storage/')))
                    : null;

                if ($local !== null && is_file($local)) {
                    return str_replace($image[1], $local, $image[0]);
                }

                preg_match('#\balt="([^"]*)"#i', $image[0], $alt);

                return '<em>[' . ($alt[1] ?? '') . ']</em>';
            },
            $html,
        );
    }
}
