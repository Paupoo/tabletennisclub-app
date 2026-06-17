# Guide des tests — Pest v4

> L'application utilise **Pest v4** pour tous les tests. Tests organisés par domaine, avec factories par domaine.

---

## Setup & Exécution

### Lancer tous les tests
```bash
php artisan test --parallel --compact
```
Résultat attendu: **1536 tests passing** (3 TranslationCoverageTest exclus — nl_BE incomplet, pré-existant).

### Tests filtrés
```bash
# Par fichier
php artisan test tests/Feature/Interclub/AvailabilityTest.php

# Par domaine
php artisan test --compact tests/Feature/Interclub/

# Par regex
php artisan test --filter=CreateTeamTest
```

### Options utiles
```bash
# Sans parallélisation (débug plus facile)
php artisan test --compact

# Affiche les tests qui passent (verbose)
php artisan test --verbose

# Arrête après N failures
php artisan test --bail
```

---

## Couverture de code

### Code coverage (outil dev, pas de gate CI)
```bash
php artisan test --coverage --parallel --memory-limit=512M
```
La couverture de code sert à **explorer les zones sous-couvertes** (outil de développement). Elle n'est pas gatée en CI — objectif de 80% visé, mais non bloquant.

### Type coverage (gate à 100%)
```bash
php artisan test --type-coverage --min=100 --parallel --memory-limit=512M
```
**Tous les fichiers PHP doivent avoir 100% de type coverage.** Cette règle est adoptée après que tous les fichiers aient été mis en conformité. Les types concernés :
- `rt` — return type manquant
- `pa` — paramètre non typé (y compris closures)
- `pr` — propriété de classe non typée
- `co` — constante de classe non typée

**Conventions pour les closures :**
- Closures d'eager loading `with()` / `loadMissing()` : utiliser `\Illuminate\Database\Eloquent\Relations\Relation $q`
- Closures `whereHas()` / `where()` : utiliser `\Illuminate\Database\Eloquent\Builder $q`
- Closures `whereIn()` subquery : utiliser `\Illuminate\Database\Query\Builder $q`
- Closures `array_map` / `array_filter` sur données inconnues : utiliser `mixed $v`

### Mutation testing (prochaine étape)
Le plugin `pestphp/pest-plugin-mutate` n'est pas encore installé. C'est la prochaine étape naturelle pour mesurer la qualité des assertions au-delà de la couverture de ligne.

---

## Structure des tests

```
tests/
  Feature/                          # Tests d'intégration (majorité)
    Interclub/                      # Domaine Competitions
      AvailabilityTest.php
      CaptainSelectionTest.php
      ...
    Tournament/
    Subscription/
    Meetings/
    ...
  Unit/                             # Tests unitaires (logique pure)
    TrainingBuilderTest.php
    InterclubTest.php
    ...
  Trait/                            # Helpers
    CreateUser.php                  # Helper pour créer users dans tests
```

---

## Conventions factories

### Localisations des factories
```
database/factories/
  Domains/
    Competitions/
      Interclub/Models/
      Tournament/Models/
    ClubAdmin/
      Users/Models/UserFactory.php
    Trainings/Models/
    ...
```

### Utilisation : toujours via factories
```php
// ✅ Correct
$user = User::factory()->create();
$season = Season::factory()->create();

// ❌ Jamais créer manuellement si une factory existe
$user = new User(['name' => 'Test']);
```

### States disponibles (exemples)
```php
// UserFactory
$admin = User::factory()->admin()->create();
$secretary = User::factory()->secretary()->create();
$competitor = User::factory()->competitor()->create();

// PaymentFactory
$paid = Payment::factory()->paid()->create();
$unpaid = Payment::factory()->unpaid()->create();
```

### **Important: Convention `protected $model`**
Toute factory doit déclarer son modèle :
```php
// ✅ Correct
class TeamFactory extends Factory {
  protected $model = Team::class;
  
  public function definition(): array { }
}
```

### **Important: Factories exclues de Pint**
Les factories ne doivent **jamais** être formatées par Pint (cause ParseError). 
Commande correcte:
```bash
vendor/bin/pint --dirty -- app database/migrations database/seeders
# ← Notez que database/factories/ n'est PAS inclus
```

---

## Exemples par domaine

### 1. Tester un Livewire Component

```php
test('can create team', function () {
  $this->actingAs($user = User::factory()->admin()->create());
  
  $component = Livewire::test(CreateTeam::class)
    ->set('name', 'Team A')
    ->set('captain_id', User::factory()->create()->id)
    ->call('save');
  
  $component->assertHasNoErrors()
    ->assertDispatched('notification');
  
  expect(Team::where('name', 'Team A')->exists())->toBeTrue();
});
```

### 2. Tester une Policy

```php
test('only admin can create team', function () {
  $admin = User::factory()->admin()->create();
  $user = User::factory()->create();
  
  $this->actingAs($admin)
    ->post(route('admin.teams.store'), [])
    ->assertSuccessful();
  
  $this->actingAs($user)
    ->post(route('admin.teams.store'), [])
    ->assertForbidden();
});
```

### 3. Tester une Action

```php
test('can create subscription', function () {
  $user = User::factory()->create();
  $season = Season::factory()->create();
  
  $subscription = CreateSubscription::resolve()->handle([
    'user_id' => $user->id,
    'season_id' => $season->id,
  ]);
  
  expect($subscription)->toBeInstanceOf(Subscription::class);
  expect($subscription->status)->toBe('pending');
});
```

### 4. Tester un State

```php
test('can only refund paid payment', function () {
  $payment = Payment::factory()->paid()->create();
  
  $payment->state()->refund();
  
  expect($payment->refresh()->status)->toBe('refunded');
});

test('cannot refund unpaid payment', function () {
  $payment = Payment::factory()->unpaid()->create();
  
  expect(fn() => $payment->state()->refund())
    ->toThrow(InvalidOperation::class);
});
```

### 5. Tester une relation

```php
test('user has many subscriptions', function () {
  $user = User::factory()->has(Subscription::factory()->count(3))->create();
  
  expect($user->subscriptions)->toHaveCount(3);
});
```

### 6. Tester une validation Livewire

```php
test('validates team name on save', function () {
  $component = Livewire::test(CreateTeam::class)
    ->set('name', '')  // Vide
    ->call('save');
  
  $component->assertHasErrors(['name' => 'required']);
});
```

#### Assertions de messages Laravel (`__()`)
Utiliser `__('...')` uniquement pour les messages de validation Laravel standard (pas pour les messages custom de l'application) :
```php
// ✅ Pour les messages Laravel standard
$component->assertHasErrors(['email' => __('validation.required')]);

// ❌ Pas pour les messages custom (asserter la clé suffit)
$component->assertHasErrors(['name']);
```

---

## Tests Browser (smoke JS)

L'application utilise une approche smoke test JS (via `BrowserLogsTest`) qui vérifie que chaque page :
1. Répond HTTP 200
2. Ne génère aucune erreur JavaScript dans la console

```bash
# Lancer les smoke tests
php artisan test --filter=BrowserLogsTest
```

Pas de tests Dusk (E2E complets) — l'approche smoke JS + tests Livewire couvre les cas critiques.

---

## Helpers utiles

### ActingAs (authentification)
```php
$this->actingAs($user)->post(...);  // Authentifier comme user
```

### Factories avec relations
```php
$user = User::factory()
  ->has(Subscription::factory()->count(3))
  ->create();
```

### Assert redirects
```php
$response->assertRedirect(route('home'));
```

### Assert validation errors
```php
$response->assertSessionHasErrors(['email' => 'The email field is required']);
```

---

## Checklist avant commit

- [ ] `php artisan test --parallel --compact` ✅ tous les tests passent
- [ ] `php artisan test --type-coverage --min=100 --parallel --memory-limit=512M` ✅ 100%
- [ ] Pas de warnings/skipped tests inattendus
- [ ] Aucune modification de database/factories/ par pint
- [ ] Tests reflètent la logique métier, pas juste "does it exist"
