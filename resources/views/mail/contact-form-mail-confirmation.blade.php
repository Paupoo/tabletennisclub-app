<x-mail::message>
# {{ config('app.name') }}
*Votre club de sport*

---

## {{ __('welcome-email.title', ['name' => $contact->first_name]) }}

{{ __('welcome-email.paragraph1') }}

{{ __('welcome-email.paragraph2') }}
---

### 📋 Votre demande en bref :

- **Centre d'intérêt :** {{ $contact->interest->getLabel() ?: 'Non spécifié' }}
- **Date de demande :** {{ $contact->created_at->format('d/m/Y') }}
@if($contact->phone)
- **Téléphone :** {{ $contact->phone }}
@endif

---

### Pourquoi choisir notre club ?

- 🏆 Une équipe d'entraîneurs qualifiés et passionnés
- 🤝 Un environnement convivial et familial
- 📈 Des programmes adaptés à tous les niveaux
- 🏅 Des compétitions régulières pour progresser
- 🎯 Des installations modernes et bien équipées

@if($contact->message)
---

### Votre message :

> *"{{ $contact->message }}"*

@endif

---

### Nous contacter :

N'hésitez pas à nous contacter si vous avez des questions. Nous sommes là pour vous accompagner dans votre projet sportif !
Une erreur, une précision à ajouter ? Pas problème, répondez simplement à cet email pour préciser votre demande.

---

### 📞 Nos coordonnées :

- **📧 Email :** {{ config('mail.from.address') }}
- **📞 Téléphone :** {{ config('app.club_phone_number') }} - (lu.-ven. 16h-20h).
- **📍 Adresse :** {{ config('app.club_street') . ', ' . config('app.club_zip_code') . ' ' . config('app.club_city') }}
- **🌐 Site web :** {{ config('app.url') }}

---

Sportivement,
**L'équipe de {{ config('app.name') }}**

---

*Cet email a été envoyé automatiquement suite à votre demande de contact.*
*Si vous n'êtes pas à l'origine de cette demande, vous pouvez ignorer ce message.*
</x-mail::message>
