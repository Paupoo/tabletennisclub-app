<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Contact\Models\Spam;
use App\Http\Middleware\ProtectAgainstSpam;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/*
|--------------------------------------------------------------------------
| Spam protection on the public contact form
|--------------------------------------------------------------------------
|
| The middleware returns early in the `testing` environment, so the whole
| protection — the only thing between the public form and a bot — was never
| exercised by anything. These tests drive `handle()` with the environment
| forced away from `testing`, which is the sole reason for the detectEnvironment
| calls below.
|
| The short-circuit stays: the feature tests that post to the form do not carry
| a honeypot or a form_start, and making them all do so would test the fixture
| rather than the form.
|
*/

/**
 * Run the middleware as it behaves outside the test environment.
 *
 * @param  array<string, mixed>  $input
 * @return array{response: Response, reached: bool}
 */
function runSpamGuard(array $input): array
{
    app()->detectEnvironment(fn (): string => 'production');

    $request = Request::create('/contact', 'POST', $input);
    $request->setLaravelSession(app('session.store'));

    $reached = false;

    $response = (new ProtectAgainstSpam)->handle($request, function () use (&$reached): Response {
        $reached = true;

        return new Response('passed through');
    });

    return ['response' => $response, 'reached' => $reached];
}

/** A submission a human could plausibly have made: filled slowly, honeypot untouched. */
function humanSubmission(): array
{
    return ['form_start' => time() - 30, 'message' => 'Bonjour'];
}

it('lets a human submission through', function (): void {
    $result = runSpamGuard(humanSubmission());

    expect($result['reached'])->toBeTrue();
    expect(Spam::count())->toBe(0);
})->group('security');

it('blocks a submission that filled the honeypot', function (): void {
    $result = runSpamGuard([...humanSubmission(), 'website' => 'https://buy-cheap.example']);

    expect($result['reached'])->toBeFalse();
    expect(Spam::count())->toBe(1);
})->group('security');

it('blocks a form filled faster than any human could', function (): void {
    $result = runSpamGuard(['form_start' => time(), 'message' => 'Bonjour']);

    expect($result['reached'])->toBeFalse();
    expect(Spam::count())->toBe(1);
})->group('security');

it('blocks a submission that never loaded the form', function (): void {
    $result = runSpamGuard(['message' => 'Bonjour']);

    expect($result['reached'])->toBeFalse();
    expect(Spam::count())->toBe(1);
})->group('security');

it('tells the bot nothing, answering as if the message went through', function (): void {
    $result = runSpamGuard([...humanSubmission(), 'website' => 'spam']);

    expect($result['response']->getStatusCode())->toBe(302);
    expect(session('success'))->not->toBeNull();
})->group('security');

it('records what the blocked request looked like, without any password', function (): void {
    runSpamGuard([...humanSubmission(), 'website' => 'spam', 'password' => 'hunter2']);

    $spam = Spam::sole();

    expect($spam->inputs)->toHaveKey('website')
        ->and($spam->inputs)->not->toHaveKey('password');
})->group('security');
