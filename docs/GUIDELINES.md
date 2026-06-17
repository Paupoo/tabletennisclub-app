# Conventions UI/UX et Livewire

> Conventions comportementales, linguistiques et d'accessibilité. Pour les composants et templates blade, voir [DESIGN.md](DESIGN.md).

---

## Internationalisation (i18n)

### Écriture des chaînes

Toutes les chaînes visibles par l'utilisateur doivent être écrites **en anglais** et encapsulées avec le helper `__()`. Les traductions sont ajoutées dans les fichiers JSON correspondants :

| Fichier | Langue |
|---|---|
| `lang/fr_BE.json` | Français (Belgique) |
| `lang/nl_BE.json` | Néerlandais (Belgique) |

```php
// ✅ Correct
__('Create team')
__('Delete this member?')
__('No results found.')

// ❌ Incorrect — chaîne en dur
'Créer une équipe'
```

```json
// lang/fr_BE.json
{
    "Create team": "Créer une équipe",
    "Delete this member?": "Supprimer ce membre ?",
    "No results found.": "Aucun résultat trouvé."
}

// lang/nl_BE.json
{
    "Create team": "Team aanmaken",
    "Delete this member?": "Dit lid verwijderen?",
    "No results found.": "Geen resultaten gevonden."
}
```

> Toute nouvelle chaîne doit être traduite dans **les deux fichiers** simultanément.

### Contexte de traduction

Cette application cible des **clubs de tennis de table belges francophones**. Ce contexte est important pour choisir les bons termes :

- **Sport** : vocabulaire spécifique au tennis de table (voir ci-dessous)
- **Belgique** : orthographe et formulations belges de préférence
- **Ton** : vouvoiement systématique dans toute l'interface (« Votre profil », « Confirmez-vous ? », jamais « ton profil »)
- **Fédération** : terminologie officielle de la fédération (VTTL, AJTBB, ...)

**Termes métier à respecter :**

| Anglais (clé) | Français | Néerlandais |
|---|---|---|
| `Season` | Saison | Seizoen |
| `Series` | Série | Reeks |
| `Interclub` | Interclub | Interclub |
| `Ranking` | Classement | Rangschikking |
| `Licence` | Licence | Licentie |
| `Match` | Rencontre ou Match | Wedstrijd |
| `Set` | Manche | Set |
| `Result` | Résultat | Resultaat |
| `Club room` | Salle | Zaal |

> En cas de doute sur un terme néerlandais spécifique à la fédération (VTTL), demander plutôt que traduire seul.

### Quand prendre une décision seul vs. demander

**Tu peux traduire seul** les chaînes génériques d'interface (labels de formulaire, messages de validation, actions CRUD) pour lesquelles le sens est non-ambigu et n'implique pas de vocabulaire métier.

**Tu dois demander** avant de traduire si :
- La chaîne contient un terme métier spécifique au tennis de table ou à la fédération belge.
- La traduction implique un choix de formulation (ton formel vs. informel).
- Tu n'es pas certain que le terme français corresponde à l'usage réel dans les clubs belges.

### Caractères spéciaux français et Livewire

Blade échappe automatiquement les sorties de `{{ }}` en HTML entities (`é` → `&eacute;`, `ç` → `&ccedil;`, etc.). Dans les contenus HTML classiques, le navigateur decode ces entités sans problème. Mais dans certains contextes **Livewire / Alpine.js**, les entités HTML ne sont pas decodées et s'affichent telles quelles.

**Contextes à risque :**

| Contexte | Problème | Solution |
|---|---|---|
| `wire:confirm` | Le dialog affiche `&eacute;` | Utiliser `{!! !!}` |
| Alpine `x-text` avec valeur PHP | Entités visibles | Utiliser `@js()` |
| Attributs `placeholder`, `title` | Aucun problème | `{{ }}` suffit |
| Contenu textuel `<p>{{ }}</p>` | Aucun problème | `{{ }}` suffit |

**`wire:confirm` — utiliser `{!! !!}` :**
```blade
{{-- ❌ Affiche "Supprimer l&apos;&eacute;quipe ?" dans le dialog --}}
<x-button wire:confirm="{{ __('Delete this team?') }}" wire:click="delete">

{{-- ✅ Affiche "Supprimer l'équipe ?" --}}
<x-button wire:confirm="{!! __('Delete this team?') !!}" wire:click="delete">
```

> **Sécurité** : utiliser `{!! !!}` est sûr ici car les chaînes viennent de `lang/fr_BE.json` (code source contrôlé), jamais de données utilisateur.

**Alpine.js — utiliser `@js()` :**
```blade
{{-- ❌ Entités HTML visibles dans l'expression JS --}}
<div x-data="{ label: '{{ __('No results found.') }}' }">

{{-- ✅ Valeur correctement encodée pour JavaScript --}}
<div x-data="{ label: {{ @js(__('No results found.')) }} }">
```

---

## Accessibilité (WCAG 2.1 AA)

- ✅ Contrast ratio 4.5:1 min
- ✅ Labels sur tous les inputs
- ✅ Keyboard navigation (Tab, Enter)
- ✅ Focus indicator visible
- ✅ Alt text sur images
- ✅ ARIA attributes où nécessaire (`aria-invalid` sur les champs en erreur)

---

## Responsive Design

- **Mobile-first** approach
- **Breakpoints** (Tailwind standards) :
  - `sm`: 640px
  - `md`: 768px
  - `lg`: 1024px — seuil desktop (tables visibles, mobile cards cachées)
  - `xl`: 1280px
- **Tables** : mobile cards (`lg:hidden`) + desktop table (`hidden lg:block`) — voir [DESIGN.md](DESIGN.md)
- **Modals** : full-screen sur mobile, centered sur desktop

---

## Animations & Transitions

- ✅ Fade in/out : 200ms
- ✅ Slide animations : 300ms
- ✅ Smooth hover states
- ❌ Éviter les animations > 500ms (perçues comme lentes)
- ❌ Pas d'autoplay vidéo sans consentement utilisateur

---

## Livewire — Bonnes pratiques

### Wire directives

| Directive | Usage |
|---|---|
| `wire:model` | Two-way binding (pas de mise à jour temps réel) |
| `wire:model.live` | Mise à jour instantanée |
| `wire:model.live.debounce.300ms` | Debounce 300ms (standard pour la recherche) |
| `wire:submit` | Soumission de formulaire |
| `wire:click` | Action au clic |
| `wire:confirm="{!! __('...') !!}"` | Dialog de confirmation (voir gotcha i18n ci-dessus) |
| `wire:loading` | États de chargement |
| `wire:navigate` | Navigation SPA |

### Propriétés réactives

```php
#[Computed]
public function filteredTeams(): Collection
{
    return Team::query()
        ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
        ->get();
}
```

### Lazy loading

```php
#[Lazy]
public function render(): View
{
    return view('livewire.admin.teams.list');
}

// Blade
<livewire:admin.teams.list lazy />
```

### Dispatching d'événements

```php
// Émettre depuis Livewire
$this->dispatch('team-created', teamId: $team->id);

// Écouter dans un composant
#[On('team-created')]
public function handleTeamCreated(int $teamId): void { }
```

### État après action réussie

- Appeler `$this->reset()` ou `$this->resetExcept()` après une création/suppression réussie
- Fermer les modals via `$this->createModal = false` (pas de dispatch)
- Rediriger vers la page dédiée après création d'une entité complexe (≥ 5 champs)

---

## Dark Mode

Non implémenté actuellement. Structure prévue :
- Préférence stockée en DB (`users.theme`)
- Configurable via les paramètres utilisateur
- Respect de la préférence système si non définie
