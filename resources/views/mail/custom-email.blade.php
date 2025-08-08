<x-mail::message>
{!! $customMessage !!}

Cordialement,  
**{{ $senderName }}**  
{{ $clubName }}

<x-mail::subcopy>
📞 **Nos coordonnées :**  
📧 Email : {{ config('mail.from.address') }}  
📞 Téléphone : [Votre numéro]  
🌐 Site web : {{ config('app.url') }}

---

Cet email a été envoyé par {{ $senderName }} depuis l'administration de {{ $clubName }}.  
Si vous avez reçu cet email par erreur, vous pouvez l'ignorer.
</x-mail::subcopy>
</x-mail::message>