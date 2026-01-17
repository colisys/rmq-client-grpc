<?php

declare(strict_types=1);
/**
 * Third-party RocketMQ Client SDK for Hyperf
 *
 * @contact colisys@duck.com
 * @license MIT
 * @copyright 2025 Colisys
 */

namespace Colisys\RocketmqClient\Grpc\Factory;

use Closure;
use Colisys\RocketmqClient\Grpc\Client\ProducerClient;
use Colisys\RocketmqClient\Grpc\Contract\ConnectionOption;
use Hyperf\Contract\ConnectionInterface;

use function Hyperf\Support\make;

final class ProducerFactory extends BaseFactory
{
    /**
     * @return ConnectionInterface
     */
    public function get(?ConnectionOption $options = null)
    {
        // if (isset($options)) {
        //     $this->options = $options;
        //     $this->pool = null;
        // }

        if (! isset($this->pool)) {
            $this->pool = make(
                ConnectionPool::class,
                [
                    'config' => [
                        'client_type' => ProducerClient::class,
                        'client_options' => $this->options,
                    ],
                ]
            );
        }

        return $this->pool->get();
    }

    /**
     * @param Closure(ProducerClient $client): mixed $callback
     */
    public function using(?ConnectionOption $options = null, ?callable $callback = null)
    {
        if (! isset($callback)) {
            return;
        }
        /**
         * @var ConnectionInterface
         */
        $conn = $this->get($options);
        $result = $callback($conn->getConnection());
        $conn->release();
        return $result;
    }
}
