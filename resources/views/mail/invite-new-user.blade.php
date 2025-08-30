<x-mail::message>
Bonjour {{ $user->first_name . ' ' . $user->last_name }},

Nous sommes ravis de vous accueillir au **{{ config('app.name') }}** !

Votre login est {{ $user->email }}.

Pour terminer la création de votre compte et renseigner vos informations personnelles, merci de cliquer sur le bouton ci-dessous :

<x-mail::button :url="$link" :color="'primary'">
Finaliser mon inscription
</x-mail::button>

Une fois cette étape complétée, vous pourrez :

- Finaliser votre adhésion au club
- Choisir vos sessions d’entraînement
- Participer aux événements organisés
- Gérer facilement votre compte en ligne

À très bientôt à la salle !

Sportivement,  
**Le comité du {{ config('app.name') }}**

<small>Cet email vous est adressé automatiquement, merci de ne pas y répondre.</small>
</x-mail::message>
