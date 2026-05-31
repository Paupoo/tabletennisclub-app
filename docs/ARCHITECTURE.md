# Architecture de l'application

> Guide de la structure du code, des patterns, et des conventions.

---

## Structure organisationnelle: Domaine-First (12 Domaines)

L'application est **entièrement réorganisée** par domaines métier, pas par type technique.
Tous les fichiers ont été migrés de `app/Models/`, `app/Actions/`, etc. vers `app/Domains/`.

```
app/Domains/
  Bar/                              # Bar management
    Models/, Actions/, Services/, ...
  
  ClubAdmin/                        # Administrative domain
    Club/
    Contact/
    Payment/
    Subscriptions/
    Users/
  
  ClubPosts/                        # News and articles
  
  Competitions/                     # Team competitions and tournaments
    Interclub/                      # (6 models, 7 factories)
    Tournament/                     # (4 models, 4 factories)
  
  Meetings/                         # Committee meetings
  
  Shared/                           # Transversal utilities
    Casts/
    Enums/                          # (27 files)
    Events/                         # (4 files: Tournament events)
    Exceptions/
    Models/                         # (Shared models like AppSetting)
    Rules/                          # (Validation rules)
    States/                         # (Payment states × 6, Tournament states × 11)
    Traits/                         # (HasAvailability, etc.)
  
  Trainings/                        # Training sessions and packs

  Livewire/                         # UI layer (organized by context)
    Admin/
      Competitions/, Meetings/, Subscriptions/, ...
    Public/
      Competitions/, ...
    User/
      Trainings/, ...
    Shared/
      _Components/                  # Reusable components

  Http/
    Controllers/                    # (Minimal legacy, mostly migrated to Livewire)
    Requests/
    Middleware/
```

**Migration Status**: ✅ **100% Complete** (12/12 domains moved)
- All models moved from `/Models/` → `/Domains/{Domain}/Models/`
- All factories moved from `/database/factories/` → `/database/factories/Domains/{Domain}/Models/`
- All events consolidated into `/Domains/Shared/Events/`
- All states consolidated into `/Domains/Shared/States/`

---

## Patterns architecturaux

### 1. Actions (Classe par action = Une responsabilité)

**Purpose**: Encapsuler une seule opération métier, réutilisable de partout.

**Signature**:
```php
// app/Domains/Competitions/Actions/CreateTeam.php
namespace App\Domains\Competitions\Actions;

class CreateTeam {
  public function __construct(
    private TeamValidator $validator,
    private TeamPolicy $policy,
  ) {}

  public function handle(array $data): Team {
    // 1. Validate
    $validated = $this->validator->validateForCreation($data);
    
    // 2. Authorize
    $this->authorize('create', Team::class);
    
    // 3. Execute
    $team = Team::create($validated);
    
    // 4. Dispatch event
    event(new TeamCreated($team));
    
    return $team;
  }
}
```

**Utilisation**:
```php
// Depuis Livewire
CreateTeam::resolve()->handle($data);

// Depuis CLI/API/Test
app(CreateTeam::class)->handle($data);
```

**Conventions**:
- ✅ Une classe = Une action
- ✅ Idempotent (même entrée = même résultat)
- ✅ Validable, autorisable, testable
- ✅ Retourn l'entité créée/modifiée

---

### 2. Services avec Strategy Pattern (Pour la logique transversale)

**Purpose**: Logique réutilisable par plusieurs domaines.

**Exemple**: S'inscrire à X (Training pack, Season, Interclub)

```php
// app/Domains/Subscriptions/Services/Handlers/SubscriptionHandler.php
interface SubscriptionHandler {
  public function canSubscribe(User $user): bool;
  public function getPrice(): int;
  public function subscribe(User $user): void;
}

// Implémentations
class TrainingPackSubscriptionHandler implements SubscriptionHandler { }
class SeasonSubscriptionHandler implements SubscriptionHandler { }
class InterclubSelectionHandler implements SubscriptionHandler { }

// Service transversal
class SubscriptionService {
  public function subscribe(Subscribable $entity, User $user): void {
    $handler = $this->resolveHandler($entity);
    
    if (!$handler->canSubscribe($user)) {
      throw new InvalidOperation('User cannot subscribe');
    }
    
    $handler->subscribe($user);
  }
}
```

---

### 3. States (Lightweight State Pattern)

**Purpose**: Encapsuler la logique "chaque état sait ce qu'il peut faire".

**Exemple**: Payment peut être unpaid, paid, refunded...

```php
// app/Domains/Subscriptions/States/PaymentState.php
abstract class PaymentState {
  public function __construct(protected Payment $payment) {}
}

class PaymentUnpaidState extends PaymentState {
  public function canPay(): bool { return true; }
  public function canRefund(): bool { return false; }
  
  public function pay(int $amount): void {
    if ($amount >= $this->payment->amount_due) {
      $this->payment->status = 'paid';
    } else {
      $this->payment->status = 'partially_paid';
    }
    $this->payment->save();
  }
}

class PaymentPaidState extends PaymentState {
  public function canPay(): bool { return false; }
  public function canRefund(): bool { return true; }
  
  public function refund(): void {
    $this->payment->status = 'refunded';
    $this->payment->save();
  }
}

// Sur le Model
public function state(): PaymentState {
  return match($this->status) {
    'unpaid' => new PaymentUnpaidState($this),
    'paid' => new PaymentPaidState($this),
    'refunded' => new PaymentRefundedState($this),
  };
}

// Usage
$payment->state()->refund();  // ✅ si paid
$payment->state()->refund();  // ❌ Exception si unpaid
```

---

### 4. Validation (Strategy + Livewire hybrid)

**Purpose**: Valider depuis partout (API, CLI, Livewire, Commands) sans duplication. **Replaces Form Requests.**

#### **Validator Classes (Strategy Pattern)**
```php
// app/Domains/Competitions/Interclub/Validators/TeamValidator.php
namespace App\Domains\Competitions\Interclub\Validators;

class TeamValidator {
  public function validateForCreation(array $data): array {
    return Validator::make($data, [
      'name' => 'required|string|min:2|max:255|unique:teams,name',
      'captain_id' => 'required|exists:users,id',
      'league_id' => 'required|exists:leagues,id',
    ])->validate();
  }
  
  public function validateForUpdate(array $data): array {
    return Validator::make($data, [
      'name' => 'sometimes|required|string|min:2|max:255',
      'captain_id' => 'sometimes|required|exists:users,id',
    ])->validate();
  }
}
```

#### **Action uses Validator**
```php
// app/Domains/Competitions/Interclub/Actions/CreateTeamAction.php
class CreateTeamAction {
  public function __construct(
    private TeamValidator $validator,
  ) {}

  public function handle(array $data): Team {
    // 1. Validate
    $validated = $this->validator->validateForCreation($data);
    
    // 2. Authorize
    $this->authorize('create', Team::class);
    
    // 3. Execute
    $team = Team::create($validated);
    
    // 4. Dispatch event
    event(new TeamCreated($team));
    
    return $team;
  }
}
```

#### **Livewire (double validation: immediate feedback + backend security)**
```php
// app/Livewire/Admin/Competitions/CreateTeamForm.php
class CreateTeamForm extends Component {
  #[Validate('required|string|min:2|max:255')]
  public string $name = '';
  
  #[Validate('required|exists:users,id')]
  public int $captain_id = 0;
  
  public function save() {
    $this->validate();  // ← Feedback immédiat au user
    CreateTeamAction::resolve()->handle($this->all());  // ← Action re-valide
    $this->dispatch('notification', 'Team created!');
  }
}
```

**Why double validation?**
- **Livewire**: User sees errors immediately (UX)
- **Action**: Backend validates again (security - never trust client)

---

### 5. Authorization (Policies — Laravel standard)

**Purpose**: Contrôler qui peut faire quoi.

```php
// app/Domains/Competitions/Policies/TeamPolicy.php
class TeamPolicy {
  public function create(User $user): bool {
    return $user->is_committee_member && $user->committee_role !== 'treasurer';
  }
  
  public function update(User $user, Team $team): bool {
    return $user->is_admin || $user->id === $team->captain_id;
  }
}

// Dans Action
class UpdateTeam {
  public function handle(Team $team, array $data): Team {
    $this->authorize('update', $team);  // ← Vérifie
    // ...
  }
}
```

---

### 6. Value Objects (Immutables)

**Purpose**: Représenter des concepts métier avec logique.

```php
// app/Domains/Shared/ValueObjects/Price.php
class Price {
  private function __construct(public readonly int $cents) {
    if ($this->cents < 0) {
      throw new InvalidArgumentException('Price cannot be negative');
    }
  }
  
  public static function fromEuros(float $euros): self {
    return new self((int)($euros * 100));
  }
  
  public function add(Price $other): Price {
    return new self($this->cents + $other->cents);
  }
  
  public function toEuros(): float {
    return $this->cents / 100;
  }
}

// Usage
$price = Price::fromEuros(25.50);
$total = $price->add(Price::fromEuros(10.00));
```

---

### 7. Domain Events

**Purpose**: Notifier que quelque chose s'est passé (sans coupling).

```php
// app/Domains/Competitions/Events/TeamCreated.php
class TeamCreated {
  public function __construct(public Team $team) {}
}

// Dans Action
class CreateTeam {
  public function handle(array $data): Team {
    $team = Team::create($validated);
    event(new TeamCreated($team));  // ← Dispatch
    return $team;
  }
}

// Listener
class SendTeamCreatedNotification {
  public function handle(TeamCreated $event) {
    Notification::send($captains, new TeamWasCreatedNotification($event->team));
  }
}
```

---

### 8. Enums (Statuts, rôles, catégories)

**Purpose**: Type-safe constantes.

```php
// app/Domains/Shared/Enums/PaymentStatus.php
enum PaymentStatus: string {
  case Unpaid = 'unpaid';
  case Paid = 'paid';
  case PartiallyPaid = 'partially_paid';
  case Refunded = 'refunded';
  case Cancelled = 'cancelled';
}

// Usage
$payment->status = PaymentStatus::Paid;
if ($payment->status === PaymentStatus::Paid) { }
```

---

### 9. Collections (Groupes d'objets)

**Purpose**: Collection typée avec logique métier.

```php
// app/Domains/Competitions/Collections/InterclubSelections.php
class InterclubSelections extends Collection {
  public function __construct(array $selections = []) {
    parent::__construct(array_map(
      fn($item) => $item instanceof InterclubUser ? $item : InterclubUser::find($item),
      $selections
    ));
  }
  
  public function confirmed(): self {
    return new self($this->filter(fn($s) => $s->is_selected)->all());
  }
  
  public function available(): self {
    return new self($this->filter(fn($s) => $s->availability === 'available')->all());
  }
}

// Usage
$interclub->selections()->confirmed()->count();
```

---

### 10. Livewire Components (Par contexte)

**Purpose**: UI réactive (Admin/Public/User).

**Structure**:
```
app/Livewire/
  Admin/Competitions/EditTeam.php      # Admin vu
  Public/Competitions/ViewTeam.php     # Public vu
  User/Competitions/MyTeam.php         # User vu
```

**Convention**:
```php
class EditTeam extends Component {
  public Team $team;
  
  #[Validate('required|string')]
  public string $name = '';
  
  #[On('team-updated')]
  public function refresh() {
    $this->team->refresh();
  }
  
  public function save() {
    $this->validate();
    
    UpdateTeam::resolve()->handle($this->team, [
      'name' => $this->name,
    ]);
    
    $this->dispatch('notification', 'Team updated!');
  }
}
```

---

## Conventions de codage

### Naming
- **Classes**: PascalCase (CreateUser, PaymentValidator, TeamPolicy)
- **Methods**: camelCase (validateForCreation, canSubscribe)
- **Properties**: camelCase (firstName, amountDue)
- **Constants**: UPPER_SNAKE_CASE (MAX_PARTICIPANTS, DEFAULT_PRICE)

### Type hints
```php
// ✅ Toujours typer
public function create(array $data): Team { }

// ✅ Retourner le bon type
public function getPrice(): int { }

// ❌ Jamais de mixed/any
public function handle($data) { }
```

### Comments
```php
// ✅ Seulement le WHY non-obvious
// Firewall rules require us to batch requests (historical constraint from 2022)
public function batchSubscriptions() { }

// ❌ Pas le WHAT (le code le dit)
// This method updates the user
public function updateUser() { }
```

---

## Workflow d'une feature

**Exemple**: Créer une équipe

```
1. User (Admin) remplit le form Livewire
   ↓
2. Component valide: #[Validate('required|string')]
   ↓
3. User clique "Save"
   ↓
4. Component appelle CreateTeam action
   ↓
5. Action:
   - Valide: TeamValidator::validateForCreation()
   - Autorise: Policy::create()
   - Exécute: Team::create()
   - Dispatch: event(new TeamCreated())
   ↓
6. Listener: SendTeamCreatedNotification
   ↓
7. Component reçoit notification, refresh
```

---

## Testabilité

Tous les patterns sont testables:

```php
// Test Action
test('can create team', function () {
  $result = CreateTeam::resolve()->handle([
    'name' => 'Team A',
    'captain_id' => $captain->id,
  ]);
  
  expect($result)->toBeInstanceOf(Team::class);
  expect($result->name)->toBe('Team A');
});

// Test Validator
test('validates team creation', function () {
  $validator = new TeamValidator();
  $validator->validateForCreation(['name' => '']);
})->throws(ValidationException::class);

// Test Policy
test('only secretary can create team', function () {
  $user = User::factory()->treasurer()->create();
  expect(new TeamPolicy())->create($user)->toBeFalse();
});

// Test State
test('can only refund paid payment', function () {
  $payment = Payment::factory()->paid()->create();
  $payment->state()->refund();
  
  expect($payment->status)->toBe('refunded');
});
```

---

## Migration vers la nouvelle structure

### Phase 1: Cleanup (Complété — bedcbfa5 + 090eb184)
- ✅ Removed obsolete routes (REST endpoints for retired features)
- ✅ Removed legacy Controllers/Actions/Livewire
- ✅ Fixed all test failures (1316/1316 passing)
- ✅ Adapted tests to use Livewire routes

### Phase 2: Domain Reorganization (Complété — c7f1d295 + 690a3b90 + c86ecf40)
- ✅ Restructured all app/ by business domains (12 domains)
- ✅ Migrated 11 models: Tournament, Meetings, Subscriptions, Interclub, User, etc.
- ✅ Migrated all factories (added `protected $model` declarations)
- ✅ Consolidated States (Payments × 6, Tournament × 11) into Shared
- ✅ Consolidated Events (Tournament × 3, Public × 1) into Shared
- ✅ Updated all imports across app/, tests/, database/ (219 User imports + States + Events)
- ✅ Maintained 1316/1316 tests passing throughout

**Key lessons learned:**
- **Circular dependencies are solvable** — uniform namespace updates work
- **Pint + factories needs care** — exclude database/factories/ from formatter
- **Config files matter** — always check config/ for model imports
- **Case-sensitive grep is essential** — `Clubadmin` vs `ClubAdmin` can hide

### Phase 3: Patterns & Enhancements (Chantier 4)

**Day 14-15: Validators (Strategy Pattern)**
- ✅ TournamentValidator, TeamValidator, SubscriptionValidator, TrainingPackValidator, MeetingValidator, ArticleValidator
- ✅ Example Action (CreateTeamAction) showing validator integration
- ✅ Replaces legacy Form Requests (25 files)

**Day 16: State Pattern Wiring**
- ✅ Tournament model: `state()` method returns TournamentStateInterface via factory
- ✅ Subscription model: `getCurrentState()` already wired (internal states)

**Day 17: Value Objects**
- ✅ Money: immutable monetary amounts (cents internally)
- ✅ Price: immutable pricing with free() factory
- ✅ Score: immutable table tennis scores with winner logic

**Day 18: Domain Events & Listeners**
- ✅ New events: SubscriptionCreated, SubscriptionPaid, MeetingCreated, TeamCreated, TrainingPackEnrolled
- ✅ Listeners: SendSubscriptionConfirmationEmail, NotifyParticipantsOfMeeting, SendTeamCreatedNotification
- ✅ EventServiceProvider: registered all events with listeners

**Day 19: Documentation (this section)**
- ✅ Marked Phase 3 complete
- ✅ All 1316 tests passing

**Future work:**
- [ ] 100% test coverage (optional)
- [ ] Domain Collections (optional)

