<?php

declare(strict_types=1);

use App\Support\Diagnostics\PageExpiredReport;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/**
 * Une requête Livewire refusée, façonnée comme celle de l'issue #36.
 *
 * @param  array<string, mixed>  $payload
 */
function refusedLivewireRequest(array $payload = [], array $headers = []): Request
{
    $request = Request::create(
        '/livewire/update',
        'POST',
        [],
        [],
        [],
        collect($headers)->mapWithKeys(fn (string $v, string $k): array => ['HTTP_' . str_replace('-', '_', strtoupper($k)) => $v])->all()
            + ['HTTP_X_LIVEWIRE' => 'true'],
        json_encode($payload + [
            'components' => [[
                'snapshot' => ['memo' => ['name' => 'pages::club-admin.users.registrations']],
                'calls' => [['method' => 'sendPaymentEmail']],
            ]],
        ], JSON_THROW_ON_ERROR),
    );

    $request->headers->set('Content-Type', 'application/json');

    return $request;
}

/**
 * Attache une session à la requête, en la démarrant sur un vrai handler.
 *
 * `$stored` est ce que le serveur retrouve sous cet id : un tableau vide
 * reproduit une session expirée ou balayée. On passe par le handler plutôt que
 * de peupler le Store à la main, parce que c'est justement le chemin de lecture
 * qui est en cause — Store::readFromHandler() rend un tableau vide sans
 * toucher à l'id, si bien qu'une session morte porte l'id du navigateur.
 *
 * @param  array<string, mixed>  $stored
 */
function bindSession(Request $request, string $id, array $stored = []): Store
{
    $handler = new ArraySessionHandler(120);

    if ($stored !== []) {
        $handler->write($id, serialize($stored));
    }

    $session = new Store((string) config('session.cookie'), $handler, $id);
    $session->start();
    $request->setLaravelSession($session);

    return $session;
}

describe('empreintes d\'un 419', function (): void {
    it('distingue un cookie absent d\'un cookie illisible', function (): void {
        $cookie = (string) config('session.cookie');

        $absent = PageExpiredReport::context(refusedLivewireRequest());

        // EncryptCookies garde la clé et annule la valeur quand le déchiffrement
        // échoue : c'est la signature d'un APP_KEY tourné sous la page ouverte.
        $unreadable = refusedLivewireRequest();
        $unreadable->cookies->set($cookie, null);

        $present = refusedLivewireRequest();
        $present->cookies->set($cookie, str_repeat('d', 40));

        expect($absent['cookie'])->toBe('absent')
            ->and(PageExpiredReport::context($unreadable)['cookie'])->toBe('unreadable')
            ->and(PageExpiredReport::context($present)['cookie'])->toBe('present');
    });

    it('distingue une session vivante d\'une session que le serveur n\'a plus', function (): void {
        $cookie = (string) config('session.cookie');
        $id = str_repeat('a', 40);

        $alive = refusedLivewireRequest();
        $alive->cookies->set($cookie, $id);
        bindSession($alive, $id, ['login_web_abc' => 7]);

        // Le cœur du diagnostic : le cookie nomme une session, le serveur ne
        // lit rien sous cet id — expirée, balayée, ou sur un autre conteneur.
        // L'id, lui, est identique : le comparer dirait « vivante ».
        $expired = refusedLivewireRequest();
        $expired->cookies->set($cookie, $id);
        $swept = bindSession($expired, $id);

        $none = refusedLivewireRequest();

        expect($swept->getId())->toBe($id, 'une session balayée garde l\'id du cookie')
            ->and(PageExpiredReport::context($alive)['session'])->toBe('alive')
            ->and(PageExpiredReport::context($alive)['session_keys'])->toBe(1)
            ->and(PageExpiredReport::context($expired)['session'])->toBe('empty')
            ->and(PageExpiredReport::context($expired)['session_keys'])->toBe(0)
            ->and(PageExpiredReport::context($none)['session'])->toBe('none');
    });

    it('dit d\'où venait le jeton, ou qu\'il n\'y en avait aucun', function (): void {
        $field = refusedLivewireRequest(['_token' => 'abc']);
        $header = refusedLivewireRequest(headers: ['X-CSRF-TOKEN' => 'abc']);
        $none = refusedLivewireRequest();

        expect(PageExpiredReport::context($field)['token'])->toBe('field')
            ->and(PageExpiredReport::context($header)['token'])->toBe('header_csrf')
            ->and(PageExpiredReport::context($none)['token'])->toBe('none');
    });

    it('nomme l\'action Livewire refusée', function (): void {
        $context = PageExpiredReport::context(refusedLivewireRequest());

        expect($context['livewire_call'])
            ->toBe('pages::club-admin.users.registrations::sendPaymentEmail');
    });

    it('n\'écrit ni identifiant de session ni jeton en clair', function (): void {
        $cookie = (string) config('session.cookie');

        $request = refusedLivewireRequest(['_token' => 'the-secret-token']);
        $request->cookies->set($cookie, str_repeat('e', 40));
        bindSession($request, str_repeat('e', 40), ['login_web_abc' => 7]);

        $serialised = json_encode(PageExpiredReport::context($request), JSON_THROW_ON_ERROR);

        expect($serialised)->not->toContain('the-secret-token')
            ->and($serialised)->not->toContain(str_repeat('e', 40));
    });

    it('survit à une requête nue, sans session ni corps', function (): void {
        // Un diagnostic qui lèverait ici transformerait un 419 dont on se
        // relève en un 500 dont on ne se relève pas.
        $context = PageExpiredReport::context(Request::create('/', 'GET'));

        expect($context)->not->toHaveKey('diagnostic_failed')
            ->and($context['session'])->toBe('none');
    });
});

it('journalise le 419 et laisse la page habituelle se rendre', function (): void {
    Log::spy();

    Route::middleware('web')->get('/__page-expired-probe', function (): never {
        throw new TokenMismatchException('CSRF token mismatch.');
    });

    $this->get('/__page-expired-probe')->assertStatus(419);

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => $message === 'Page expired (419)'
            && $context['path'] === '__page-expired-probe'
            && array_key_exists('cookie', $context)
            && array_key_exists('session', $context));
});
