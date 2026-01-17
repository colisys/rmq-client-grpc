<?php

declare(strict_types=1);
/**
 * Unofficial RocketMQ Client SDK for Hyperf
 *
 * @contact colisys@duck.com
 * @license Apache-2.0
 * @copyright 2025 Colisys
 */

namespace Colisys\RmqClient\Shared\Helper;

use Countable;
use Exception;

class Assert
{
    public static function fine($value, $message = '')
    {
        if (! $value) {
            throw new Exception($message);
        }
    }

    public static function not($value, $message = '')
    {
        if ($value) {
            throw new Exception($message);
        }
    }

    public static function true($value, $message = '')
    {
        if ($value !== true) {
            throw new Exception($message);
        }
    }

    public static function false($value, $message = '')
    {
        if ($value !== false) {
            throw new Exception($message);
        }
    }

    public static function null($value, $message = '')
    {
        if ($value !== null) {
            throw new Exception($message);
        }
    }

    public static function notNull($value, $message = '')
    {
        if ($value === null) {
            throw new Exception($message);
        }
    }

    public static function empty($value, $message = '')
    {
        if (! empty($value)) {
            throw new Exception($message);
        }
    }

    public static function notEmpty($value, $message = '')
    {
        if (empty($value)) {
            throw new Exception($message);
        }
    }

    public static function positive(float|int $value, $message = '')
    {
        if ($value <= 0) {
            throw new Exception($message);
        }
    }

    public static function greater(float|int $value, float|int $than, $message = '')
    {
        if ($value <= $than) {
            throw new Exception($message);
        }
    }

    public static function less(float|int $value, float|int $than, $message = '')
    {
        if ($value < $than) {
            throw new Exception($message);
        }
    }

    public static function zero(float|int $value, $message = '')
    {
        if ($value !== 0) {
            throw new Exception($message);
        }
    }

    public static function notZero(float|int $value, $message = '')
    {
        if ($value === 0) {
            throw new Exception($message);
        }
    }

    public static function negative(float|int $value, $message = '')
    {
        if ($value >= 0) {
            throw new Exception($message);
        }
    }

    public static function instance($value, $class, $message = '')
    {
        if (! $value instanceof $class) {
            throw new Exception($message);
        }
    }

    public static function notInstance($value, $class, $message = '')
    {
        if ($value instanceof $class) {
            throw new Exception($message);
        }
    }

    public static function length(array|Countable $countable, int $length, $message = '')
    {
        if (count($countable) !== $length) {
            throw new Exception($message);
        }
    }
}
