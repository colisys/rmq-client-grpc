<?php

declare(strict_types=1);
/**
 * Third-party RocketMQ Client SDK for Hyperf
 *
 * @contact colisys@duck.com
 * @license MIT
 * @copyright 2025 Colisys
 */

namespace Colisys\Rocketmq\Factory;

use Colisys\Rocketmq\Client\ConsumerClient;
use Colisys\Rocketmq\Contract\ConnectionOption;
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
