<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Feature flags
|--------------------------------------------------------------------------
|
| One flag per functional domain, so an immature domain can stay in the code
| yet be invisible in production while remaining fully usable in dev.
|
| Every flag defaults to true: an install that sets nothing keeps the whole
| application, exactly as before these flags existed. Switching a domain off is
| an explicit decision, taken in that environment's .env.
|
| The keys mirror App\Domains\Shared\Enums\Feature — a flag added here without
| its enum case is unreachable, and FeatureFlagTest fails on the mismatch.
|
*/

return [
    'bar' => env('FEATURE_BAR', true),
    'cash_register' => env('FEATURE_CASH_REGISTER', true),
    'contacts' => env('FEATURE_CONTACTS', true),
    'help_centre' => env('FEATURE_HELP_CENTRE', true),
    'interclubs' => env('FEATURE_INTERCLUBS', true),
    'meetings' => env('FEATURE_MEETINGS', true),
    'supervision' => env('FEATURE_SUPERVISION', true),
    'tournaments' => env('FEATURE_TOURNAMENTS', true),
    'training_planning' => env('FEATURE_TRAINING_PLANNING', true),
    'trainings' => env('FEATURE_TRAININGS', true),
    'treasury' => env('FEATURE_TREASURY', true),
    'website' => env('FEATURE_WEBSITE', true),
];
