<?php

declare(strict_types=1);

use App\Actions\Payments\GeneratePayment;
use App\Models\Payment;
use App\Models\Subscription;

// Méthode 1 : Ajouter le groupe directement dans le fichier
uses()->group('payment');

// Test basique : vérifier qu'un paiement est bien créé
it('creates a payment for a subscription', function () {
    // Arrange : préparer les données
    $subscription = Subscription::factory()->create();
    $action = new GeneratePayment;

    // Act : exécuter l'action
    $action($subscription);

    // Assert : vérifier les résultats
    expect($subscription->payments()->count())->toBe(1);
});

// Test : vérifier les attributs du paiement créé
it('creates a payment with correct attributes', function () {
    $subscription = Subscription::factory()->create([
        'amount_due' => 5000, // en centimes par exemple
    ]);
    $action = new GeneratePayment;

    $action($subscription);

    $payment = $subscription->payments()->first();

    expect($payment)
        ->reference->not->toBeNull()
        ->amount_due->toBe(5000)
        ->amount_paid->toBe(0)
        ->status->toBe('pending');
});

// Test : vérifier que la référence est unique
it('generates unique payment references', function () {
    $subscription = Subscription::factory()->create();
    $action = new GeneratePayment;

    // Créer deux paiements
    $action($subscription);
    $action($subscription);

    $references = $subscription->payments()->pluck('reference')->toArray();

    // Les deux références doivent être différentes
    expect($references)->toHaveCount(2)
        ->and($references[0])->not->toBe($references[1]);
});

// Test : vérifier la redirection avec message de succès
it('redirects back with success message', function () {
    $subscription = Subscription::factory()->create();
    $action = new GeneratePayment;

    $response = $action($subscription);

    expect($response)
        ->toBeInstanceOf(\Illuminate\Http\RedirectResponse::class);

    // Vérifier que le message de succès est présent dans la session
    expect(session('success'))
        ->toBe(__('A new payment has been generated'));
});

// Test : vérifier que getAmountDue() est bien utilisé
it('uses subscription amount_due from getAmountDue method', function () {
    $subscription = Subscription::factory()->create();

    // Mock ou spy sur la méthode getAmountDue si nécessaire
    $expectedAmount = 7500;
    $subscription->shouldReceive('getAmountDue')
        ->once()
        ->andReturn($expectedAmount);

    $action = new GeneratePayment;
    $action($subscription);

    $payment = Payment::latest()->first();
    expect($payment->amount_due)->toBe($expectedAmount);
});

// Test : vérifier le comportement avec plusieurs paiements
it('can generate multiple payments for the same subscription', function () {
    $subscription = Subscription::factory()->create();
    $action = new GeneratePayment;

    $action($subscription);
    $action($subscription);
    $action($subscription);

    expect($subscription->payments()->count())->toBe(3);

    // Tous les paiements doivent être 'pending'
    $allPending = $subscription->payments()
        ->where('status', 'pending')
        ->count();

    expect($allPending)->toBe(3);
});
