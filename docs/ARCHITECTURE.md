# Architecture de l'application

> Guide de la structure du code, des patterns, et des conventions.

---

## Structure organisationnelle: Domaine-First

L'application est organisée par **domaines métier**, pas par type technique.

```
app/
  Domains/                          # Logique métier par domaine
    Competitions/
      Models/
      Actions/
      Services/
      Policies/
      Events/
      Notifications/
    Trainings/
      Models/
      Actions/
      Services/
      ...
    Meetings/
    Subscriptions/
    Communication/
    Resources/
    Bar/
    Shared/                         # Transversal (Enums, Traits, ValueObjects, DomainEvents, Collections)
  
  Livewire/                         # UI layer, organisé par contexte
    Admin/
      Competitions/
      Trainings/
      Meetings/
      Subscriptions/
    Public/
      Competitions/
      ...
    User/
      Trainings/
      ...
    Shared/
      _Components/                  # Composants réutilisables

  Http/
    Controllers/                    # Seulement tournois et paiements (edge cases)
    Requests/
    Middleware/
  
  Models/                          # (LEGACY - À migrer dans Domains/)
  Policies/                        # (LEGACY - À migrer dans Domains/)
  Services/
  Actions/
  Events/
  Notifications/
```

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

**Purpose**: Valider depuis partout (API, CLI, Livewire) sans duplication.

```php
// app/Domains/Subscriptions/Validators/SubscriptionValidator.php
class SubscriptionValidator {
  public function validateForCreation(array $data): array {
    return Validator::make($data, [
      'user_id' => 'required|exists:users,id',
      'season_id' => 'required|exists:seasons,id',
      'subscription_price' => 'required|numeric|min:0',
    ])->validate();
  }
}

// Dans Action
class CreateSubscription {
  public function handle(array $data): Subscription {
    $validated = app(SubscriptionValidator::class)
      ->validateForCreation($data);
    return Subscription::create($validated);
  }
}

// Dans Livewire (feedback immédiat)
class CreateSubscriptionForm extends Component {
  #[Validate('required|exists:users,id')]
  public int $user_id = 0;
  
  public function save() {
    $this->validate();  // ← Feedback immédiat au user
    CreateSubscription::resolve()->handle($this->all());  // ← Action re-valide
  }
}
```

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

### Phase 1 (Déjà fait)
- ✅ Nettoyé code mort (routes, controllers obsolètes)
- ✅ Documenté domaines

### Phase 2 (En cours)
- [ ] Restructurer app/ par domaines
- [ ] Migrer Models
- [ ] Migrer Actions
- [ ] Implémenter Validators (Strategy)
- [ ] Implémenter States
- [ ] Créer Value Objects, Enums, Collections

### Phase 3 (Après démo)
- [ ] Implémenter Domain Events
- [ ] Créer Listeners complets
- [ ] 100% test coverage

