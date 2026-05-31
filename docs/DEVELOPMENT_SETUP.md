# Setup développement local

> Guide pour configurer l'environnement de développement sur votre machine locale.

---

## Prérequis

- **PHP 8.5** (or 8.4 minimum)
- **Composer** (latest)
- **Node.js 18+** et **npm**
- **MySQL 8.0+** (ou SQLite pour développement simple)
- **Git**

### Vérifier les versions
```bash
php --version
composer --version
node --version
mysql --version
```

---

## Installation

### 1. Cloner le repository
```bash
git clone <repo-url>
cd tabletennisclub-app
```

### 2. Installer les dépendances PHP
```bash
composer install
```

### 3. Installer les dépendances Node
```bash
npm install
```

### 4. Configurer `.env`
```bash
cp .env.example .env
php artisan key:generate
```

**Configurer la base de données** dans `.env`:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tabletennisclub_app
DB_USERNAME=root
DB_PASSWORD=
```

### 5. Créer la base de données
```bash
mysql -u root -e "CREATE DATABASE tabletennisclub_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### 6. Migrations et seeders
```bash
php artisan migrate
php artisan db:seed
```

### 7. Compiler les assets
```bash
npm run build
```

---

## Commandes quotidiennes

### Démarrer le serveur de développement
```bash
composer run dev
```
Cela lance:
- Vite (frontend hot reload): http://localhost:5173
- Pail (real-time logs): affichées dans le terminal
- Queue worker (traitements asynchrones)

### Lancer les tests
```bash
# Tous les tests
php artisan test --parallel --compact

# Tests d'un domaine
php artisan test --compact tests/Feature/Interclub/

# Un test spécifique
php artisan test --filter=CreateTeamTest
```

### Formater le code
```bash
vendor/bin/pint --dirty
```
**Important:** Pint exclut `database/factories/` (voir TROUBLESHOOTING.md)

### Voir les routes
```bash
php artisan route:list --except-vendor
```

### Accéder à Tinker (PHP REPL)
```bash
php artisan tinker
```
Utile pour tester du code rapidement:
```php
>>> User::factory()->create()
>>> Season::current()
```

---

## Environnement `.env` — Variables clés

```env
# Application
APP_NAME="Table Tennis Club"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=tabletennisclub_app
DB_USERNAME=root
DB_PASSWORD=

# Mail (pour dev local)
MAIL_MAILER=log  # Logs emails instead of sending

# Queue
QUEUE_CONNECTION=sync  # Process jobs synchronously in dev
# ou: QUEUE_CONNECTION=database (stocke dans la DB, worker process)

# Cache
CACHE_DRIVER=array  # In-memory, flushed on restart

# Session
SESSION_DRIVER=cookie
```

### Changements courants
```bash
# Si vous avez un mail service (Mailtrap, etc.)
MAIL_MAILER=smtp
MAIL_HOST=live.smtp.mailtrap.io
MAIL_USERNAME=...
MAIL_PASSWORD=...

# Si vous voulez async jobs
QUEUE_CONNECTION=database
php artisan queue:work  # Dans un autre terminal
```

---

## Makefile / Scripts utiles

Si un `Makefile` existe à la racine:
```bash
make test
make dev
make seed
```

Consultez `composer.json` scripts si pas de Makefile:
```bash
cat composer.json | grep '"scripts"' -A 10
```

---

## Dossiers importants

| Dossier | Contenu |
|---------|---------|
| `app/Domains/` | Logique métier (12 domaines) |
| `tests/` | Tests (Feature + Unit) |
| `database/` | Migrations, factories, seeders |
| `resources/views/` | Blade templates (héritage Livewire) |
| `docs/` | Documentation |
| `.env` | Configuration locale (ne pas committer) |

---

## Troubleshooting lors du setup

### "Class not found" au démarrage
```bash
composer dump-autoload
php artisan config:clear
```

### Erreur "Vite manifest not found"
```bash
npm run build
# ou pour dev avec hot reload:
npm run dev
```

### Migrations échouent
```bash
# Vérifier les permissions DB
# Ou roll back et rejouer:
php artisan migrate:refresh
php artisan db:seed
```

### Tests échouent après git pull
```bash
composer install
npm install
npm run build
php artisan test --parallel --compact
```

---

## VSCode Extensions recommandées

- **Laravel Blade Snippets** (Blakewing)
- **Laravel Artisan** (Ryan Naddy) — lancer artisan commands
- **Livewire Language Support** (frenco)
- **Pest Snippets** (Pest) — test snippets
- **Thunder Client** (Thunder Client) — tester les APIs

---

## Git Workflow

```bash
# Créer une branche
git checkout -b feature/ma-feature

# Faire des commits
git add <fichiers>
git commit -m "feat: description"

# Push
git push origin feature/ma-feature

# Créer un PR sur GitHub
# Puis: code review → merge
```

**Avant commit:**
```bash
php artisan test --parallel --compact  # ✅ Tests passent
vendor/bin/pint --dirty                # ✅ Code formaté
```

