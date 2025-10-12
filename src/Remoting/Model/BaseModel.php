<?php

declare(strict_types=1);
/**
 * Third-party RocketMQ Client SDK for Hyperf
 *
 * @contact colisys@duck.com
 * @license MIT
 * @copyright 2025 Colisys
 */

namespace Colisys\Rocketmq\Remoting\Model;

use ReflectionClass;
use ReflectionProperty;

/**
 * @method static static parse($data)
 */
abstract class BaseModel
{
    public static function parse($data)
    {
        if (method_exists(static::class, 'decode')) {
            return static::{'decode'}($data);
        }
        if (is_string($data)) {
            $parsed = json_decode($data, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $data = $parsed;
            }
        }
        if (is_array($data)) {
            return self::decode($data);
        }
    }

    public static function parseFromArray(array $data)
    {
        return array_map(static::class . '::parse', $data);
    }

    private static function decode($data): static
    {
        $rclass = new ReflectionClass(static::class);
        // var_dump($rclass->getName());
        $instance = $rclass->newInstanceArgs($data);
        $properties = $rclass->getProperties(ReflectionProperty::IS_PRIVATE);
        foreach ($properties as $key => $property) {
            $property->getName();
            $value = $data[$key] ?? null;
            if (is_null($value)) {
                continue;
            }
        }
        return $instance;
    }
}
