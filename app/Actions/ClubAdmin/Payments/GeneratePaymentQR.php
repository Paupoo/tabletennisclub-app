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
        return $this->render($this->qrText($payment));
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

    /**
     * A transfer to someone other than the club, as PNG bytes.
     *
     * @throws ValidationException
     */
    public function transferPng(string $beneficiary, string $iban, float $amount, string $communication): string
    {
        return $this->render($this->transferText($beneficiary, $iban, $amount, $communication));
    }

    /**
     * The EPC069-12 payload of a transfer to a third party — a provincial
     * committee, say — in version 002, which lets the BIC go unstated: the
     * committee publishes none, and every bank in the SEPA zone resolves it
     * from the IBAN.
     */
    public function transferText(string $beneficiary, string $iban, float $amount, string $communication): string
    {
        return sprintf(
            "BCD\n002\n1\nSCT\n\n%s\n%s\nEUR%s\n\n\n%s",
            mb_substr($beneficiary, 0, 70),
            str_replace(' ', '', $iban),
            number_format(max(0.0, $amount), 2, '.', ''),
            mb_substr($communication, 0, 140),
        );
    }

    /**
     * @throws ValidationException
     */
    private function render(string $data): string
    {
        $builder = new Builder(
            writer: new PngWriter,
            data: $data,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 300,
            margin: 10
        );

        return $builder->build()->getString();
    }
}
