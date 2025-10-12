<?php

declare(strict_types=1);
/**
 * Third-party RocketMQ Client SDK for Hyperf
 *
 * @contact colisys@duck.com
 * @license MIT
 * @copyright 2025 Colisys
 */

namespace Colisys\Rocketmq\Helper;

use Closure;
use Google\Protobuf\Internal\RepeatedField;
use stdClass;

/**
 * @template T
 */
class Arr
{
    /**
     * @var array<int|string, T>
     */
    public array $origin = [];

    /**
     * @template T
     * @param array $origin The origin array
     * @param int $capacity `-1` for unlimited
     * @param class-string<T> $className
     * @return Arr<T>
     */
    public function __construct(
        array $origin = [],
        public readonly int $capacity = -1,
        private string $className = stdClass::class,
    ) {
        $this->origin = $origin;
        $this->chunk();
    }

    public function __destruct()
    {
        if ($this->className != stdClass::class) {
            foreach ($this->origin as $key => $value) {
                unset($this->origin[$key]);
            }
        }
        unset($this->origin);
    }

    public function count(): int
    {
        return count($this->origin);
    }

    /**
     * @template T
     * @param class-string<T> $className
     * @return Arr<T>
     */
    public static function fromArray(
        array $origin,
        string $className = stdClass::class,
        int $capacity = -1,
    ): self {
        $obj = new self($origin, $capacity, $className);
        return $obj->chunk();
    }

    /**
     * @template T
     *
     * @param class-string<T> $className
     * @return Arr<T>
     */
    public static function fromRepeatField(
        RepeatedField $field,
        string $className = stdClass::class,
        int $capacity = -1
    ): self {
        $total = $field->count();
        $obj = new static([], $capacity, $className);
        while ($total > 0) {
            $obj->set(null, $field->offsetGet(--$total));
        }
        return $obj->chunk();
    }

    /**
     * @template T
     * @template U
     * @param U $default
     * @return T|U
     */
    public function get(int|string $key, $default = null)
    {
        return $this->origin[$key] ?? $default;
    }

    public function has(int|string $key): bool
    {
        return isset($this->origin[$key]);
    }

    /**
     * @template T
     * @param mixed $value
     * @return Arr<T>
     */
    public function set(int|string|null $key, $value)
    {
        if (isset($key)) {
            $this->origin[$key] = $value;
        } else {
            $this->origin[] = $value;
        }
        return $this->chunk();
    }

    /**
     * @template T
     * @template TReturn
     *
     * @param null|callable(T $item): TReturn $callback
     * @return Arr<TReturn>
     */
    public function map(callable $callback): self
    {
        $arr = new self($this->origin);
        $arr->origin = array_map($callback, $arr->origin);
        return $arr->chunk();
    }

    /**
     * @param Closure(T $value, int|string $key): void $callback
     * @return Arr<T>
     */
    public function each(callable $callback): self
    {
        foreach ($this->origin as $key => $value) {
            $callback($value, $key);
        }
        return $this;
    }

    /**
     * @template T
     * @param null|callable(TValue $value, TKey $key): bool $callback
     * @return Arr<T>
     */
    public function filter(callable $callback): self
    {
        $arr = new self($this->origin, $this->capacity, $this->className);
        $arr->origin = array_filter($arr->origin, $callback, ARRAY_FILTER_USE_BOTH);
        return $arr->chunk();
    }

    /**
     * @template T
     * @param Arr<T> $arr
     */
    public function append(array|self $arr): self
    {
        $this->origin = array_merge(
            $this->origin,
            match (is_array($arr)) {
                true => $arr,
                default => $arr->toArray(),
            }
        );
        return $this->chunk();
    }

    /**
     * @template T
     * @param Arr<T> $arr
     */
    public function extend(array|self $arr, int $capacity = -1): self
    {
        $origin = array_merge(
            $this->origin,
            match (is_array($arr)) {
                true => $arr,
                default => $arr->toArray(),
            }
        );
        return new self($origin, $capacity, $this->className);
    }

    public function pick(int|string ...$keys): self
    {
        $this->origin = array_intersect_key($this->origin, array_flip($keys));
        return $this;
    }

    /**
     * @return T
     */
    public function random()
    {
        return $this->get(array_rand($this->origin, 1));
    }

    /**
     * @return ?T
     */
    public function first()
    {
        $keys = array_keys($this->origin);
        if (count($keys) == 0) {
            return null;
        }
        return $this->get(array_shift($keys));
    }

    /**
     * @return ?T
     */
    public function last()
    {
        $keys = array_keys($this->origin);
        if (count($keys) == 0) {
            return null;
        }
        return $this->get(array_pop($keys));
    }

    /**
     * @return T[]
     */
    public function toArray(): array
    {
        return $this->origin;
    }

    private function chunk(): self
    {
        if ($this->capacity != -1 && count($this->origin) > $this->capacity) {
            $this->origin = array_shift(array_chunk($this->origin, $this->capacity, true));
        }
        if ($this->className != stdClass::class) {
            $this->origin = array_filter($this->origin, fn ($v) => $this->isSameType($v));
        }
        return $this;
    }

    private function isSameType($obj)
    {
        return match ($this->className) {
            'array' => is_array($obj),
            'string', 'int', 'float', 'bool', 'double', 'null', 'resource' => "is_{$this->className}"($obj),
            stdClass::class => true,
            default => is_a($obj, $this->className, true),
        };
    }
}
