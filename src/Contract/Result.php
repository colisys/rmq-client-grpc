<?php

declare(strict_types=1);
/**
 * Third-party RocketMQ Client SDK for Hyperf
 *
 * @contact colisys@duck.com
 * @license MIT
 * @copyright 2025 Colisys
 */

namespace Colisys\Rocketmq\Contract;

/**
 * @template C
 */
class Wrapper
{
    /**
     * @param C $value
     */
    public function __construct(public $value)
    {
    }
}

/**
 * @template T
 * @template E
 */
class Result
{
    /**
     * @param Wrapper<?T> $result
     * @param Wrapper<?E> $error
     */
    public function __construct(
        private $result,
        private $error
    ) {
    }

    /**
     * @var T
     * @param mixed $value
     */
    public static function Ok($value = null): static
    {
        return new static(new Wrapper($value), null);
    }

    /**
     * @var E
     * @param mixed $error
     */
    public static function Err($error = null): static
    {
        return new static(null, new Wrapper($error));
    }

    public function isOk(): bool
    {
        return $this->error === null;
    }

    public function isErr(): bool
    {
        return $this->error !== null;
    }

    /**
     * @return T
     */
    public function getResult(): mixed
    {
        return $this->result->value;
    }

    /**
     * @return E
     */
    public function getError(): mixed
    {
        return $this->error->value;
    }
}
