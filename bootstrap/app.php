<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Http\Middleware\CommitteeMemberMiddelware;
use App\Http\Middleware\EnsureFeatureIsEnabled;
use App\Http\Middleware\EnsureProfileIsComplete;
use App\Http\Middleware\EnsureSetupComplete;
use App\Http\Middleware\EnsureSetupNotComplete;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    /*
     * La découverte automatique des listeners est active par défaut dans le
     * framework ; elle ne tournait pas ici uniquement parce que
     * App\Providers\EventServiceProvider en était une sous-classe. La laisser
     * s'allumer enregistrerait une seconde fois chaque listener déjà déclaré à
     * la main : un mail de bienvenue par inscription deviendrait deux.
     */
    ->withEvents(discover: false)
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        then: function (): void {
            // Le module bar est isolé : son propre fichier de routes, son propre
            // préfixe, et deux verrous qui n'existent nulle part ailleurs — le
            // feature flag du domaine et la permission d'accès.
            Route::middleware(['web', 'auth', 'feature:bar', 'can:bar.access'])
                ->prefix('bar')
                ->name('bar.')
                ->group(base_path('routes/bar.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
         * TrustHosts n'était pas activé par défaut : sans lui l'application
         * répond à n'importe quel en-tête Host, de quoi empoisonner les liens
         * des mails de réinitialisation. Le comportement par défaut du framework
         * — APP_URL et ses sous-domaines — est exactement ce que la sous-classe
         * maison faisait, et il reste inerte en `local` et sous les tests.
         *
         * TrustProxies, à l'inverse, n'est volontairement pas configuré : Apache
         * sert PHP en direct sur le VPS, sans reverse proxy ni CDN, donc
         * REMOTE_ADDR est déjà celle du visiteur. Faire confiance à `*` ici
         * laisserait n'importe qui forger X-Forwarded-For et passer devant tous
         * les throttles par IP. Le jour où un proxy est ajouté, c'est ici qu'il
         * faut revenir — et un test le rappelle.
         */
        $middleware->trustHosts(subdomains: true);

        /*
         * En tête de pile : SecurityHeaders ne lit pas la requête, il décore la
         * réponse. Plus il est haut, plus il en couvre — y compris la 503 du
         * mode maintenance et la 413 d'un POST trop gros, qui sortent avant
         * d'atteindre la moindre route.
         */
        $middleware->prepend(SecurityHeaders::class);

        $middleware->throttleApi();

        // EnsureSetupComplete envoie le premier visiteur vers /setup ;
        // EnsureProfileIsComplete réclame un profil utilisable avant le reste.
        $middleware->web(append: [
            EnsureSetupComplete::class,
            EnsureProfileIsComplete::class,
        ]);

        $middleware->alias([
            'committee' => CommitteeMemberMiddelware::class,
            'feature' => EnsureFeatureIsEnabled::class,
            'guest' => RedirectIfAuthenticated::class,
            'profile.complete' => EnsureProfileIsComplete::class,
            'setup.complete' => EnsureSetupComplete::class,
            'setup.not_complete' => EnsureSetupNotComplete::class,
        ]);

        /*
         * EnsureSetupComplete doit passer avant `auth`, sinon un visiteur non
         * authentifié qui arrive sur une installation vierge est renvoyé vers
         * /login — une page qui ne peut encore connecter personne — au lieu de
         * /setup. On l'insère dans la liste du framework plutôt que de la
         * réécrire : le reste de l'ordre ne nous regarde pas.
         */
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: EnsureSetupComplete::class,
        );

        // Le middleware `auth` du framework ne redirige nulle part tant qu'on ne
        // le lui dit pas. Les requêtes qui attendent du JSON reçoivent un 401
        // avant que ce chemin soit consulté.
        $middleware->redirectGuestsTo(fn (): string => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
         * Un lien d'invitation périmé est un cas ordinaire, pas une erreur : le
         * membre reçoit une page qui le lui dit et, s'il n'a jamais validé son
         * adresse, de quoi en redemander une. Ailleurs, une signature invalide
         * reste un 403 sec.
         */
        $exceptions->render(function (InvalidSignatureException $e, Request $request): ?Response {
            if (! $request->routeIs('invitation.accept', 'invitation.store')) {
                return null;
            }

            $routeUser = $request->route('user');
            $user = $routeUser instanceof User ? $routeUser : User::find($routeUser);

            return response()->view('clubAdmin.users.auth.invitation-expired', [
                'resendUser' => $user !== null && $user->email_verified_at === null ? $user : null,
            ], 403);
        });
    })->create();
