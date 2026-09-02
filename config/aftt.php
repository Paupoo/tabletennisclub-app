<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | TabT API Endpoint
    |--------------------------------------------------------------------------
    |
    | The federation's competition database. AFTT and VTTL both run TabT behind
    | their own host, so a club on the other wing changes this one line rather
    | than the importer. Versioned rather than read from the environment: it is
    | not deployment configuration, and a wrong value here would silently import
    | another federation's calendar.
    |
    */

    'base_url' => 'https://api.aftt.be/',

];
