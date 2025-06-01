<?php

namespace App\Enums;

enum PoolGenerationTypeEnum: int
{
    case POOL_NUMBER = 1;
    case MATCHES_PER_PLAYER = 2;
    case ONE_POOL_PER_TABLE = 3;
}

