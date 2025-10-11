<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use const App\Http\Controllers\Actions\Payments\bancontact_qr;
use const App\Http\Controllers\Actions\Payments\png;

use App\Models\Payment;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;

class GeneratePaymentQR
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    public function __invoke(Payment $payment): string
    {
        $IBAN = 'BE23732333208791';
        $BIC = 'CREGBEBB';
        $amount = $payment->amount_due;
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
