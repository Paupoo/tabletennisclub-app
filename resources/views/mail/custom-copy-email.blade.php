{{-- resources/views/emails/custom-copy.blade.php --}}
<x-mail::message>
# 📋 COPIE ADMINISTRATEUR

Ceci est une copie de l'email envoyé au contact.

---

## 📧 Email envoyé à :

**{{ $contact->first_name }} {{ $contact->last_name }}**  
📧 {{ $contact->email }}  
@if($contact->phone)
📞 {{ $contact->phone }}  
@endif
📅 Contact créé le {{ $contact->created_at->format('d/m/Y à H:i') }}

---

## 📝 Sujet : {{ $subject }}

### Message envoyé :

{!! $customMessage !!}

---

## 📊 Informations d'envoi

- **Expéditeur :** {{ $senderName }}
- **Date d'envoi :** {{ now()->format('d/m/Y à H:i:s') }}
- **Contact ID :** #{{ $contact->id }}
- **Statut contact :** {{ ucfirst(str_replace('_', ' ', $contact->status)) }}
- **Centre d'intérêt :** {{ $contact->interest?->getLabel() ?? 'Non spécifié' }}

@if($contact->message)
## 💬 Message original du contact

> *"{{ $contact->message }}"*
@endif

<x-mail::button :url="route('admin.contacts.show', $contact)" color="primary">
👁️ Voir le contact complet
</x-mail::button>

<x-mail::subcopy>
Email automatiquement généré par le système d'administration de {{ $clubName }}.  
Cette copie est destinée uniquement aux administrateurs.
</x-mail::subcopy>
</x-mail::message>