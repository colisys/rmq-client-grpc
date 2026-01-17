<?php

declare(strict_types=1);
/**
 * Unofficial RocketMQ Client SDK for Hyperf
 *
 * @contact colisys@duck.com
 * @license Apache-2.0
 * @copyright 2025 Colisys
 */

namespace Colisys\Rocketmq\Helper;

/**
 * @internal
 */
class Signature
{
    public static function sign(string $secretKey, string $stringToSign): string
    {
        return hash_hmac('sha1', $stringToSign, $secretKey, true);
    }

    public static function verify(string $secretKey, string $stringToSign, string $signature): bool
    {
        return hash_equals(self::sign($secretKey, $stringToSign), $signature);
    }
}
