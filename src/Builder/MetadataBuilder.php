<?php

declare(strict_types=1);
/**
 * Third-party RocketMQ Client SDK for Hyperf
 *
 * @contact colisys@duck.com
 * @license MIT
 * @copyright 2025 Colisys
 */

namespace Colisys\Rocketmq\Builder;

class MetadataBuilder
{
    private static array $metadata = [];

    public function add(string $key, string $value): self
    {
        self::$metadata[$key] = $value;
        return $this;
    }

    public function get(string $key): ?string
    {
        return self::$metadata[$key] ?? null;
    }

    public function remove(string $key): self
    {
        unset(self::$metadata[$key]);
        return $this;
    }

    public function clear(): self
    {
        self::$metadata = [];
        return $this;
    }

    public function build(): array
    {
        $meta = [];
        foreach (self::$metadata as $key => $value) {
            $meta[$key] = strval($value);
        }
        return $meta;
    }
}
