<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | TabT API Endpoint
    |--------------------------------------------------------------------------
    |
    | The federation's competition database. Versioned rather than read from
    | the environment: it is not deployment configuration.
    |
    | Yes, VTTL. Both wings share one TabT database, and every request names
    | our club by its licence, so this serves the AFTT calendar all the same.
    | In October 2026 api.aftt.be started answering with api.vttl.be's
    | certificate — every call died in the TLS handshake — and its plain-HTTP
    | side now redirects here. resultats.aftt.be/api/ also answers, but still
    | calls 2025-2026 the current season: the hourly sync, which follows the
    | federation's idea of "current", would quietly re-import last year.
    |
    */

    'base_url' => 'https://api.vttl.be/',

];
