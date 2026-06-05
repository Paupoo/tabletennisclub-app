# Conventions UI/UX et Livewire

> Guide pour maintenir la cohérence visuelle et comportementale.

---

## Système de design

### Couleurs
- **Primary**: TBD (voir tailwind.config.js)
- **Success**: Green (confirmations)
- **Warning**: Amber (actions à vérifier)
- **Danger**: Red (destructions, erreurs)
- **Info**: Blue (informations)

### Typographie
- **Headings**: Font-weight 600+
- **Body text**: Font-weight 400
- **UI labels**: Font-weight 500

---

## Composants Livewire

### Conventions communes

#### 1. Livewire Forms
```php
class CreateTeamForm extends Component {
  // State
  #[Validate('required|string|max:255')]
  public string $name = '';
  
  #[Validate('required|exists:users,id')]
  public int $captain_id = 0;
  
  // Actions
  public function save() {
    $this->validate();
    CreateTeam::resolve()->handle($this->all());
    $this->reset();
    $this->dispatch('notification', 'Team created!');
  }
  
  public function cancel() {
    $this->dispatch('close-modal');
  }
  
  public function render() {
    return view('livewire.teams.create-form');
  }
}
```

**Conventions**:
- ✅ Public properties pour le state
- ✅ `#[Validate]` attributes pour la validation
- ✅ Méthodes publiques pour les actions
- ✅ `dispatch()` pour communiquer vers parent
- ✅ `reset()` après une action réussie

#### 2. Modals
```blade
<x-modal @close="$wire.cancel()">
  <x-slot name="title">Create Team</x-slot>
  <form wire:submit="save">
    <x-input label="Name" wire:model="name" />
    <x-button-group>
      <x-button type="submit">Save</x-button>
      <x-button type="button" variant="ghost" @click="$wire.cancel()">Cancel</x-button>
    </x-button-group>
  </form>
</x-modal>
```

**Conventions**:
- ✅ Modal headings clairs
- ✅ Footer avec Save/Cancel
- ✅ Feedback immédiat (validation errors inline)

#### 3. Tables
```blade
<x-table>
  <x-slot name="heading">
    <x-table.header-cell>Name</x-table.header-cell>
    <x-table.header-cell>Captain</x-table.header-cell>
    <x-table.header-cell>Actions</x-table.header-cell>
  </x-slot>
  
  @foreach($teams as $team)
    <x-table.row>
      <x-table.cell>{{ $team->name }}</x-table.cell>
      <x-table.cell>{{ $team->captain->name }}</x-table.cell>
      <x-table.cell>
        <x-button size="sm" @click="$wire.edit({{ $team->id }})">Edit</x-button>
        <x-button size="sm" variant="danger" wire:click="delete({{ $team->id }})" wire:confirm="Delete this team?">Delete</x-button>
      </x-table.cell>
    </x-table.row>
  @endforeach
</x-table>
```

**Conventions**:
- ✅ Header row clair
- ✅ Pas plus de 10 colonnes (sinon scroll ou paginate)
- ✅ Actions à droite
- ✅ Delete = danger color + confirmation

#### 4. Empty States
```blade
@if($teams->isEmpty())
  <x-empty-state
    icon="users"
    title="No teams yet"
    description="Create your first team to get started."
  >
    <x-button wire:click="$dispatch('open-modal', { name: 'create-team' })">
      Create Team
    </x-button>
  </x-empty-state>
@else
  <!-- table -->
@endif
```

**Conventions**:
- ✅ Icone évocatrice
- ✅ Titre clair
- ✅ CTA (call-to-action) constructif

#### 5. Loading States
```blade
<x-button wire:click="save" wire:loading.attr="disabled">
  <span wire:loading.remove>Save</span>
  <span wire:loading>Saving...</span>
</x-button>
```

**Conventions**:
- ✅ Bouton disabled pendant l'action
- ✅ Texte change (Save → Saving...)
- ✅ Spinner optionnel

#### 6. Validation Feedback
```blade
<x-input 
  label="Email" 
  wire:model="email" 
  @error('email') aria-invalid="true" @enderror 
/>
@error('email')
  <x-error message="{{ $message }}" />
@enderror
```

**Conventions**:
- ✅ Messages d'erreur inline sous le champ
- ✅ Champ surligné en rouge
- ✅ Classe `aria-invalid` pour accessibilité

#### 7. Notifications (Badges & Alerts)
```blade
<!-- Success -->
<x-alert type="success" title="Success!">
  Your team was created successfully.
</x-alert>

<!-- Warning -->
<x-alert type="warning" title="Heads up">
  Ensure all members have paid their fees.
</x-alert>

<!-- Danger -->
<x-alert type="danger" title="Error">
  Something went wrong. Please try again.
</x-alert>
```

**Conventions**:
- ✅ Icone + titre + message
- ✅ Dismissible (X à droite)
- ✅ Auto-dismiss après 5 secondes (success only)

---

## Index Pages (Listes)

### Composants standards

1. **Header** (titre + CTA)
   ```blade
   <x-page-header title="Teams">
     <x-button @click="$dispatch('open-modal', { name: 'create-team' })">
       Create Team
     </x-button>
   </x-page-header>
   ```

2. **Filters** (si applicable)
   ```blade
   <x-filter-bar>
     <x-select label="Season" wire:model.live="season_id" />
     <x-input label="Search" wire:model.live="search" placeholder="Team name..." />
     <x-button variant="ghost" wire:click="reset">Reset</x-button>
   </x-filter-bar>
   ```

3. **Results**
   - Table si > 5 items
   - Cards si design requires
   - Empty state si 0 items

4. **Pagination** (si > 50 items)
   ```blade
   {{ $teams->links('pagination::tailwind') }}
   ```

---

## Page Admin (ClubAdmin)

### Breadcrumbs
```blade
<x-breadcrumbs :items="[
  ['label' => 'Dashboard', 'url' => route('dashboard')],
  ['label' => 'Teams', 'url' => route('teams.index')],
  ['label' => 'Edit', 'current' => true],
]" />
```

**Convention**: toujours breadcrumbs en haut pour la navigation.

### Sidebar navigation
```blade
<x-sidebar>
  <x-sidebar-item label="Dashboard" icon="home" route="dashboard" />
  <x-sidebar-divider label="Club Management" />
  <x-sidebar-item label="Teams" icon="users" route="teams.index" />
  <x-sidebar-item label="Competitions" icon="trophy" route="competitions.index" />
  <!-- etc -->
</x-sidebar>
```

---

## Public Pages

### Landing/Hero Section
- Hero image ou vidéo
- Headline + subheading
- CTA prominent (Register, Learn more)

### Event Card (pour tournois, entraînements, etc)
```blade
<x-event-card>
  <x-slot name="image">
    <img src="{{ $event->image }}" />
  </x-slot>
  <x-slot name="title">{{ $event->title }}</x-slot>
  <x-slot name="date">{{ $event->event_date->format('d M Y') }}</x-slot>
  <x-slot name="description">{{ $event->description }}</x-slot>
  <x-button>Register</x-button>
</x-event-card>
```

---

## Accessibilité (WCAG 2.1 AA)

- ✅ Contrast ratio 4.5:1 min
- ✅ Labels sur tous les inputs
- ✅ Keyboard navigation (Tab, Enter)
- ✅ Focus indicator visible
- ✅ Alt text sur images
- ✅ ARIA attributes où nécessaire

---

## Responsive Design

- **Mobile-first** approach
- **Breakpoints**:
  - `sm`: 640px
  - `md`: 768px
  - `lg`: 1024px
  - `xl`: 1280px
- **Tables**: Stack sur mobile, scroll si nécessaire
- **Modals**: Full-screen sur mobile, centered sur desktop

---

## Animations & Transitions

- ✅ Fade in/out (200ms)
- ✅ Slide animations (300ms)
- ✅ Smooth hover states
- ❌ Avoid >500ms animations (feel slow)
- ❌ No autoplay videos without user consent

---

## Livewire Best Practices

### 1. Reactive properties
```php
#[Computed]
public function filteredTeams() {
  return Team::query()
    ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
    ->get();
}
```

### 2. Lazy loading
```php
#[Lazy]
public function teams() {
  return Team::paginate(50);
}

// Blade
<livewire:admin.teams.list lazy />
```

### 3. Wire directives
- `wire:model` — two-way binding
- `wire:model.live` — instant updates
- `wire:model.debounce-500` — debounce
- `wire:submit` — form submission
- `wire:click` — button click
- `wire:confirm` — confirmation dialog
- `wire:loading` — loading states

### 4. Dispatching events
```php
// From Livewire
$this->dispatch('team-created', teamId: $team->id);

// Listen in Blade
@addEventListener('team-created', 'handleTeamCreated')

// Listen in Component
#[On('team-created')]
public function handleTeamCreated($teamId) { }
```

---

## Dark Mode

Currently not implemented, but structured for future:
```php
// User preference saved in DB (users.theme)
// Configurable via User settings
// Respect system preference if not set
```

---

## Testing UI

```php
// Test form submission
test('can create team from form', function () {
  livewire(CreateTeamForm::class)
    ->set('name', 'Team A')
    ->set('captain_id', $captain->id)
    ->call('save')
    ->assertDispatched('notification');
  
  expect(Team::whereName('Team A'))->toExist();
});

// Test validation
test('requires team name', function () {
  livewire(CreateTeamForm::class)
    ->call('save')
    ->assertHasErrors('name');
});

// Test table rendering
test('lists all teams', function () {
  $teams = Team::factory(3)->create();
  
  livewire(TeamList::class)
    ->assertSee($teams[0]->name)
    ->assertSee($teams[1]->name)
    ->assertSee($teams[2]->name);
});
```

