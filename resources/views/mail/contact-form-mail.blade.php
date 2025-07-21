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
- {{ $membership_family_members }} licence récréative
- {{ $membership_competitors }} licence compétitive
- {{ $membership_training_sessions }} séance d'entraînement dirigé
- Estimation affichée du futur membre : {{ $membership_total_cost }} €

@endif
                
                {{-- 'membership_family_members' => $this->formData['membership_family_members'] ?? '',
                'membership_competitors' => $this->formData['membership_competitors'] ?? '',
                'membership_training_sessions' => $this->formData['membership_training_sessions'] ?? '',
                'membership_total_cost' => $this->formData['membership_total_cost'] ?? '', --}}
{{-- <x-mail::button :url="''">
Button Text
</x-mail::button> --}}

Envoyé le {{ now()->format('d-m-Y H:i') }},<br>
{{ config('app.name') }}
</x-mail::message>
