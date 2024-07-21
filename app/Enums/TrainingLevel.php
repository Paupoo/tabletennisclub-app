<?php

namespace App\Enums;

enum TrainingLevel: string
{
    case BEGINNERS = 'Beginners';
    case KIDS = 'Kids';
    case INTERMEDIATE = 'Intermediate';
    case YOUNG_POTENTIAL = 'Young potential';
    case ELITE = 'Elite';
}