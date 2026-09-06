<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use ReflectionNamedType;
use ReflectionProperty;

/**
 * Survives an emptied number input on a component that types its properties.
 *
 * Clearing `<input type="number" wire:model.live>` sends `""`. Livewire cannot
 * write that into a `public int $x`, and deliberately falls back to
 * `unset($component->$property)` — its comment says "making it null", which is
 * true of an untyped property and false of a typed one: the property becomes
 * *uninitialised*, which as far as PHP is concerned means it is not there at
 * all. The `updatedX()` hook that fires straight after reads it, falls through
 * to Livewire's `__get`, and the page dies on
 * `Property [$x] not found on component`.
 *
 * The reader did nothing unusual: selecting "4" and typing "5" passes through
 * an empty field, and with a debounce the empty state is what gets sent.
 *
 * An empty numeric field is a moment in typing, not a value. So it is ignored:
 * the property keeps what it had, and the next keystroke replaces it. Nothing
 * is coerced to a number of our own choosing — snapping the field to 0 or 1
 * mid-word would be its own small lie, and a loud one here, since the
 * tournament cap follows the pool structure.
 *
 * Only non-nullable int and float properties are concerned. A `?int` takes the
 * blank as null and a string keeps it verbatim; both are legitimate answers and
 * are left alone.
 *
 * The value has to be caught on the way in and put back on the way out, rather
 * than simply corrected: Livewire hands the incoming value to the `updating`
 * hooks **by value**, so amending it there changes nothing. `updating` runs
 * before the assignment — the last moment the old value is readable — and the
 * generic `updated` hook runs after it but before `updatedX()`, which is the
 * one that would crash.
 */
trait KeepsNumericPropertiesTyped
{
    /**
     * Values seen just before a blank overwrote them, keyed by property.
     *
     * Private, so Livewire never serialises it, and it never needs to survive
     * beyond the request that fills it.
     *
     * @var array<string, int|float>
     */
    private array $numericValueBeforeBlank = [];

    /** Runs after the assignment, and before the property's own `updatedX()`. */
    public function updated(string $name, mixed $value): void
    {
        if (! array_key_exists($name, $this->numericValueBeforeBlank)) {
            return;
        }

        $previous = $this->numericValueBeforeBlank[$name];
        unset($this->numericValueBeforeBlank[$name]);

        // Asked through reflection rather than isset(): an uninitialised typed
        // property routes isset() to __isset(), and the answer would be
        // Livewire's rather than PHP's.
        if (! new ReflectionProperty($this, $name)->isInitialized($this)) {
            $this->{$name} = $previous;
        }
    }

    /** Runs before the assignment, the last moment the old value is readable. */
    public function updating(string $name, mixed $value): void
    {
        // Les deux formes du vide, exactement celles que Livewire lui-même
        // traite ainsi : le champ effacé arrive tantôt en chaîne vide, tantôt
        // déjà converti en null. Sur une propriété non-nullable, ni l'une ni
        // l'autre n'est une valeur que quelqu'un a voulu écrire.
        if (($value !== '' && $value !== null) || ! $this->isNonNullableNumber($name)) {
            return;
        }

        $this->numericValueBeforeBlank[$name] = $this->{$name};
    }

    /**
     * Whether the component declares this property as a plain int or float.
     *
     * A dotted path names an array entry, not a property, and `property_exists`
     * answers false for it — which is the right answer here.
     */
    private function isNonNullableNumber(string $name): bool
    {
        if (! property_exists($this, $name)) {
            return false;
        }

        $type = new ReflectionProperty($this, $name)->getType();

        return $type instanceof ReflectionNamedType
            && ! $type->allowsNull()
            && in_array($type->getName(), ['int', 'float'], true);
    }
}
