<x-mail::message>
    # {{ config('app.name') }}
*Votre club de sport*

---

## Bonjour {{ $contact->first_name }},

Nous vous remercions sincèrement pour l'intérêt que vous avez manifesté envers notre club et pour le temps que vous avez consacré à votre candidature.

---

### 📝 Suite à votre demande

Après étude attentive de votre dossier, nous regrettons de vous informer que nous ne pouvons pas donner suite favorable à votre demande d'adhésion à ce stade.

Cette décision peut être due à différentes raisons :
- Effectifs complets pour cette saison
- Critères de sélection spécifiques non remplis
- Planning incompatible avec vos disponibilités

---

### 🔄 Alternatives et perspectives

Cependant, nous aimerions vous proposer plusieurs alternatives :

#### Pour cette saison
- **Liste d'attente :** Inscription prioritaire en cas de désistement
- **Cours d'essai ponctuels :** Participation selon les places disponibles
- **Événements ouverts :** Participation aux compétitions et manifestations

#### Pour la saison prochaine
- **Pré-inscription :** Candidature prioritaire pour {{ date('Y') + 1 }}-{{ date('Y') + 2 }}
- **Stage de préparation :** Formation intensive avant la nouvelle saison

---

### 🤝 Restons en contact

Votre profil nous a intéressés et nous serions ravis de reconsidérer votre candidature ultérieurement.

**Nous vous invitons à :**
- Suivre nos actualités sur nos réseaux sociaux
- Participer à nos événements ouverts au public
- Nous recontacter dans quelques mois

---

### 📅 Prochains événements ouverts

- **🏆 Tournoi amical :** {{ date('d/m/Y', strtotime('+1 month')) }}
- **🎪 Journée portes ouvertes :** {{ date('d/m/Y', strtotime('+2 months')) }}
- **🏃 Stage découverte :** {{ date('d/m/Y', strtotime('+3 months')) }}

---

### 💡 Clubs partenaires

Si vous souhaitez poursuivre votre recherche, nous pouvons vous recommander d'excellents clubs partenaires :

- **[Club A]** - Spécialisé débutants : [contact@cluba.fr](mailto:contact@cluba.fr)
- **[Club B]** - Axé compétition : [info@clubb.fr](mailto:info@clubb.fr)
- **[Club C]** - Pratique loisir : [accueil@clubc.fr](mailto:accueil@clubc.fr)

---

Nous vous remercions encore pour votre démarche et espérons pouvoir vous accueillir prochainement dans de meilleures conditions.

N'hésitez pas à nous recontacter si vous avez des questions ou souhaitez des précisions.

Cordialement,  
**L'équipe de {{ config('app.name')  }}**

---

### 📞 Contact

- **📧 Email :** {{ config('mail.from.address') }}
- **📞 Téléphone :** [Votre numéro]
- **🌐 Site web :** {{ config('app.url') }}

---

*Cette décision ne remet nullement en question vos qualités sportives ou personnelles.*
</x-mail::message>