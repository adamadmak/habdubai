<?php

namespace App\Support;

/**
 * Whether an address is on the public internet. This API sits next to its
 * private database, and some of what it fetches comes from outside (a job
 * source someone typed, a link inside a listing), so a fetch never goes to a
 * private, loopback or link-local address, or to a name inside the cluster.
 */
final class PublicAddress
{
    /** @var (callable(string): array<int, string>)|null Stands in for DNS under test. */
    public static $resolver = null;

    public static function allows(string $url): bool
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return false;
        }

        foreach (['localhost', '.localhost', '.local', '.internal', '.cluster.local', '.svc'] as $private) {
            if ($host === ltrim($private, '.') || str_ends_with($host, $private)) {
                return false;
            }
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : self::resolve($host);

        return $addresses !== [] && collect($addresses)->every(fn (string $address): bool => self::isPublic($address));
    }

    /** @return array<int, string> */
    private static function resolve(string $host): array
    {
        if (self::$resolver !== null) {
            return (self::$resolver)($host);
        }

        $v6 = @dns_get_record($host, DNS_AAAA) ?: [];

        return array_merge(gethostbynamel($host) ?: [], array_values(array_filter(array_column($v6, 'ipv6'))));
    }

    private static function isPublic(string $address): bool
    {
        // An IPv4 address written as IPv6 is that IPv4 address.
        if (preg_match('/^::ffff:(\d+\.\d+\.\d+\.\d+)$/i', $address, $mapped)) {
            $address = $mapped[1];
        }

        if (! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }

        // Carrier-grade NAT, which PHP's flags let through and clusters use.
        return ! (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && (ip2long($address) & 0xFFC00000) === (ip2long('100.64.0.0') & 0xFFC00000));
    }
}
