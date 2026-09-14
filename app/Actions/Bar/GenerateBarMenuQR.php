<?php

declare(strict_types=1);

namespace App\Actions\Bar;

use App\Actions\Tournament\GenerateTournamentQR;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Exception\ValidationException;
use Endroid\QrCode\Writer\PngWriter;

/**
 * Le QR qui met la carte du bar dans la main du client attablé.
 *
 * Il est généré, jamais collé : l'adresse change, le code suit. Une image
 * déposée une fois dans `public/` serait juste devant tout le monde jusqu'au
 * jour où elle ne le serait plus, sans que rien ne le dise.
 *
 * Correction d'erreur haute et bleu du club, comme
 * {@see GenerateTournamentQR} : c'est photographié sur une feuille posée sur
 * une table de bar, de travers, un verre à la main.
 *
 * @throws ValidationException
 */
class GenerateBarMenuQR
{
    /** @return string Une URI `data:image/png;base64,…`, intégrable dans une page imprimée. */
    public function __invoke(string $url, int $size = 420): string
    {
        $result = new Builder(
            writer: new PngWriter,
            data: $url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: $size,
            margin: 0,
            foregroundColor: new Color(30, 64, 175),
        )->build();

        return 'data:image/png;base64,' . base64_encode($result->getString());
    }
}
