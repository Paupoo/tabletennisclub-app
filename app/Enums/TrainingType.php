<?php

namespace App\Enums;

enum TrainingType: string
{
    case FREE = 'Free';
    case DIRECTED = 'Directed';
    case SUPERVISED = 'Supervised';
}