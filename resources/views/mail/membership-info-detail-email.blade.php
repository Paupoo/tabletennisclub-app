<x-mail::message>
    # {{ config('app.name') }}
*Votre club de sport*

---

## Bonjour {{ $contact->first_name }} !

Suite à votre demande d'informations concernant l'adhésion à notre club, nous avons le plaisir de vous transmettre tous les détails pratiques.

---

### 💰 Nos tarifs

#### Licences récréatives
- **Adulte :** 120€ / an
- **Enfant (-16 ans) :** 80€ / an  
- **Famille (3+ personnes) :** -20% sur le tarif total

#### Licences compétitives
- **Adulte :** 180€ / an
- **Enfant (-16 ans) :** 120€ / an
- **Surcoût compétition :** +30€ / an

#### Séances d'entraînement
- **1 séance/semaine :** incluse
- **2 séances/semaine :** +20€ / an
- **Illimitée :** +40€ / an

---

### 📋 Documents nécessaires

Pour finaliser votre adhésion, merci de nous fournir :

- ✅ Formulaire d'inscription complété
- ✅ Certificat médical (- de 1 an)
- ✅ Photo d'identité récente
- ✅ Copie de la pièce d'identité
- ✅ Attestation d'assurance (si applicable)

---

@if($contact->membership_total_cost)
### 🧮 Votre estimation personnalisée

Selon vos besoins exprimés :
@if($contact->membership_family_members)
- **Licences récréatives :** {{ $contact->membership_family_members }} × 100€ = {{ $contact->membership_family_members * 100 }}€
@endif
@if($contact->membership_competitors)
- **Licences compétitives :** {{ $contact->membership_competitors }} × 150€ = {{ $contact->membership_competitors * 150 }}€
@endif
@if($contact->membership_training_sessions > 1)
- **Séances supplémentaires :** {{ $contact->membership_training_sessions - 1 }} × 20€ = {{ ($contact->membership_training_sessions - 1) * 20 }}€
@endif

**💸 Total estimé : {{ $contact->membership_total_cost }}€**

---
@endif

### 📅 Prochaines étapes

1. **Contactez-nous** pour programmer un entretien
2. **Visitez nos installations** (visite gratuite)
3. **Participez à un cours d'essai** (gratuit)
4. **Finalisez votre inscription** avec les documents

---

### 🎯 Cours d'essai gratuit

Venez découvrir notre ambiance lors d'un cours d'essai !

**Créneaux disponibles :**
- Lundi 18h-19h30 : Niveau débutant
- Mercredi 19h-20h30 : Niveau intermédiaire  
- Samedi 10h-11h30 : Cours famille

**[📅 Réserver votre cours d'essai](mailto:{{ config('mail.from.address') }}?subject=Demande cours d'essai)**

---

### 📞 Contact

Des questions ? N'hésitez pas à nous contacter :

- **📧 Email :** {{ config('mail.from.address') }}
- **📞 Téléphone :** [Votre numéro]
- **🕒 Permanences :** Mardi et Jeudi 17h-19h

---

Nous avons hâte de vous accueillir parmi nous !

Sportivement,  
**L'équipe de {{ config('app.name') }}**

---

*Tarifs valables pour la saison {{ date('Y') }}-{{ date('Y') + 1 }}*
</x-mail::message>