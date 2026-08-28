# Résolution de problèmes fréquents

> Solutions aux erreurs courants rencontrés pendant le développement et la refactorisation.

---

## Tests échouent après une refactorisation

### Symptôme
```
FAILED Tests\Feature\Auth\AuthenticationTest > users can authenticate
```

### Vérifications (dans cet ordre)

1. **Vérifier `config/auth.php`** — chercher d'anciens imports de modèles
   ```bash
   grep -n "App\\Models" config/auth.php
   ```
   Si vous trouvez `use App\Models\ClubAdmin\Users\User;`, remplacer par:
   ```php
   use App\Domains\ClubAdmin\Users\Models\User;
   ```

2. **Vérifier les factories** — s'assurer que `protected $model` est présent
   ```bash
   grep -l "protected \$model" database/factories/Domains/*/Models/*.php
   ```
   Chaque factory **doit** avoir:
   ```php
   class UserFactory extends Factory {
     protected $model = User::class;
     // ...
   }
   ```

3. **Vérifier les seeders** — chercher les anciens namespaces
   ```bash
   grep -r "App\\Models\\ClubAdmin" database/seeders/
   grep -r "App\\States\\\\" database/seeders/
   ```
   Remplacer `App\Models\ClubAdmin\Users\User` par `App\Domains\ClubAdmin\Users\Models\User`

4. **Rebuild autoloader**
   ```bash
   composer dump-autoload
   php artisan config:clear
   php artisan test --parallel --compact
   ```

---

## Pint casse les factories (ParseError)

### Symptôme
```
ParseError: syntax error, unexpected T_STRING in TrainingBuilderTest.php
```

### Cause
Pint's `class_definition` fixer modifie les factories et introduit des erreurs de syntaxe.

### Solution
**Ne jamais formatter `database/factories/` avec Pint.**

Commande correcte:
```bash
vendor/bin/pint --dirty -- app database/migrations database/seeders
# ← Note: database/factories/ est EXCLU
```

### Pourquoi?
Les factories contiennent des déclarations `protected $model` qui peuvent être maladroitement reformattées.

---

## "Class not found" après `git pull`

### Symptôme
```
Class "App\Models\ClubAdmin\Users\User" not found
```

### Solution
Trois étapes pour reconstruire l'environnement:
```bash
composer dump-autoload
php artisan config:clear
php artisan test --parallel --compact
```

---

## Circular dependency imports (pas un problème)

### Symptôme
```
Subscription imports Season, Season imports Training, Training imports Subscription
```

### Pourquoi c'est OK
Les dépendances circulaires existent en PHP, tant qu'elles sont **au niveau des imports**, pas au niveau du constructeur/instanciation.

Laravel les gère correctement avec l'autoloader.

### Solution
Si vous refactorisez un domaine:
1. Mettre à jour **TOUS** les imports uniformément sur toute la base de code
2. Utiliser grep exhaustive pour trouver les anciens namespaces:
   ```bash
   grep -r "App\\Models\\OldNamespace" app tests database config
   ```
3. Remplacer partout avec find-and-replace:
   ```bash
   find . -name "*.php" | xargs perl -pi -e 's{use App\\Models\\OldNamespace}{use App\\Domains\\New\\Namespace}g'
   ```

---

## "Unable to locate file in Vite manifest"

### Symptôme
```
Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest
```

### Solution
Compiler les assets:
```bash
# Pour build une fois
npm run build

# Pour développement avec hot reload
npm run dev
```

---

## "Class not found" inattendu après changement de fichier

### Exemple
```
Class "App\Domains\Competitions\Interclub\Models\Club" not found
```

### Solution rapide
```bash
composer dump-autoload
php artisan config:clear
php artisan view:clear
php artisan cache:clear
```

### Explication
Laravel cache les routes, la config, et les vues. Après un changement de namespace:
- `dump-autoload` → recrée la map des classes PHP
- `config:clear` → vide le cache de la config
- `view:clear` → vide le cache des vues compilées

---

## Migration ne s'exécute pas

### Symptôme
```
Nothing to migrate.
```

### Solution
```bash
# Vérifier l'état
php artisan migrate:status

# Si vous avez modifié une migration non-exécutée:
php artisan migrate

# Si une migration a échoué:
php artisan migrate:refresh  # Reroll tout
php artisan migrate
```

### Attention
`migrate:refresh` **supprime toutes les données**. Utiliser seulement en dev.

---

## Case-sensitive import issues (Windows/Mac)

### Symptôme
Tests passent sur votre PC mais échouent en CI (Linux)

### Cause
Windows/Mac sont case-insensitive pour les fichiers, Linux non.

### Exemple
```php
// ❌ Fonctionne sur Windows, échoue sur Linux
use App\Models\Clubadmin\Users\User;  // Mauvaise casse

// ✅ Correct sur tous les OS
use App\Domains\ClubAdmin\Users\Models\User;
```

### Solution
Vérifier les imports:
```bash
# Trouver les imports avec casse douteuse
grep -r "Clubadmin\|clubadmin" app tests database
```

---

## Queue jobs ne s'exécutent pas

### Symptôme
```
Job sent to queue but never executed
```

### Vérifier le paramètre `.env`
```bash
# Synchronous (dev - exécute immédiatement)
QUEUE_CONNECTION=sync

# Asynchronous (production - nécessite un worker)
QUEUE_CONNECTION=database
```

En dev, utilisez `sync`. Pour tester l'async:
```bash
# Terminal 1
php artisan queue:work

# Terminal 2
php artisan test
```

---

## Un envoi en masse s'arrête au bout de quelques mails

### Symptôme
```
App\Jobs\SendTournamentAnnouncementJob has been attempted too many times.
Illuminate\Queue\MaxAttemptsExceededException
```
Une rafale d'échecs de quelques millisecondes chacun, juste après une publication de
tournoi ou un envoi d'invitations. Les premiers destinataires reçoivent leur mail, les
autres jamais — et rien ne le signale à l'utilisateur.

### Cause
Le middleware `RateLimited` ne met pas un job en attente : quand la fenêtre du limiteur est
pleine, il le **release** en fin de file, et un release **consomme une tentative**. Un job
qui attend son tour est donc « tenté » une fois par fenêtre traversée. Sous un worker lancé
avec `--tries=1` — ce que fait `composer dev` — il est tué à son retour, avant que
`handle()` ne s'exécute.

### Solution
La politique de retry appartient au job, pas au flag du worker (qui diffère entre
`composer dev` et le serveur). Utiliser le trait prévu pour ça :

```php
use App\Jobs\Concerns\RetriesWhileRateLimited;

class SendTournamentAnnouncementJob implements ShouldQueue
{
    use Queueable, RetriesWhileRateLimited;
```

Il pose une échéance (`retryUntil`, 6 h) au lieu d'un compteur — dans le worker, une
`retryUntil` court-circuite entièrement `maxTries` — et un `maxExceptions = 3` pour qu'une
vraie panne échoue vite. Voir le pattern 11 de `ARCHITECTURE.md`.

Pour relancer les mails déjà perdus :
```bash
php artisan queue:retry all
```

---

## Validation échoue en Livewire

### Symptôme
```
Session is missing expected key [errors]
```

### Diagnostic
```php
// Dans le Livewire component
public function save() {
  $this->validate();  // Cela échoue?
  // ...
}
```

### Vérifier
1. Les propriétés sont-elles annotées?
   ```php
   #[Validate('required|string')]
   public string $name = '';
   ```

2. Le form utilise-t-il `wire:model`?
   ```html
   <input type="text" wire:model="name">
   ```

3. L'action backend re-valide?
   ```php
   class CreateTeam {
     public function handle(array $data): Team {
       $validated = $this->validator->validateForCreation($data);
       // ...
     }
   }
   ```

---

## "Too many database connections"

### Symptôme
```
SQLSTATE[HY000]: General error: 1040 Too many connections
```

### Lors des tests parallèles
```bash
# Réduire le nombre de workers
php artisan test --parallel --processes=4
```

### Vérifier le pooling
Dans `.env` ou `config/database.php`:
```php
'mysql' => [
  'options' => [
    PDO::ATTR_TIMEOUT => 5,
  ],
],
```

---

## Livewire component ne se recharge pas après action

### Symptôme
```
Component shows old data after save
```

### Solution
Forcer un refresh:
```php
$this->dispatch('notification', 'Saved!');
$this->team = $this->team->fresh();  // Recharger depuis DB
```

Ou utiliser `#[On]`:
```php
#[On('team-updated')]
public function refresh() {
  $this->team->refresh();
}
```

---

## Questions fréquentes

**Q: Comment déboguer une Action?**  
A: Utiliser Tinker:
```bash
php artisan tinker
>>> CreateTeam::resolve()->handle(['name' => 'Test', ...])
```

**Q: Comment voir les requêtes SQL?**  
A: Activer la log dans `.env`:
```env
LOG_CHANNEL=single
```
Puis lire `storage/logs/laravel.log`

**Q: Tests lents?**  
A: Utiliser `--parallel`:
```bash
php artisan test --parallel --compact
```

