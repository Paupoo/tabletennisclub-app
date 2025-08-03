<x-mail::message>
# Vous avez reçu un message depuis le formulaire de contact du site {{ config('app.name') }}

Message de :

- Prénom : {{ $first_name }}
- Nom : {{ $last_name }}
- Email : {{ $email }}
- Téléphone : {{ $phone }}
- Au sujet de : {{ $interest }}
- Message : {{  $message }}

@if($interest === 'join')
Demande pour 
- {{ $membership_family_members }} licence{{ $membership_family_members > 1 ? 's' : '' }} récréative{{ $membership_family_members > 1 ? 's' : '' }}
- {{ $membership_competitors }} licence{{ $membership_competitors > 1 ? 's' : '' }} compétitive{{ $membership_competitors > 1 ? 's' : '' }}
- {{ $membership_training_sessions }} séance{{ $membership_training_sessions > 1 ? 's' : '' }} d'entraînement dirigé
- Estimation affichée du futur membre : {{ $membership_total_cost }} €

@endif
                
                {{-- 'membership_family_members' => $this->formData['membership_family_members'] ?? '',
                'membership_competitors' => $this->formData['membership_competitors'] ?? '',
                'membership_training_sessions' => $this->formData['membership_training_sessions'] ?? '',
                'membership_total_cost' => $this->formData['membership_total_cost'] ?? '', --}}
<x-mail::button :url="'https://www.google.com'">
Do something
</x-mail::button>
<x-mail::button :url="''" color="success">
Do something else
</x-mail::button>
<x-mail::button :url="''" color="error">
Do something else dangerous
</x-mail::button>

<x-mail::panel>
This is the panel content as a test.
</x-mail::panel>

<x-mail::table>
| Licence(s) récréatives       | Licence(s) sportive(s)         | Entrainement(s) dirigé(s)       |

| ------------- | :-----------: | ------------: |

| {{ $membership_family_members }}      | {{ $membership_competitors }}       | {{ $membership_training_sessions }}           |
</x-mail::table>

Envoyé le {{ now()->format('d-m-Y H:i') }},<br>
{{ config('app.name') }}
</x-mail::message>
