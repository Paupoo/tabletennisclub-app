<?php

declare(strict_types=1);

pest()->group('components', 'statCard');

it('affiche le label, la valeur et la précision', function (): void {
    $this->blade('<x-admin.shared.stat-card label="Pending" value="1 250,00 €" hint="3 paiements en attente" icon="o-clock" />')
        ->assertSee('Pending')
        ->assertSee('1 250,00 €')
        ->assertSee('3 paiements en attente');
});

it('omet la précision quand elle n est pas fournie', function (): void {
    $this->blade('<x-admin.shared.stat-card label="Total" value="42" />')
        ->assertSee('Total')
        ->assertSee('42')
        ->assertDontSee('opacity-60', escape: false);
});

it('mappe la couleur sur la pastille d icône, pas sur le chiffre', function (string $color, string $chip, string $iconColor): void {
    $rendered = $this->blade(
        '<x-admin.shared.stat-card label="L" value="V" icon="o-clock" :color="$color" />',
        ['color' => $color]
    );

    $rendered->assertSee($chip, escape: false);
    $rendered->assertSee($iconColor, escape: false);
})->with([
    'success' => ['success', 'bg-success/10', 'text-success-content'],
    'warning' => ['warning', 'bg-warning/10', 'text-warning-content'],
    'error' => ['error', 'bg-error/10', 'text-error-content'],
    'primary' => ['primary', 'bg-primary/10', 'text-primary'],
    'neutral' => ['neutral', 'bg-base-200', 'text-base-content/60'],
]);

it('utilise les tokens -content pour l icône, lisibles sur surface claire', function (string $color, string $baseToken): void {
    // Les tokens de base sont trop clairs (L≈70-82 %) pour du contenu sur carte
    // blanche ; `app.css:105-110`. Les `-content` (L≈37-41 %) passent.
    $this->blade(
        '<x-admin.shared.stat-card label="L" value="V" icon="o-clock" :color="$color" />',
        ['color' => $color]
    )->assertDontSee('h-5 w-5 ' . $baseToken . '"', escape: false);
})->with([
    'success' => ['success', 'text-success'],
    'warning' => ['warning', 'text-warning'],
    'error' => ['error', 'text-error'],
]);

it('n applique jamais la couleur sémantique au chiffre', function (): void {
    // Une grille de chiffres colorés se lit mal : la pastille porte le sens.
    $this->blade('<x-admin.shared.stat-card label="Pending" value="1 250,00 €" icon="o-clock" color="warning" />')
        ->assertSee('font-black tabular-nums', escape: false)
        ->assertDontSee('font-black tabular-nums text-warning', escape: false);
});

it('n affiche aucune pastille sans icône', function (): void {
    $this->blade('<x-admin.shared.stat-card label="Total" value="42" color="success" />')
        ->assertDontSee('bg-success/10', escape: false);
});

it('agrandit le chiffre avec emphasis', function (): void {
    $this->blade('<x-admin.shared.stat-card label="Solde" value="120,00 €" emphasis />')
        ->assertSee('text-3xl', escape: false);

    $this->blade('<x-admin.shared.stat-card label="Solde" value="120,00 €" />')
        ->assertSee('text-2xl', escape: false);
});

it('reste purement informative : aucun filtrage au clic', function (): void {
    // Le filtrage passe par les onglets. Une carte cliquable sur trois est une
    // affordance indevinable, et doublonne l'onglet correspondant.
    $this->blade('<x-admin.shared.stat-card label="To refund" value="80,00 €" icon="o-arrow-uturn-left" color="error" />')
        ->assertDontSee('wire:click', escape: false)
        ->assertDontSee('cursor-pointer', escape: false);
});
