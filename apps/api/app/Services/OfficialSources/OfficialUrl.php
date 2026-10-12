<?php

namespace App\Services\OfficialSources;

use DomainException;

/** Fetch authorization is deliberately stricter than catalog metadata URL syntax. */
class OfficialUrl
{
    public function normalize(string $url): string
    {
        if (strlen($url) > 2048 || ! mb_check_encoding($url, 'UTF-8')
            || preg_match('/[\x00-\x20\x7f\\\\]/', $url)) {
            throw new DomainException('official_invalid_url');
        }
        $p = parse_url($url);
        if (! $p || strtolower($p['scheme'] ?? '') !== 'https'
            || isset($p['user']) || isset($p['pass']) || isset($p['query']) || isset($p['fragment'])
            || (isset($p['port']) && $p['port'] !== 443)) {
            throw new DomainException('official_invalid_url');
        }
        $host = strtolower($p['host'] ?? '');
        // ASCII fully qualified domains only: no literals, shorthand IPs, local names or parser aliases.
        if (strlen($host) > 253 || ! preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/D', $host)
            || preg_match('/(?:^|\.)(localhost|local|internal|test|invalid|example)$/D', $host)) {
            throw new DomainException('official_invalid_host');
        }

        return 'https://'.$host.($p['path'] ?? '/');
    }

    public function origin(string $url): string
    {
        return 'https://'.parse_url($this->normalize($url), PHP_URL_HOST);
    }
}
