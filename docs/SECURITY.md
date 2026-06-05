# Modèle de sécurité

> Guide des mécanismes de sécurité (authentification, autorisation, validation, protection des routes).

---

## Authentification

### Système
- **Laravel Sanctum** (session-based pour le web, tokens pour API)
- **Laravel Breeze** pour les vues auth prédéfinies
- Middleware: `auth`, `auth:sanctum`

### Tables impliquées
```
users                        # Modèle principal
sessions                     # Sessions HTTP
password_reset_tokens        # Resets
```

### Middleware de protection
```php
// Route group
Route::middleware('auth')->group(function () {
  Route::get('/dashboard', DashboardController::class);
});

// Sur une action Livewire
class EditTeam extends Component {
  #[On('require-auth')]
  public function onlyIfAdmin() {
    if (!Auth::user()->is_admin) {
      abort(403);
    }
  }
}
```

### Session expiration
Configuré dans `config/session.php`:
```php
'lifetime' => 120,  // Expiration après 120 minutes
'expire_on_close' => false,
```

---

## Autorisation (Policies)

### Pattern
Une **Policy** par modèle principal. Rôles centralisés dans `User` model.

### Rôles utilisateurs
```php
// Dans User model
public bool $is_admin;
public bool $is_committee_member;
public ?string $committee_role;  // 'president', 'secretary', 'treasurer'
```

### Policies par domaine

#### ClubAdmin\Users
```php
class UserPolicy {
  public function create(User $user): bool {
    return $user->is_admin;
  }
  
  public function update(User $user, User $target): bool {
    return $user->is_admin || $user->id === $target->id;
  }
}
```

#### Competitions\Teams
```php
class TeamPolicy {
  public function create(User $user): bool {
    return $user->is_committee_member && 
           $user->committee_role !== 'treasurer';
  }
  
  public function update(User $user, Team $team): bool {
    return $user->is_admin || $user->id === $team->captain_id;
  }
  
  public function view(User $user, Team $team): bool {
    return true;  // Public read
  }
}
```

#### Competitions\Interclub
```php
class InterclubPolicy {
  public function manage(User $user): bool {
    return $user->is_committee_member;
  }
  
  public function selectPlayers(User $user, Interclub $ic): bool {
    // Seul le sélectionneur peut
    return $user->id === $ic->season->selector_id;
  }
}
```

### Usage dans les Actions
```php
class UpdateTeam {
  public function handle(Team $team, array $data): Team {
    $this->authorize('update', $team);  // Vérifie la policy
    
    return tap($team)->update($data);
  }
}
```

### Enregistrement des Policies
Dans `app/Providers/AuthServiceProvider.php`:
```php
protected $policies = [
  User::class => UserPolicy::class,
  Team::class => TeamPolicy::class,
  Interclub::class => InterclubPolicy::class,
];
```

---

## Protection des routes

### Routes admin (nécessitent authentification)
```php
// app/routes/web.php
Route::middleware('auth')->group(function () {
  Route::prefix('admin')->group(function () {
    Route::livewire('/teams', 'admin.teams.index')->name('teams.index');
    Route::livewire('/teams/{team}/edit', 'admin.teams.edit')->name('teams.edit');
    // ...
  });
});
```

### Routes publiques (sans authentification)
```php
Route::get('/', HomeController::class)->name('home');
Route::get('/tournaments/{tournament}', ViewTournament::class)->name('tournaments.show');
```

### Livewire components (protection dans le composant)
```php
class EditTeam extends Component {
  public function mount(Team $team) {
    // Vérifier manuellement si nécessaire
    if (!Auth::user()->can('update', $team)) {
      abort(403, 'Not authorized to edit this team');
    }
  }
  
  public function save() {
    // L'Action re-vérifie aussi
    UpdateTeam::resolve()->handle($this->team, [...]);
  }
}
```

---

## Validation

### Double validation (sécurité en profondeur)

#### 1. Livewire (feedback immédiat au user)
```php
class CreateTeamForm extends Component {
  #[Validate('required|string|min:3')]
  public string $name = '';
  
  #[Validate('required|exists:users,id')]
  public int $captain_id = 0;
  
  public function save() {
    $this->validate();  // Validation 1
    
    CreateTeam::resolve()->handle($this->all());
  }
}
```

#### 2. Action (sécurité backend)
```php
class CreateTeam {
  public function __construct(
    private TeamValidator $validator,
  ) {}
  
  public function handle(array $data): Team {
    // Validation 2 (même si on vient du Livewire validé)
    $validated = $this->validator->validateForCreation($data);
    
    // Autorisation
    $this->authorize('create', Team::class);
    
    return Team::create($validated);
  }
}
```

### Validators (par domaine)
```php
// app/Domains/Competitions/Validators/TeamValidator.php
class TeamValidator {
  public function validateForCreation(array $data): array {
    return Validator::make($data, [
      'name' => 'required|string|min:3|max:255',
      'captain_id' => 'required|exists:users,id',
      'league_id' => 'required|exists:leagues,id',
    ])->validate();
  }
}
```

---

## Rôles et permissions par domaine

| Domaine | Action | Rôle requis |
|---------|--------|------------|
| **Users** | Créer user | Admin |
| | Modifier profil | Self ou Admin |
| **Competitions** | Créer équipe | Committee (non-trésorier) |
| | Modifier équipe | Captain ou Admin |
| | Voir résultats | Public |
| | Sélectionner joueurs | Sélectionneur |
| **Trainings** | Créer pack | Secretary |
| | Tracker présences | Trainer |
| | S'inscrire | Logué uniquement |
| **Meetings** | Créer réunion | Président |
| | Voter pour date | Committee |
| | Consulter minutes | Si publique ou committee |
| **Payments** | Voir ses paiements | Self ou Admin |
| | Valider paiement | Trésorier ou Admin |

---

## Protection des données sensibles

### Encryption
Les champs sensibles sont cryptés en DB:
```php
protected $encrypted = [
  'phone_number',  // Dans User
  'email',         // Optionnel
];
```

### Mass assignment
Whitelist les propriétés assignables:
```php
class User extends Model {
  protected $fillable = [
    'first_name', 'last_name', 'email', 'password',
    // Jamais: is_admin, is_committee_member
  ];
  
  protected $guarded = [
    'is_admin',
    'is_committee_member',
  ];
}
```

### Logging des actions sensibles
```php
// Dans une Action
class UpdatePaymentStatus {
  public function handle(Payment $payment, string $status) {
    activity()
      ->causedBy(Auth::user())
      ->performedOn($payment)
      ->withProperties(['old' => $payment->status, 'new' => $status])
      ->log("payment_status_changed");
  }
}
```

---

## CSRF Protection

Automatique dans Laravel pour:
- Tous les form POST/PUT/DELETE
- Inclus dans les blade templates via `@csrf`

Livewire l'inclut automatiquement aussi.

```html
<!-- Dans une blade view -->
<form method="POST">
  @csrf
  <input type="text" name="name">
</form>
```

---

## Best practices de développement

✅ **À faire:**
- Toujours utiliser les Policies pour l'autorisation
- Double-valider (Livewire + Action)
- Whitelister les propriétés assignables
- Utiliser `Auth::user()` pas des variables globales
- Logger les actions sensibles

❌ **À ne pas faire:**
- Hardcoder des rôles (`if ($user->is_admin)` partout)
- Faire confiance aux données du client
- Stocker des secrets dans le code
- Skipper la validation backend
- Exposer d'erreurs détaillées au frontend

