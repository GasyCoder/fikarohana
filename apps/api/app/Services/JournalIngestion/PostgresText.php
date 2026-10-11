<?php

namespace App\Services\JournalIngestion;

use DomainException;

class PostgresText
{
    /** Reject incompatible strings, including object/array keys, without rewriting input. */
    public static function validate(mixed $value): void
    {
        if (is_string($value)) {
            if (str_contains($value, "\0") || ! mb_check_encoding($value, 'UTF-8')) {
                throw new DomainException('postgres_incompatible_text');
            }
        } elseif (is_array($value) || is_object($value)) {
            foreach ($value as $key => $entry) {
                self::validate($key);
                self::validate($entry);
            }
        }
    }
}
