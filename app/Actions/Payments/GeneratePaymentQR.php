<?php

namespace App\Actions\Payments;

use App\Models\Payment;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;

use const App\Http\Controllers\Actions\Payments\bancontact_qr;
use const App\Http\Controllers\Actions\Payments\base64;
use const App\Http\Controllers\Actions\Payments\image;
use const App\Http\Controllers\Actions\Payments\png;

class GeneratePaymentQR
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    /**
     *
     * @param \App\Models\Payment $payment
     * @return string
     */
    public function __invoke(Payment $payment): string
    {
        $IBAN = 'BE23732333208791';
        $BIC = 'CREGBEBB';
        $amount = $payment->amount_due;
        $currency = 'EUR';
        $beneficiary = 'CTT Ottignies-Blocry ASBL';
        $reference = $payment->reference    ; // votre référence / communication
        
        $qrText = "BCD\n001\n1\nSCT\n$\App\Http\Controllers\Actions\Payments\BIC\n$\App\Http\Controllers\Actions\Payments\beneficiary\n$\App\Http\Controllers\Actions\Payments\IBAN\n$currency$\App\Http\Controllers\Actions\Payments\amount\nCHAR\n\n$reference";

        $builder = new Builder(
            writer: new PngWriter(),
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
