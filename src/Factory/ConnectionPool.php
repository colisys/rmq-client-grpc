<?php

declare(strict_types=1);
/**
 * Third-party RocketMQ Client SDK for Hyperf
 *
 * @contact colisys@duck.com
 * @license MIT
 * @copyright 2025 Colisys
 */

namespace Colisys\RmqClient\Grpc\Factory;

use Colisys\RmqClient\Grpc\Builder\MetadataBuilder;
use Colisys\RmqClient\Grpc\Constant\SDK;
use Colisys\RmqClient\Grpc\Contract\ClientWrapper;
use Colisys\RmqClient\Grpc\Contract\ConnectionOption;
use Colisys\Rocketmq\Helper\Signature;
use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\ConnectionInterface;
use Hyperf\Pool\Pool;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use ReflectionClass;

final class ConnectionPool extends Pool
{
    /**
     * @var class-string
     */
    private string $client_type;

    private ConnectionOption $client_options;

    public function __construct(
        protected ContainerInterface $container,
        array $config = []
    ) {
        if (! isset($config['client_type'])) {
            throw new InvalidArgumentException('client_type is required');
        }
        if (! isset($config['client_options'])) {
            throw new InvalidArgumentException('client_options is required');
        }

        $rclass = new ReflectionClass($config['client_type']);
        if (! $rclass->isSubclassOf(ClientWrapper::class)) {
            throw new InvalidArgumentException('client_type must be a subclass of ' . ClientWrapper::class);
        }

        $this->client_type = $config['client_type'];
        $this->client_options = $config['client_options'];

        parent::__construct($container, $config);
    }

    public function get(): ConnectionInterface
    {
        return parent::get();
    }

    protected function createConnection(): ConnectionInterface
    {
        return new class($this->client_type, $this->client_options, $this) implements ConnectionInterface {
            /**
             * @var ConsumerClient|ProducerClient
             */
            protected $conn;

            public function __construct(
                private string $client_type,
                private ConnectionOption $client_options,
                private ConnectionPool $pool,
            ) {
            }

            /**
             * Get the real connection from pool.
             *
             * @return ClientWrapper
             */
            public function getConnection()
            {
                $rclass = new ReflectionClass($this->client_type);
                $this->conn = $rclass->newInstanceArgs(['options' => $this->client_options]);
                $fn = function ($next, ...$args) {
                    if (count($args) == 5) {
                        [$service, $message, $decoder, $metadata, $options] = $args;
                    }
                    if (count($args) == 4) {
                        [$service, $decoder, $metadata, $options] = $args;
                    }

                    $metaBuilder = ApplicationContext::getContainer()->get(MetadataBuilder::class);
                    $metaBuilder
                        ->clear()
                        ->add('x-mq-client-id', $this->client_options->clientId)
                        ->add('x-mq-language', 'PHP')
                        ->add('x-mq-date-time', date('c'))
                        ->add('x-mq-client-version', SDK::SDK_VERSION)
                        ->add('x-mq-protocol-version', SDK::PROTOCOL_VERSION);

                    if ($this->client_options->namespace) {
                        $metaBuilder->add('x-mq-namespace', $this->client_options->namespace);
                    }

                    if ($this->client_options->secretKey && $this->client_options->accessKey) {
                        $dummy = bin2hex(random_bytes(16));
                        $sign = bin2hex(Signature::sign($this->client_options->secretKey, $dummy));
                        $metaBuilder
                            ->add('x-mq-challenge', $dummy)
                            ->add('authorization', "MQv2-HMAC-SHA1 Credential={$this->client_options->accessKey}, SignedHeaders=x-mq-challenge, Signature={$sign}");
                    }

                    if ($this->client_options->sessionToken) {
                        $metaBuilder->add('x-mq-session-token', $this->client_options->sessionToken);
                    }

                    foreach ($metadata as $key => $value) {
                        $metaBuilder->add($key, $value);
                    }

                    $metadata = $metaBuilder->build();
                    if (count($args) == 5) {
                        return $next($service, $message, $decoder, $metadata, $options);
                    }
                    if (count($args) == 4) {
                        return $next($service, $decoder, $metadata, $options);
                    }
                };
                $this->conn->beforeRequest[] = $fn;
                $this->conn->beforeStream[] = $fn;
                return $this->conn;
            }

            /**
             * Reconnect the connection.
             */
            public function reconnect(): bool
            {
                $this->close();
                $this->getConnection();
                return $this->check();
            }

            /**
             * Check the connection is valid.
             */
            public function check(): bool
            {
                if (! isset($this->conn)) {
                    $this->reconnect();
                }
                return $this->conn?->_getGrpcClient()?->isConnected() ?? false;
            }

            /**
             * Close the connection.
             */
            public function close(): bool
            {
                return $this->conn?->close() ?? false;
            }

            /**
             * Release the connection to pool.
             */
            public function release(): void
            {
                $this->conn = null;
                $this->pool->release($this);
            }
        };
    }
}
