<?php

declare(strict_types=1);
/**
 * Third-party RocketMQ Client SDK for Hyperf
 *
 * @contact colisys@duck.com
 * @license MIT
 * @copyright 2025 Colisys
 */

namespace Colisys\Rocketmq\Remoting;

use Closure;
use Colisys\Rocketmq\Contract\Result;
use Exception;
use Swoole\Coroutine\Client;

class CoroutineClient
{
    private ?Client $client;

    private bool $nesting = false;

    public function __construct(
        private string $host,
        private int $port,
        private int $timeout = 0,
        private int $flag = 0
    ) {
        $this->client = new Client(SOCK_STREAM);
    }

    public function send(string $data, int $timeout = 0): Result
    {
        if (! $this->getActiveConnection()) {
            return Result::Err(new Exception("Failed to connect to {$this->host}:{$this->port}"));
        }

        if ($this->client->send($data) === false) {
            return Result::Err(new Exception("Failed to receive data from {$this->host}:{$this->port}"));
        }

        return Result::Ok();
    }

    public function recv(int $timeout = 0): Result
    {
        if (! $this->getActiveConnection()) {
            return Result::Err(new Exception("Failed to connect to {$this->host}:{$this->port}"));
        }

        $result = $this->client->recv($timeout);

        if ($result === false) {
            return Result::Err(new Exception("Failed to receive data from {$this->host}:{$this->port}"));
        }

        return Result::Ok($result);
    }

    /**
     * @template T
     * @param Closure(static $client): T $callback
     */
    public function use(Closure $callback)
    {
        if (! $this->getActiveConnection()) {
            throw new Exception("Failed to connect to {$this->host}:{$this->port}");
        }
        $this->nesting = true;
        $result = $callback($this);
        $this->nesting = false;
        $this->client->close();
        return $result;
    }

    private function getActiveConnection(): bool
    {
        if (! isset($this->client)) {
            return false;
        }

        if ($this->client?->isConnected()) {
            return true;
        }

        $this->client->close();
        return $this->client->connect($this->host, $this->port, $this->timeout, $this->flag);
    }
}
