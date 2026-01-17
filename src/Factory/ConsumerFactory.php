<?php

declare(strict_types=1);
/**
 * Unofficial RocketMQ Client SDK for Hyperf
 *
 * @contact colisys@duck.com
 * @license Apache-2.0
 * @copyright 2025 Colisys
 */

namespace Colisys\RmqClient\Grpc\Factory;

use Colisys\RmqClient\Grpc\Client\ConsumerClient;
use Colisys\RmqClient\Grpc\Contract\ConnectionOption;
use Hyperf\Contract\ConnectionInterface;

use function Hyperf\Support\make;

final class ConsumerFactory extends BaseFactory
{
    /**
     * @return ConsumerClient
     */
    public function get(?ConnectionOption $options = null)
    {
        if (isset($options)) {
            $this->options = $options;
        }

        if (! isset($this->pool)) {
            $this->pool = make(
                ConnectionPool::class,
                [
                    'config' => [
                        'client_type' => ConsumerClient::class,
                        'client_options' => $this->options,
                    ],
                ]
            );
        }

        return $this->pool->get();
    }

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
