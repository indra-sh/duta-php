<?php

declare(strict_types=1);

namespace Duta\Resources;

/** @internal */
final class Keys
{
    /** A fresh idempotency key, so the SDK's own retries of one call can never send twice. */
    public static function idempotency(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return 'duta-php-' . vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }
}
