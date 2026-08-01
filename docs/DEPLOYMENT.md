# Déploiement en production

Procédure de mise en production sur un serveur classique (VPS / hébergement), via SSH et `git pull`.

> Les étapes propres à une version donnée (migrations de données à ordonner, seeders exceptionnels, nouvelles variables `.env`) ne sont **pas** ici : elles vivent dans le changelog de la release concernée. Ce document ne décrit que ce qui est valable à **chaque** déploiement.

---

## Prérequis serveur

| Composant | Version / contrainte |
|---|---|
| PHP | 8.5 (mêmes extensions que le dev : `pdo_mysql`, `mbstring`, `gd`, `zip`, `intl`) |
| Composer | 2.x |
| Node / npm | pour compiler les assets (build sur le serveur ou artefact déposé) |
| Base de données | MySQL / MariaDB |
| Accès | SSH avec les droits d'écriture sur le répertoire applicatif |

Trois éléments doivent tourner **en permanence**, en plus du serveur web :

1. **Un worker de queue** — sans lui, aucun e-mail ne part (invitations, rappels de paiement, notifications).
2. **Le planificateur (cron)** — voir [Tâches planifiées](#tâches-planifiées).
3. **Le serveur web** pointant sur `public/`, jamais sur la racine du projet.

---

## Avant de déployer

```bash
# En local, sur la branche à déployer
composer test          # suites Unit/Feature/Architecture en parallèle, puis Browser
vendor/bin/pint        # le formatage est vérifié par composer test
```

> ⚠️ La suite Browser **ne tourne pas** sous `--parallel`. Utilisez `composer test`, qui sépare correctement les suites — ne lancez pas `php artisan test --parallel` seul.

Sur le serveur, avant toute chose :

```bash
# Sauvegarde de la base — non négociable dès qu'il y a une migration
mysqldump -u <user> -p <database> > ~/backups/pre-deploy-$(date +%F-%H%M).sql

# Sauvegarde des documents membres (stockés hors du dépôt)
tar czf ~/backups/storage-$(date +%F-%H%M).tar.gz storage/app
```

---

## Procédure

```bash
cd /chemin/vers/application

# 1. Fermer l'application aux visiteurs
php artisan down --retry=60

# 2. Récupérer le code
git pull origin main

# 3. Dépendances PHP — sans les paquets de dev, autoloader optimisé
composer install --no-dev --optimize-autoloader --no-interaction

# 4. Assets front
npm ci
npm run build

# 5. Schéma de base de données
php artisan migrate --force

# 6. Rôles et permissions — voir l'encadré ci-dessous
php artisan db:seed --class=RoleSeeder --force

# 7. Lien symbolique de stockage public (idempotent)
php artisan storage:link

# 8. Caches de production
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 9. Relancer le worker pour qu'il charge le nouveau code
php artisan queue:restart

# 10. Rouvrir l'application
php artisan up
```

> ### 🔴 Le `RoleSeeder` n'est pas optionnel
>
> L'autorisation repose sur `spatie/laravel-permission` : les rôles et permissions vivent **en base**, pas dans le code. Un déploiement qui saute cette étape laisse la table des permissions dans son état antérieur — les nouveaux droits n'existent pas, et **plus personne n'accède au back-office**.
>
> C'est exactement ce qui s'est produit le 22 juillet 2026. Le seeder est idempotent : le relancer à chaque déploiement ne coûte rien.
>
> Même logique pour `composer install` : un déploiement qui se contente d'un `git pull` tourne avec les dépendances de la version précédente.

---

## Tâches planifiées

L'application définit **6 tâches récurrentes** dans `app/Console/Kernel.php`. Sans cron, elles ne s'exécutent jamais — silencieusement.

Une seule entrée crontab suffit :

```cron
* * * * * cd /chemin/vers/application && php artisan schedule:run >> /dev/null 2>&1
```

| Tâche | Fréquence | Rôle |
|---|---|---|
| `tournament:process-deadlines` | horaire | Expire les places d'attente (48 h) et les inscriptions impayées, envoie les rappels |
| `training:process-deadlines` | horaire | Expire les offres de liste d'attente et promeut le suivant |
| `tournament:close-registrations` | 00 h 05 | Ferme les inscriptions dont la date limite est passée |
| `payment:send-refund-reminder` | lundi 08 h 00 | Rappel de remboursement au trésorier et au secrétaire |
| `season:provision` | 1er juillet, 06 h 00 | Provisionne les deux saisons suivantes (idempotent) |
| `queue:check-health` | horaire | Alerte les admins par e-mail si le worker semble mort |

Chaque tâche est conditionnée au *feature flag* de son domaine : un domaine éteint dans cet environnement n'envoie plus rien.

---

## Worker de queue

`QUEUE_CONNECTION=sync` (la valeur par défaut de `.env.example`) exécute les jobs dans la requête HTTP : **à proscrire en production**. Utilisez `database` ou `redis`, et faites superviser le worker.

Exemple avec systemd :

```ini
# /etc/systemd/system/ttc-queue.service
[Unit]
Description=Table Tennis Club queue worker
After=network.target

[Service]
User=<utilisateur-web>
Restart=always
WorkingDirectory=/chemin/vers/application
ExecStart=/usr/bin/php artisan queue:work --tries=3 --timeout=120 --sleep=3

[Install]
WantedBy=multi-user.target
```

`php artisan queue:restart` demande au worker de s'arrêter proprement à la fin du job courant ; c'est le superviseur qui le relance avec le nouveau code. D'où sa présence à l'étape 9 — sans elle, le worker continue d'exécuter l'ancienne version.

---

## Stockage des fichiers

Deux disques, aux rôles distincts :

| Disque | Emplacement | Exposé au web | Contenu |
|---|---|---|---|
| `public` | `storage/app/public` | oui, via `storage:link` | Photos de profil, images d'articles |
| `local` | `storage/app` | **non** | Documents des membres |

Les documents des membres sont délibérément servis par une route contrôlée, jamais par une URL directe. Ne déplacez rien vers `public` et ne créez pas de lien symbolique vers `storage/app`.

`storage/` et `bootstrap/cache/` doivent être accessibles en écriture à l'utilisateur du serveur web.

---

## Vérifications après déploiement

```bash
php artisan about                    # versions, drivers, caches actifs
php artisan migrate:status | tail    # les dernières migrations sont bien passées
php artisan queue:monitor default    # profondeur de la file
php artisan config:show app.url      # TrustHosts rejette tout si APP_URL est faux
```

Puis, dans le navigateur :

1. Se connecter avec un compte administrateur — valide la chaîne rôles/permissions.
2. Ouvrir une page de chaque délégation (membres, trésorerie, interclubs) — valide les caches de routes et de vues.
3. Déclencher un envoi d'e-mail (renvoi d'invitation, par exemple) et vérifier qu'il part — valide le worker.
4. Ouvrir une page publique — valide le build des assets.

En cas d'écran blanc ou d'erreur 500 après déploiement, la cause la plus fréquente est un cache obsolète :

```bash
php artisan optimize:clear
```

puis rejouer les étapes 8 et 9.

---

## Retour arrière

```bash
php artisan down

git reset --hard <commit-précédent>
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan optimize:clear
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart
php artisan up
```

⚠️ **Les migrations ne se rejouent pas à l'envers sans risque.** Plusieurs migrations de ce projet suppriment des colonnes ou déplacent des données : un `migrate:rollback` détruit les données produites depuis le déploiement. Si le schéma a changé, restaurez le dump pris avant le déploiement plutôt que de faire un rollback.

---

## Variables d'environnement

`.env` n'est pas versionné. À chaque déploiement, comparez-le à `.env.example` pour repérer les nouvelles clés :

```bash
diff <(grep -oE '^[A-Z_]+' .env | sort -u) <(grep -oE '^[A-Z_]+' .env.example | sort -u)
```

Réglages spécifiques à la production :

```dotenv
APP_ENV=production
APP_DEBUG=false          # jamais true en production
APP_URL=https://<domaine>
QUEUE_CONNECTION=database
MAIL_MAILER=smtp         # un vrai relais, pas mailpit
```

Les *feature flags* (bloc en fin de `.env.example`) permettent d'éteindre un domaine jugé immature dans cet environnement. Tout est activé par défaut : ne renseignez une clé que pour **désactiver** un domaine.

---

## Hôtes et proxies de confiance

Deux middlewares globaux dépendent de la topologie réseau du serveur. Ils sont réglés pour l'infrastructure actuelle : **un VPS où Apache sert PHP directement, sans reverse proxy et sans CDN devant le domaine.** Si cela change, les deux sont à revoir.

### `TrustHosts` — actif

Le middleware n'accepte que les requêtes dont l'en-tête `Host` correspond au domaine d'`APP_URL` et à ses sous-domaines. Il ferme l'empoisonnement de cache et des liens de réinitialisation de mot de passe.

⚠️ **`APP_URL` doit être renseigné correctement en production.** S'il est vide ou faux, le middleware rejette *toutes* les requêtes. Il est volontairement inerte en `local` et sous les tests, donc l'erreur ne se voit qu'une fois déployé — c'est le premier point à vérifier après un changement de domaine.

### `TrustProxies` — volontairement vide

`protected $proxies;` reste à `null`. Apache passe le vrai `REMOTE_ADDR` à PHP : `$request->ip()` renvoie déjà l'IP du visiteur, et les limitations par IP (`throttle:10,1` sur le formulaire de contact) comptent bien par visiteur.

**Ne pas y mettre `'*'`** tant que la topologie est celle-ci : ce serait une régression. N'importe qui pourrait envoyer un en-tête `X-Forwarded-For` forgé et contourner toutes les limitations par IP.

À l'inverse, si un reverse proxy, un load balancer ou Cloudflare est un jour placé devant l'application, il **faut** renseigner ses adresses dans `app/Http/Middleware/TrustProxies.php` : sans cela tous les visiteurs partagent l'IP du proxy et un seul suffit à bloquer le formulaire de contact pour tout le club.

Le comportement attendu dans les deux cas est verrouillé par `tests/Feature/Security/TrustedHostsAndProxiesTest.php`.

---

## Voir aussi

- [DEVELOPMENT_SETUP.md](./DEVELOPMENT_SETUP.md) — installation locale
- [TROUBLESHOOTING.md](./TROUBLESHOOTING.md) — erreurs fréquentes (« Class not found », manifeste Vite, migrations bloquées)
- [permissions.md](./permissions.md) — rôles et délégations
