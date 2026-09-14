<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Club Name
    |--------------------------------------------------------------------------
    |
    | The club name as the site displays it: navigation wordmark, page titles,
    | footer, maintenance page and sign-in layout. It is deliberately versioned
    | and NOT read from the environment — APP_NAME is deployment configuration
    | (logs, queues, mail) and a typo there must never rename the club on the
    | public site.
    |
    */

    'name' => 'CTT Ottignies-Blocry',

    /*
    |--------------------------------------------------------------------------
    | Sponsors
    |--------------------------------------------------------------------------
    |
    | Ceux qui soutiennent le club, et où mène leur logo. La liste vivait en dur
    | dans HomeController ; la carte du bar les affiche aussi, sur l'écran comme
    | sur le téléphone. Deux listes auraient divergé au premier sponsor ajouté,
    | et on l'aurait appris par le sponsor.
    |
    | Le chemin du logo est relatif à `public/` : il traverse `asset()` à
    | l'affichage, jamais ici — une URL absolue figée dans la configuration
    | survivrait à un changement de domaine.
    |
    */

    'sponsors' => [
        [
            'name' => 'La maison de Malou',
            'logo' => 'images/sponsors/sponsor_1_v2.jpg',
            'url' => 'https://www.lamaisondemalou.be/',
        ],
        [
            'name' => 'Chatisfait',
            'logo' => 'images/sponsors/sponsor_2_v2.png',
            'url' => 'https://www.chatisfait.be/',
        ],
    ],

];
