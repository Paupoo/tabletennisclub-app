<x-mail::message>
Vous avez reçu un message depuis le formulaire de contact du site {{ config('app.name') }}

# Message de :

- Prénom : {{ $first_name }}
- Nom : {{ $last_name }}
- Email : {{ $email }}
- Téléphone : {{ $phone }}
- Au sujet de : {{ $interest->getLabel() }}
 

# Message : 

<x-mail::panel>{{  $message }}</x-mail::panel>

@if($interest == \App\Enums\ContactReasonEnum::join)

# Récapitulatif de la demande :

<x-mail::table>
| Demande                                                                                                                   | Total                                 |
| -------------                                                                                                             | ------------:                         |
| Licence{{ $membership_family_members > 1 ? 's' : '' }} récréative{{ $membership_family_members > 1 ? 's' : '' }}          | {{ $membership_family_members }}      |
| Licence{{ $membership_competitors > 1 ? 's' : '' }} compétitive{{ $membership_competitors > 1 ? 's' : '' }}               | {{ $membership_competitors }}         |
| Entraînement{{ $membership_training_sessions > 1 ? 's' : '' }} dirigé{{ $membership_training_sessions > 1 ? 's' : '' }}   | {{ $membership_training_sessions }}   |
</x-mail::table>
@endif

<x-mail::button url="{{ route('admin.contacts.index') }}">
Gérer la demande sur le site
</x-mail::button>

Envoyé le {{ now()->format('d-m-Y H:i') }}<br>
</x-mail::message>
