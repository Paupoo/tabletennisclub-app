<x-mail::message>
Bonjour {{ $user->first_name }} !

Bienvenue au **{{ config('app.name') }}** — votre compte est maintenant actif.

Vous pouvez dès à présent accéder à votre espace membre pour :

- Finaliser votre adhésion au club
- Choisir vos sessions d'entraînement
- Consulter les tournois et événements
- Gérer vos informations personnelles

<x-mail::button :url="$dashboardUrl" color="primary">
Accéder à mon espace
</x-mail::button>

À très bientôt à la salle !

Sportivement,
**Le comité du {{ config('app.name') }}**

<small>{{ __('This email was sent automatically, please do not reply.') }}</small>
</x-mail::message>
