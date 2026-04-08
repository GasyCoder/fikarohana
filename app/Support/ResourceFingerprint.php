<?php

namespace App\Support;

use Illuminate\Support\Str;

class ResourceFingerprint
{
    public static function make(string $title, string $author): string
    {
        return sha1(
            Str::lower(Str::squish($title)).'|'.Str::lower(Str::squish($author))
        );
    }
}
