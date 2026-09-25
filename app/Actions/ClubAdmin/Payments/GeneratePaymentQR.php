<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Payments;

use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\Competitions\Interclub\Models\Club;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Exception\ValidationException;
use Endroid\QrCode\Writer\PngWriter;

class GeneratePaymentQR
{
    /**
     * The QR as a `data:` URI, for a browser.
     *
     * Not for an email: Gmail strips `data:` sources from `<img>`, so a message
     * built this way shows its alt text and nothing else — which is exactly what
     * it did in production while Mailpit rendered it fine in development. Mail
     * views embed {@see self::png()} instead.
     *
     * @throws ValidationException
     */
    public function __invoke(Payment $payment): string
    {
        return 'data:image/png;base64,' . base64_encode($this->png($payment));
    }

    /**
     * The QR as raw PNG bytes, to be embedded in a message.
     *
     * @throws ValidationException
     */
    public function png(Payment $payment): string
    {
        $builder = new Builder(
            writer: new PngWriter,
            data: $this->qrText($payment),
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 300,
            margin: 10
        );

        $result = $builder->build();

        return $result->getString();
    }

    /**
     * Le contenu encodé dans le QR, au format EPC069-12.
     *
     * Public, et c'est délibéré : c'est la charge utile qu'on veut pouvoir
     * vérifier. Décoder un PNG pour savoir quel montant un membre verra dans
     * son application bancaire est un détour que personne ne prendra.
     */
    public function qrText(Payment $payment): string
    {
        $club = Club::ourClub()->first();

        // Le solde, pas ce qui a été réclamé au départ. Tant que « payé en
        // partie » n'existait pas, les deux se confondaient ; depuis, un membre
        // ayant versé 200 € sur 365 € et scannant sa relance se verrait
        // proposer un virement de 365 € — il aurait payé 565 € en tout.
        $balance = max(0.0, round((float) $payment->amount_due - (float) $payment->amount_paid, 2));

        return sprintf(
            "BCD\n001\n1\nSCT\n%s\n%s\n%s\nEUR%s\nCHAR\n\n%s",
            $club->bic,
            'CTT Ottignies-Blocry ASBL',
            $club->bank_account,
            number_format($balance, 2, '.', ''),
            $payment->reference,
        );
    }
}
