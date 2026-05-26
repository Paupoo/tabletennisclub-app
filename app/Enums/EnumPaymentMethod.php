<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethod: string
{
    case CASH = 'Cash';
    case WIRE = 'Wire';
    case QRCODE = 'QRCode'
    case OFFERED = 'Offered';
}
