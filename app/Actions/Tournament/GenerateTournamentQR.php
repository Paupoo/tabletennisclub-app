<?php

declare(strict_types=1);

namespace App\Actions\Tournament;

use App\Actions\ClubAdmin\Payments\GeneratePaymentQR;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Exception\ValidationException;
use Endroid\QrCode\Writer\PngWriter;

/**
 * The QR code that puts the live tournament page in somebody's hand.
 *
 * Printed on the sheet that goes on the wall: the club has no way of telling a
 * player standing in the room that the page exists, and telling them to type a
 * URL off a poster is the same as not telling them.
 *
 * High error correction on purpose. This is photographed off a sheet of paper
 * taped to a wall, in a sports hall, at an angle, by somebody holding a bat —
 * a code that tolerates a third of itself being unreadable is worth the extra
 * density. Same writer and same encoding as {@see GeneratePaymentQR}.
 */
class GenerateTournamentQR
{
    /**
     * @return string A `data:image/png;base64,…` URI, embeddable in a printed page.
     *
     * @throws ValidationException
     */
    public function __invoke(string $url, int $size = 320): string
    {
        $result = new Builder(
            writer: new PngWriter,
            data: $url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: $size,
            margin: 8,
        )->build();

        return 'data:image/png;base64,' . base64_encode($result->getString());
    }
}
