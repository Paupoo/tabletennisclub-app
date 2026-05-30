<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

enum PoolGenerationTypeEnum: int
{
    case MATCHES_PER_PLAYER = 1;
    case ONE_POOL_PER_TABLE = 2;
    case POOL_NUMBER = 3;
}
