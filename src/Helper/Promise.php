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
use Fiber;
use Throwable;

final class Promise
{
    private ?Fiber $handler;

    private array $then = [];

    private array $catch = [];

    public function __construct(
        $callback = null
    ) {
        if (is_callable($callback)) {
            $this->then[] = $callback;
        }
        $this->handler = new Fiber(function () {
            $chain = 'then';
            $lastResult = null;
            $then = function ($data = null) use (&$lastResult) {
                $lastResult = $data;
            };
            $catch = function ($data = null) use (&$chain, &$lastResult) {
                $chain = 'catch';
                $lastResult = $data;
            };
            while (count($this->{$chain}) > 0) {
                try {
                    if ($chain == 'then') {
                        array_shift($this->{$chain})($lastResult, $resolve = $then, $reject = $catch);
                    }
                    if ($chain == 'catch') {
                        array_shift($this->{$chain})($lastResult);
                    }
                } catch (Throwable $th) {
                    if ($chain == 'catch') {
                        throw $th;
                    }
                    $chain = 'catch';
                }
            }
            return $lastResult;
        });
    }

    /**
     * @param Closure(mixed $data, Closure $resolve, Closure $reject): void $callback
     */
    public function then(callable $callback): self
    {
        $this->then[] = $callback;
        return $this;
    }

    /**
     * @param Closure(mixed $data): void $callback
     */
    public function catch(callable $callback): self
    {
        $this->catch[] = $callback;
        return $this;
    }

    public function start()
    {
        $this->handler->start();
    }

    public function wait(): mixed
    {
        $this->handler->start();
        return $this->handler->getReturn();
    }
}
