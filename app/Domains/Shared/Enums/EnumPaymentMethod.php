<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

enum PaymentMethod: string
{
    case CASH = 'Cash';
    case OFFERED = 'Offered';
    case QRCODE = 'QRCode';
    case WIRE = 'Wire';
}
