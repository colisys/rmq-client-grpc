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

use Closure;

class Set
{
    private $set = [];

    public function __construct(array|self|null $var = null)
    {
        if (isset($var)) {
            $this->addAll($var);
        }
    }

    public function __toString(): string
    {
        return json_encode($this->toArray());
    }

    public function add(string $key): void
    {
        $this->set[$key] = $key;
    }

    public function contains(string $key): bool
    {
        return isset($this->set[$key]);
    }

    public function size(): int
    {
        return count($this->set);
    }

    public function clear(): void
    {
        $this->set = [];
    }

    public function toArray(): array
    {
        return array_values($this->set);
    }

    public function isEmpty(): bool
    {
        return empty($this->set);
    }

    public function remove(string $key): void
    {
        unset($this->set[$key]);
    }

    public function containsAll(Set $set): bool
    {
        foreach ($set->toArray() as $key => $value) {
            if (! $this->contains($key)) {
                return false;
            }
        }

        return true;
    }

    public function addAll(array|Set $set): void
    {
        if ($set instanceof Set) {
            $set = $set->toArray();
        }
        foreach (array_values($set) as $key) {
            $this->add($key);
        }
    }

    public function removeAll(array|Set $set): void
    {
        if ($set instanceof Set) {
            $set = $set->toArray();
        }
        foreach (array_values($set) as $key) {
            $this->remove($key);
        }
    }

    public function retainAll(Set $set): void
    {
        foreach ($this->toArray() as $key => $value) {
            if (! $set->contains($key)) {
                $this->remove($key);
            }
        }
    }

    public function equals(Set $set): bool
    {
        return $this->toArray() === $set->toArray();
    }

    public function each(Closure $closure)
    {
        foreach ($this->toArray() as $value) {
            $closure($value);
        }
        return $this;
    }

    public function map(Closure $closure)
    {
        $obj = new static($this->set);
        $obj->each(function ($value) use ($closure, $obj) {
            $obj->add($value, $closure($value));
        });
        return $obj;
    }

    public function filter(Closure $closure)
    {
        return new static(array_filter($this->toArray(), $closure));
    }
}
