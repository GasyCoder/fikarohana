<?php

namespace App\Services\JournalIngestion;

class CanonicalJson
{
    public static function encode(mixed $value): string
    {
        if (is_array($value)) {
            if (! array_is_list($value)) {
                ksort($value);
            }
            foreach ($value as &$entry) {
                $entry = json_decode(self::encode($entry), true, 32, JSON_THROW_ON_ERROR);
            }
            unset($entry);
        }

        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public static function hash(mixed $value): string
    {
        return hash('sha256', self::encode($value));
    }
}
