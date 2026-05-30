# 📚 Table Tennis Club App — Documentation

Bienvenue dans la documentation complète de l'application Table Tennis Club.

---

## 📖 Structure de la documentation

### 1. **[DOMAINS.md](./DOMAINS.md)** — Les cœurs de l'application
Comprenez les 6 domaines métier principaux:
- **Competitions** (Équipes + Interclubs)
- **Trainings** (Entraînements + packs)
- **Meetings** (Réunions du comité)
- **Subscriptions** (Adhésions + paiements)
- **Communication** (Articles + événements)
- **Resources** (Salles + tables)

**Lire quand**: Vous découvrez l'application, vous avez besoin de comprendre la logique métier.

---

### 2. **[DATABASE.md](./DATABASE.md)** — Schéma de la base de données
Vue complète des tables, relations, et logique de données:
- Diagramme Entité-Relation (Mermaid ERD)
- Tables par domaine
- Relations clés (polymorphes, hiérarchies, transversaux)
- Énumérés et statuts

**Lire quand**: Vous modifiez la BD, vous créez une migration, vous devez comprendre une relation.

---

### 3. **[ARCHITECTURE.md](./ARCHITECTURE.md)** — Structure du code et patterns
Comment le code est organisé et les patterns utilisés:
- **Structure**: Domaine-first (pas par type technique)
- **Patterns**: Actions, Services (Strategy), States, Validators, Authorization (Policies)
- **Autres**: Value Objects, Domain Events, Enums, Collections
- **Livewire**: Components par contexte (Admin, Public, User)
- **Testabilité**: Comment tester chaque pattern

**Lire quand**: Vous développez une feature, vous avez besoin de comprendre comment implémenter quelque chose.

---

### 4. **[GUIDELINES.md](./GUIDELINES.md)** — Conventions UI/UX et Livewire
Comment construire l'interface utilisateur de façon cohérente:
- **Composants Livewire**: Forms, Modals, Tables, Empty states, Loading states
- **Patterns**: Pages, Filtres, Pagination, Breadcrumbs
- **Accessibilité**: WCAG 2.1 AA
- **Responsive**: Mobile-first
- **Best practices**: Reactive properties, Lazy loading, Wire directives

**Lire quand**: Vous créez une nouvelle page/composant Livewire, vous avez besoin de cohérence UI.

---

### 5. **[FEATURES.md](./FEATURES.md)** — Features principales & FAQ
Comment utiliser les features principales:
- **Compétitions**: Sélections, résultats
- **Entraînements**: Créer packs, tracker présences
- **Réunions**: Voter pour date, publier minutes
- **Subscriptions**: Onboarding, paiements
- **Communication**: Articles, événements
- **Dashboard**: Que voit chaque rôle?

**Lire quand**: Vous testez une feature, vous avez une question sur le workflow.

---

## 🚀 Pour commencer rapidement

### Je veux...

**Comprendre l'application en 5 minutes**
1. Lire [DOMAINS.md](./DOMAINS.md) (summary des 6 domaines)
2. Regarder le diagramme ERD dans [DATABASE.md](./DATABASE.md)

**Développer une nouvelle feature**
1. Identifier le domaine dans [DOMAINS.md](./DOMAINS.md)
2. Regarder la structure dans [ARCHITECTURE.md](./ARCHITECTURE.md)
3. Voir un exemple de feature dans [FEATURES.md](./FEATURES.md)
4. Implémenter en suivant les patterns de [ARCHITECTURE.md](./ARCHITECTURE.md)
5. Tester en utilisant les exemples de test de [ARCHITECTURE.md](./ARCHITECTURE.md)

**Créer une page Livewire**
1. Lire le pattern "Livewire Components" dans [ARCHITECTURE.md](./ARCHITECTURE.md)
2. Lire la section "Composants Livewire" dans [GUIDELINES.md](./GUIDELINES.md)
3. Copier un exemple similaire dans la codebase

**Ajouter une validation**
1. Lire le pattern "Validation (Strategy + Livewire hybrid)" dans [ARCHITECTURE.md](./ARCHITECTURE.md)
2. Créer une classe `{Domaine}Validator`
3. L'utiliser dans l'Action

**Modifier la BD**
1. Identifier la table dans [DATABASE.md](./DATABASE.md)
2. Créer une migration
3. Mettre à jour le Model
4. Update [DATABASE.md](./DATABASE.md) si la structure change

---

## 📊 Vue globale de l'architecture

```
User Input (Livewire Component)
  ↓
Validation (#[Validate] + Validator Strategy)
  ↓
Action (une responsabilité)
  ├─ Validator::validate()
  ├─ Policy::authorize()
  ├─ Model::create/update/delete
  └─ event(new DomainEvent())
  ↓
Listener (réagit à l'événement)
  ├─ Notification
  ├─ Database update
  └─ Cache invalidation
  ↓
State Management (chaque domaine a ses états)
  ├─ Payment: unpaid → paid → refunded
  ├─ Meeting: planning → voting → scheduled → completed
  └─ Subscription: pending → confirmed → paid
  ↓
Database (Domaine-organized tables)
  ↓
UI Feedback (Livewire dispatch / redirect)
```

---

## 🔑 Concepts clés

### Domaine
Unité d'organisation isolée (Competitions, Trainings, etc).
Contient: Models, Actions, Services, Policies, Events, Notifications, Validators.

### Action
Une classe = une opération métier atomique.
Idempotente, testable, réutilisable de partout.
Exemple: `CreateTeam`, `ConfirmPayment`, `SelectInterclubPlayer`.

### Service (Strategy Pattern)
Logique réutilisable par plusieurs domaines.
Interface + implémentations par domaine.
Exemple: `SubscriptionService` → `TrainingPackHandler`, `SeasonHandler`, etc.

### State
Chaque état sait ce qu'il peut faire.
Empêche les opérations invalides.
Exemple: `PaymentPaidState` → peut refund, ne peut pas payer.

### Value Object
Entité immutable avec logique.
Type-safe, validable à la construction.
Exemple: `Price`, `Score`.

### Domain Event
"Quelque chose s'est passé" — notifie les listeners sans coupling.
Exemple: `TeamCreated`, `PaymentProcessed`.

---

## 🛠️ Stack technique

- **Backend**: Laravel 13, PHP 8.5
- **Frontend**: Livewire 4, Alpine.js 3, TailwindCSS 4
- **Testing**: Pest 4
- **Database**: SQLite (dev), MySQL (prod)
- **Notifications**: Database + Email

---

## 👥 Rôles & Permissions

| Rôle | Domaines | Exemples d'actions |
|------|----------|-------------------|
| **Admin (IT)** | Tous | Gérer settings, débloquer situations |
| **Président** | Meetings, Communication | Créer réunions, publier articles |
| **Secrétaire** | Trainings, Subscriptions, Communication | Créer packs, onboard members, publier |
| **Trésorier** | Subscriptions | Valider paiements, tracker affiliation |
| **Sélectionneur** | Competitions | Planifier saison, superviser sélections |
| **Entraîneur** | Trainings, Resources | Tracker présences, annoncer annulations |
| **Capitaine** | Competitions | Sélectionner joueurs pour matchs |
| **Compétiteur** | Competitions, Trainings, Subscriptions | Donner dispo, s'inscrire, payer |

---

## 📖 Conventions

### Code
- **Classes**: PascalCase (CreateTeam, PaymentValidator)
- **Methods**: camelCase (validateForCreation, canSubscribe)
- **Properties**: camelCase (firstName, amountDue)
- **Enums**: UPPER_SNAKE_CASE (PaymentStatus, UserRole)

### Commits
```
<type>: <subject>

<body>

Contributor: Aurélien Paulus <aurelien.paulus@gmail.com>
```

Types: `feat`, `fix`, `refactor`, `docs`, `test`, `chore`

### Testing
- Use Pest (not PHPUnit)
- Test Actions, Validators, Policies
- Use factories for models
- Organize tests by domain

---

## 🔗 Accès rapide

- **Domains overview**: [DOMAINS.md](./DOMAINS.md#résumé-des-domaines)
- **DB schema**: [DATABASE.md](./DATABASE.md)
- **All patterns**: [ARCHITECTURE.md](./ARCHITECTURE.md#patterns-architecturaux)
- **All components**: [GUIDELINES.md](./GUIDELINES.md#composants-livewire)
- **How-to guides**: [FEATURES.md](./FEATURES.md)

---

## 🎯 Prochaines étapes (Phase 2)

After the initial demo (Day 20):
- Implémenter Domain Events complets
- Créer Listeners manquants
- Atteindre 100% test coverage
- Migrer le code legacy (Migrations, Requests, Controllers)
- Optimiser les queries (N+1, eager loading)

---

**Version**: 1.0 (after Phase 1 cleanup/reorganization)  
**Last updated**: 2026-05-30  
**Maintained by**: [@aurelien](https://github.com/aurelienjp)

