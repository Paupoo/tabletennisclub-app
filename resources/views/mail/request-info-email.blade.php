<x-mail::message>
    # {{ config('app.name') }}
*Votre club de sport*

---

## Bonjour {{ $contact->first_name }},

Merci pour votre demande d'adhésion à notre club ! Nous avons bien reçu votre dossier et nous sommes ravis de votre intérêt.

---

### 📋 Informations complémentaires requises

Pour finaliser l'étude de votre candidature, nous aurions besoin de quelques précisions supplémentaires :

#### 📄 Documents manquants
- [ ] Certificat médical récent (moins de 3 mois)
- [ ] Photo d'identité format standard
- [ ] Justificatif de domicile
- [ ] Autorisation parentale (si mineur)

#### ❓ Informations à préciser
- **Niveau de pratique :** Débutant / Intermédiaire / Confirmé
- **Objectifs sportifs :** Loisir / Remise en forme / Compétition
- **Disponibilités :** Créneaux préférés dans la semaine
- **Expérience antérieure :** Clubs précédents, blessures, etc.

#### 💰 Modalités de paiement
- **Paiement souhaité :** Annuel / Trimestriel / Mensuel
- **Mode de règlement :** Chèque / Virement / Espèces
- **Éligibilité aux aides :** Bourse, CE, réductions familiales

---

### 📅 Entretien individuel

Nous aimerions également programmer un entretien pour :
- Discuter de vos attentes et objectifs
- Vous présenter nos installations
- Définir le groupe le plus adapté
- Répondre à toutes vos questions

**Créneaux disponibles :**
- **Mardi 18h-19h** : Entretiens individuels
- **Jeudi 17h-18h** : Entretiens individuels  
- **Samedi 9h-12h** : Entretiens + visite guidée

---

### 🏃 Cours d'essai

En attendant, n'hésitez pas à participer à un cours d'essai gratuit pour vous faire une idée de notre pédagogie :

**Prochaines sessions ouvertes :**
- {{ date('l d/m', strtotime('next monday')) }} 18h-19h30 : Groupe débutant
- {{ date('l d/m', strtotime('next wednesday')) }} 19h-20h30 : Groupe intermédiaire
- {{ date('l d/m', strtotime('next saturday')) }} 10h-11h30 : Session découverte

---

### 📞 Comment nous transmettre ces informations

**Option 1 : Par email**
Répondez simplement à cet email avec les informations demandées

**Option 2 : Par téléphone**
Appelez-nous au [Votre numéro] pour un échange direct

**Option 3 : Sur place**
Passez nous voir lors de nos permanences :
- **Mardi 17h-19h**
- **Jeudi 17h-19h**  
- **Samedi 9h-12h**

---

### ⏱️ Délai de réponse

Nous vous laissons **15 jours** pour nous transmettre ces éléments. Passé ce délai, votre dossier sera automatiquement archivé, mais vous pourrez bien sûr le réactiver à tout moment.

---

### ❓ Besoin d'aide ?

Si vous avez des questions sur les documents à fournir ou sur la procédure, n'hésitez pas à nous contacter :

- **📧 Email :** {{ config('mail.from.address') }}
- **📞 Téléphone :** {{ config('app.club_phone_number') }}
- **🌐 Site web :** {{ config('app.url') }}

---

Nous avons hâte de vous compter parmi nos membres !

Sportivement,  
**L'équipe de {{ config('app.name') }}**

---

### 🗺️ Accès au club

**📍 Adresse :** {{ config('app.club_street') . ', ' . config('app.club_zip_code') . ' ' . config('app.club_city') }}

- **🚲 Vélo :** Présence d'un parking vélo
- **🚌 Transports :** Ligne 20, arrêt "OTTIGNIES Avenue des Bouvreuils"
- **🚗 Parking :** Gratuit sur place  

---

*Merci de votre compréhension et de votre patience.*
</x-mail::message>