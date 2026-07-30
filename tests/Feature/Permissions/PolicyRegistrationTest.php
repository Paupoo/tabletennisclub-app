<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;

/*
| Policies live in two namespaces here — App\Policies and App\Domains\*\Policies
| — and none were declared, so every authorization decision relied on Gate's
| namespace-walking fallback. It happened to resolve, but dropping a Policies\
| namespace anywhere between a model and App\Policies would have silently
| hijacked it. They are declared now; this asserts the registration itself,
| through Gate rather than the provider, so it proves the outcome.
*/

it('registers policies explicitly rather than relying on name resolution', function (): void {
    expect(Gate::policies())->not->toBe([]);
});

it('resolves every registered model to its declared policy', function (): void {
    foreach (Gate::policies() as $model => $policy) {
        expect(class_exists($model))->toBeTrue("Modèle introuvable : {$model}")
            ->and(Gate::getPolicyFor($model))->toBeInstanceOf($policy);
    }
});

it('registers every policy that exists on disk', function (): void {
    $onDisk = collect(glob(app_path('Policies/*.php')))
        ->merge(glob(app_path('Domains/*/Policies/*.php')))
        ->merge(glob(app_path('Domains/*/*/Policies/*.php')))
        ->map(fn (string $path): string => basename($path, '.php'))
        ->sort()
        ->values()
        ->all();

    $registered = collect(Gate::policies())
        ->map(fn (string $policy): string => class_basename($policy))
        ->sort()
        ->values()
        ->all();

    expect($registered)->toBe($onDisk);
});
