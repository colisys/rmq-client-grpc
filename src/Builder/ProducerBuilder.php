<?php

declare(strict_types=1);
/**
 * Third-party RocketMQ Client SDK for Hyperf
 *
 * @contact colisys@duck.com
 * @license MIT
 * @copyright 2025 Colisys
 */

namespace Colisys\RmqClient\Grpc\Builder;

use Apache\Rocketmq\V2\Message;
use Apache\Rocketmq\V2\Settings;
use Closure;
use Colisys\RmqClient\Shared\Constant\TransactionResult;
use Colisys\RmqClient\Shared\Contract\ClientContainer;
use Colisys\RmqClient\Shared\Contract\ConnectionOption;
use Colisys\RmqClient\Shared\Helper\Set;
use Colisys\RmqClient\Grpc\Impl\Producer;
use Hyperf\Context\ApplicationContext;
use Psr\Container\ContainerInterface;
use Swoole\Coroutine;
use function Colisys\RmqClient\Shared\Helper\container;

class ProducerBuilder
{
    private ?Settings $settings = null;

    private int $maxAttempts = 3;

    private Set $topics;

    /**
     * @var Closure(Message): TransactionResult
     */
    private $transactionChecker;

    public function __construct(
        private ConnectionOption $options,
        private ContainerInterface $container,
    ) {
        $this->clear();
    }

    public static function make(
        ?ConnectionOption $options = null,
        ?ContainerInterface $container = null
    ): self {
        return new self(
            $options ?? new ConnectionOption(),
            $container ?? ApplicationContext::getContainer()
        );
    }

    public function setConfiguration(Settings $settings): self
    {
        $this->settings = $settings;
        return $this;
    }

    public function clear(): self
    {
        $this->topics = new Set();
        $this->settings = null;
        $this->maxAttempts = 3;
        return $this;
    }

    /**
     * Declare topics ahead of message sending.
     *
     * **Note:**
     *
     * RocketMQ Producers are **not** required to declare topics ahead of time,
     * **not** improve the performance of message sending either, but
     * it will help to avoid the "topic not found error".
     *
     * @param string[] $topics
     */
    public function setTopics(array $topics): self
    {
        Assert::notEmpty($topics, 'Topics must not be empty');
        $this->topics->addAll($topics);
        return $this;
    }

    /**
     * Set the max attempts for max internal retries of message publishing.
     */
    public function setMaxAttempts(int $attempts): self
    {
        Assert::positive($attempts, 'Max attempts must be positive');
        $this->maxAttempts = $attempts;
        return $this;
    }

    /**
     * Transaction checker is a callback function that will be called when
     * RocketMQ server requires the producer to check the status of a transaction.
     *
     * @param Closure(Message $message): TransactionResult $checker
     */
    public function setTransactionChecker(callable $checker): self
    {
        $this->transactionChecker = $checker;
        return $this;
    }

    public function build(): Producer
    {
        $producer = new Producer(
            $this->options,
            $this->settings,
            $this->maxAttempts,
            $this->topics,
            $this->transactionChecker,
        );
        $this->clear();
        container()->get(ClientContainer::class)->add(null, $producer);
        Coroutine::create(fn () => $producer->start());
        return $producer;
    }
}
