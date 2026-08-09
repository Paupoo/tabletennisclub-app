<?php

declare(strict_types=1);

namespace App\Domains\Shared\Casts;

use App\Domains\Shared\Support\IbanNormalizer;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * @implements CastsAttributes<?string, ?string>
 */
class IbanCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return IbanNormalizer::normalize($value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return IbanNormalizer::normalize($value);
    }
}
