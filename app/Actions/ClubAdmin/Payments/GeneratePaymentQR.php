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
        $BIC = Club::ourClub()->first()->bic;
        $IBAN = Club::ourClub()->first()->bank_account;
        $amount = number_format((float) $payment->amount_due, 2, '.', '');
        $currency = 'EUR';
        $beneficiary = 'CTT Ottignies-Blocry ASBL';
        $reference = $payment->reference; // votre référence / communication

        $qrText = "BCD\n001\n1\nSCT\n{$BIC}\n{$beneficiary}\n{$IBAN}\n{$currency}{$amount}\nCHAR\n\n{$reference}";

        $builder = new Builder(
            writer: new PngWriter,
            data: $qrText,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 300,
            margin: 10
        );

        $result = $builder->build();

        return $result->getString();
    }
}
