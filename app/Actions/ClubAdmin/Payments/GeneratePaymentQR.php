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
     * Create a new class instance.
     */
    public function __construct() {}

    /**
     * @throws ValidationException
     */
    public function __invoke(Payment $payment): string
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

        // $result->saveToFile(path: __DIR__ . '/bancontact_qr.png');
        return 'data:image/png;base64,' . base64_encode($result->getString());
    }
}
