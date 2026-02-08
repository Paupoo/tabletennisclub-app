<x-mail::message>
# {{ config('app.name') }}
*Votre club de sport*

---

## Bonjour {{ $contact->first_name }} !

Nous avons bien reçu votre demande de contact et nous vous remercions de l'intérêt que vous portez à notre club.

Notre équipe va examiner votre demande dans les plus brefs délais. En attendant, nous souhaitions vous faire découvrir notre unive
rs et vous donner quelques informations utiles.

---

### 📋 Votre demande en bref :

- **Centre d'intérêt :** {{ $contact->interest ?: 'Non spécifié' }}
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

### 💬 Votre message :

> *"{{ $contact->message }}"*

@endif

---

N'hésitez pas à nous contacter si vous avez des questions. Nous sommes là pour vous accompagner dans votre projet sportif !

**[📧 Nous contacter](mailto:{{ config('mail.from.address') }})**

---

### 📞 Nos coordonnées :

- **📧 Email :** {{ config('mail.from.address') }}
- **📞 Téléphone :** [Votre numéro]
- **📍 Adresse :** [Votre adresse]
- **🌐 Site web :** {{ config('app.url') }}

---

Sportivement,  
**L'équipe de {{ config('app.name') }}**

---

*Cet email a été envoyé automatiquement suite à votre demande de contact.*  
*Si vous n'êtes pas à l'origine de cette demande, vous pouvez ignorer ce message.*
</x-mail::message>
