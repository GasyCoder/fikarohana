<?php

namespace App\Support;

use Illuminate\Support\Str;
use InvalidArgumentException;

class CatalogNormalizer
{
    public static function name(string $value): string
    {
        return Str::lower(preg_replace('/\s+/u', ' ', trim($value)));
    }

    public static function issn(string $value): string
    {
        $compact = strtoupper(str_replace(['-', ' '], '', trim($value)));
        if (! preg_match('/^[0-9]{7}[0-9X]$/', $compact)) {
            throw new InvalidArgumentException('Invalid ISSN format.');
        }
        $sum = 0;
        for ($i = 0; $i < 7; $i++) {
            $sum += (int) $compact[$i] * (8 - $i);
        }
        $check = (11 - ($sum % 11)) % 11;
        if (($check === 10 ? 'X' : (string) $check) !== $compact[7]) {
            throw new InvalidArgumentException('Invalid ISSN checksum.');
        }

        return substr($compact, 0, 4).'-'.substr($compact, 4);
    }

    public static function url(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (! mb_check_encoding($value, 'UTF-8') || preg_match('/[\x00-\x1f\x7f]/', $value)) {
            throw new InvalidArgumentException('Catalog URLs must contain valid text without controls.');
        }
        $value = trim($value);
        $parts = parse_url($value);
        $scheme = strtolower($parts['scheme'] ?? '');
        if (! filter_var($value, FILTER_VALIDATE_URL) || ! $parts
            || ! in_array($scheme, ['http', 'https'], true)
            || isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException('Catalog URLs must be HTTP(S) without credentials.');
        }

        // Preserve case-sensitive paths, queries and fragments; never fetch here.
        return $scheme.'://'.strtolower($parts['host'])
            .(isset($parts['port']) ? ':'.$parts['port'] : '')
            .($parts['path'] ?? '').(isset($parts['query']) ? '?'.$parts['query'] : '')
            .(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
    }
}
