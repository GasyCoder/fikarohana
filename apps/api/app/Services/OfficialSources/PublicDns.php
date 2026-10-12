<?php

namespace App\Services\OfficialSources;

use DomainException;
use Illuminate\Support\Facades\Process;
use Throwable;

class PublicDns
{
    /** All returned A/AAAA answers must be public; mixed public/private answers fail closed. */
    public function resolve(string $host): array
    {
        // Bound the system resolver itself, not just the later HTTP connection.
        // Laravel's existing Process/Symfony dependency runs argv directly, without a shell.
        (new OfficialUrl)->normalize('https://'.$host.'/');
        $program = <<<'PHP'
$r = @dns_get_record($argv[1], DNS_A | DNS_AAAA);
$ips = [];
foreach (is_array($r) ? $r : [] as $entry) {
    if (isset($entry['ip'])) $ips[] = $entry['ip'];
    if (isset($entry['ipv6'])) $ips[] = $entry['ipv6'];
}
echo json_encode(count($ips) <= 32 ? $ips : []);
PHP;
        try {
            $result = Process::timeout(5)->run([PHP_BINARY, '-r', $program, $host]);
            if (! $result->successful() || strlen($result->output()) > 8192) {
                throw new DomainException('official_dns_failed');
            }
            $ips = json_decode($result->output(), true, 4, JSON_THROW_ON_ERROR);
            if (! is_array($ips) || ! array_is_list($ips)) {
                throw new DomainException('official_dns_failed');
            }
        } catch (Throwable) {
            throw new DomainException('official_dns_failed');
        }

        return $this->validate($ips);
    }

    public function validate(array $ips): array
    {
        if ($ips === [] || count($ips) > 32) {
            throw new DomainException('official_dns_failed');
        }
        foreach ($ips as $ip) {
            if (! is_string($ip) || ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new DomainException('official_prohibited_address');
            }
            if (str_contains($ip, ':')) {
                // Only global unicast; additionally exclude special-use, tunnelling and documentation space.
                if (! $this->inRange($ip, '2000::', 3)) {
                    throw new DomainException('official_prohibited_address');
                }
                $ranges = [['2001::', 23], ['2001:db8::', 32], ['2002::', 16], ['3fff::', 20]];
            } else {
                $ranges = [['0.0.0.0', 8], ['100.64.0.0', 10], ['127.0.0.0', 8], ['169.254.0.0', 16],
                    ['192.0.0.0', 24], ['192.0.2.0', 24], ['192.88.99.0', 24], ['198.18.0.0', 15],
                    ['198.51.100.0', 24], ['203.0.113.0', 24], ['224.0.0.0', 3]];
            }
            foreach ($ranges as [$network, $bits]) {
                if ($this->inRange($ip, $network, $bits)) {
                    throw new DomainException('official_prohibited_address');
                }
            }
        }

        return array_values(array_unique($ips));
    }

    private function inRange(string $ip, string $network, int $bits): bool
    {
        $address = inet_pton($ip);
        $base = inet_pton($network);
        $bytes = intdiv($bits, 8);
        $remainder = $bits % 8;

        return substr($address, 0, $bytes) === substr($base, 0, $bytes)
            && ($remainder === 0 || (ord($address[$bytes]) & (255 << (8 - $remainder))) === (ord($base[$bytes]) & (255 << (8 - $remainder))));
    }
}
