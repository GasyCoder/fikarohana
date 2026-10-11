<?php

namespace App\Rules;

use App\Support\CatalogNormalizer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;

class Issn implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            if (! is_string($value)) {
                throw new InvalidArgumentException;
            }
            CatalogNormalizer::issn($value);
        } catch (InvalidArgumentException) {
            $fail('The :attribute must be an ISSN with a valid checksum.');
        }
    }
}
