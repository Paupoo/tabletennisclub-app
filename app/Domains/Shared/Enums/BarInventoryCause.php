<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

/**
 * Ce qui s'est passé quand le comptage d'un inventaire ne tombe pas sur l'attendu.
 *
 * Une liste fixe, en langage courant : on la lit debout dans la réserve, et les
 * inventaires doivent rester comparables entre eux. « Ajouté au bar » n'est
 * jamais choisi : il marque le premier comptage d'un produit qui n'avait encore
 * jamais eu de stock.
 */
enum BarInventoryCause: string
{
    case AddedToBar = 'added_to_bar';
    case Broken = 'broken';
    case Expired = 'expired';
    case ReceivedOutsideShopping = 'received_outside_shopping';
    case Unknown = 'unknown';
    case UnrecordedSale = 'unrecorded_sale';

    /**
     * Ce qu'on peut choisir quand on compte moins que l'attendu.
     *
     * @return array<int, self>
     */
    public static function forShortage(): array
    {
        return [self::UnrecordedSale, self::Broken, self::Expired, self::Unknown];
    }

    /**
     * Ce qu'on peut choisir quand on compte plus que l'attendu.
     *
     * @return array<int, self>
     */
    public static function forSurplus(): array
    {
        return [self::ReceivedOutsideShopping];
    }

    /**
     * Si la cause peut expliquer un écart de ce sens-là.
     */
    public function fits(int $gap): bool
    {
        return match (true) {
            $gap < 0 => in_array($this, self::forShortage(), true),
            $gap > 0 => $this === self::AddedToBar || in_array($this, self::forSurplus(), true),
            default => false,
        };
    }

    /**
     * Si la perte est partie chez quelqu'un, et qu'il faudra donc la racheter.
     *
     * Ce qui a été bu sans passer en caisse est une vraie demande ; ce dont on ne
     * sait rien aussi, faute de mieux. La casse et le périmé n'en sont pas : les
     * compter ferait racheter davantage de ce qui périme déjà.
     */
    public function isDemand(): bool
    {
        return in_array($this, [self::UnrecordedSale, self::Unknown], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::AddedToBar => __('Added to the bar'),
            self::Broken => __('Broken'),
            self::Expired => __('Expired'),
            self::ReceivedOutsideShopping => __('Received outside the shopping'),
            self::UnrecordedSale => __('Drunk or eaten without going through the till'),
            self::Unknown => __('I don\'t know'),
        };
    }
}
