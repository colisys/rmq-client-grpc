<?php

declare(strict_types=1);
/**
 * Unofficial RocketMQ Client SDK for Hyperf
 *
 * @contact colisys@duck.com
 * @license Apache-2.0
 * @copyright 2025 Colisys
 */

namespace Colisys\RmqClient\Grpc\Contract;

use Colisys\RmqClient\Grpc\Impl\Consumer;
use Colisys\RmqClient\Grpc\Impl\Producer;
use Psr\Container\ContainerInterface;

class ClientContainer implements ContainerInterface
{
    /**
     * @var array<Consumer|Producer>
     */
    public static $clients = [];

    public function add($name, $client)
    {
        if (isset($name)) {
            self::$clients[$name] = $client;
        } else {
            self::$clients[] = $client;
        }
    }

    public function get(string $id)
    {
        return self::$clients[$id];
    }

    public function has(string $id): bool
    {
        return isset(self::$clients[$id]);
    }

    public function shutdown()
    {
        foreach (self::$clients as $client) {
            $client->shutdown();
        }
    }
}
