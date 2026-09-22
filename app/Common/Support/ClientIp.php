<?php

declare(strict_types=1);

namespace App\Common\Support;

final class ClientIp
{
    /** @param null|list<string> $trustedProxies */
    public static function resolve(string $peer, string $forwarded = '', ?array $trustedProxies = null): string
    {
        $trustedProxies ??= (array) config('abuse.trusted_proxies', []);
        $current = self::normalize($peer) ?? '0.0.0.0';
        foreach (array_reverse(explode(',', $forwarded)) as $hop) {
            if (!self::trusted($current, $trustedProxies)) {
                break;
            }
            $next = self::normalize(trim($hop));
            if ($next === null) {
                break;
            }
            $current = $next;
        }

        return $current;
    }

    private static function normalize(string $ip): ?string
    {
        $packed = @inet_pton($ip);
        if ($packed === false) {
            return null;
        }
        if (strlen($packed) === 16 && substr($packed, 0, 12) === str_repeat("\0", 10) . "\xff\xff") {
            $packed = substr($packed, 12);
        }

        return inet_ntop($packed) ?: null;
    }

    /** @param list<string> $networks */
    private static function trusted(string $ip, array $networks): bool
    {
        $address = inet_pton($ip);
        foreach ($networks as $network) {
            [$base, $bits] = array_pad(explode('/', $network, 2), 2, null);
            $packed = @inet_pton($base);
            if ($packed === false || $address === false || strlen($packed) !== strlen($address)) {
                continue;
            }
            $prefix = $bits === null ? strlen($packed) * 8 : (ctype_digit($bits) ? (int) $bits : -1);
            if ($prefix < 0 || $prefix > strlen($packed) * 8) {
                continue;
            }
            $bytes = intdiv($prefix, 8);
            $remainder = $prefix % 8;
            if (substr($packed, 0, $bytes) === substr($address, 0, $bytes)
                && ($remainder === 0 || ((ord($packed[$bytes]) ^ ord($address[$bytes])) >> (8 - $remainder)) === 0)) {
                return true;
            }
        }

        return false;
    }
}
