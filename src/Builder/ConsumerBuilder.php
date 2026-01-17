<?php

declare(strict_types=1);
/**
 * Third-party RocketMQ Client SDK for Hyperf
 *
 * @contact colisys@duck.com
 * @license MIT
 * @copyright 2025 Colisys
 */

namespace Colisys\RocketmqClient\Grpc\Builder;

use Apache\Rocketmq\V2\ClientType;
use Apache\Rocketmq\V2\FilterExpression;
use Apache\Rocketmq\V2\FilterType;
use Apache\Rocketmq\V2\Settings;
use Closure;
use Colisys\RocketmqClient\Grpc\Constant\MessageConsumeStatus;
use Colisys\RocketmqClient\Grpc\Contract\ClientContainer;
use Colisys\RocketmqClient\Grpc\Contract\ConnectionOption;
use Colisys\Rocketmq\Helper\Assert;
use Colisys\RocketmqClient\Grpc\Impl\Consumer;
use Hyperf\Context\ApplicationContext;
use Psr\Container\ContainerInterface;
use ReflectionMethod;
use ReflectionNamedType;
use Swoole\Coroutine;

use function Colisys\Rocketmq\Helper\container;

class ConsumerBuilder
{
    private ?Settings $settings;

    private int $size = 32;

    /**
     * @var array<string, FilterExpression>
     */
    private array $topics;

    /**
     * @var array<Closure(MessageView): MessageConsumeStatus>
     */
    private array $listeners;

    private string $consumerGroup;

    public function __construct(
        private ConnectionOption $options,
        private ContainerInterface $container,
    ) {
        $this->clear();
    }

    public function clear(): self
    {
        $this->listeners = [];
        $this->size = 32;
        $this->consumerGroup = '';
        $this->settings = null;
        return $this;
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

    /**
     * Declare a topic to consume.
     *
     * @see https://rocketmq.apache.org/docs/featureBehavior/07messagefilter/#tag-based-filtering
     */
    public function addTopicWithTag(string $topicName, string $filterExpression): self
    {
        $this->topics[$topicName] = new FilterExpression([
            'type' => FilterType::TAG,
            'expression' => $filterExpression,
        ]);
        return $this;
    }

    /**
     * Declare a topic to consume.
     *
     * @see https://rocketmq.apache.org/docs/featureBehavior/07messagefilter/#attribute-based-sql-filtering
     */
    public function addTopicWithSQL(string $topicName, string $filterExpression): self
    {
        $this->topics[$topicName] = new FilterExpression([
            'type' => FilterType::SQL,
            'expression' => $filterExpression,
        ]);
        return $this;
    }

    /**
     * Set the consumer group.
     *
     * In short, a group of consumers share the same **topic subscriptions, filter expressions,
     * and the offset of each message queue.**
     *
     * Consumers in the same group **MUST** maintain the same consumption logic, otherwise
     * it may cause message consumption disorder.
     *
     * @see https://rocketmq.apache.org/docs/domainModel/07consumergroup/
     * @see https://rocketmq.apache.org/docs/domainModel/09subscription/
     */
    public function setConsumerGroup(string $group): self
    {
        $this->consumerGroup = $group;
        return $this;
    }

    public function setSize(int $size): self
    {
        $this->size = $size;
        return $this;
    }

    /**
     * Build a consumer.
     *
     * If you add listener, it will be build a consumer as **Push Consumer**,
     * which means the consumer will be notified by the server when a new message arrives.
     *
     * **Pull Consumer** will allow you to control when to fetch message.
     */
    public function build()
    {
        $this->check();
        $consumer = new Consumer(
            $this->options,
            $this->consumerGroup,
            count($this->listeners) > 0 ? ClientType::PUSH_CONSUMER : ClientType::SIMPLE_CONSUMER,
            $this->topics,
            $this->settings,
            $this->listeners,
            $this->size,
        );
        $this->clear();
        container()->get(ClientContainer::class)->add(null, $consumer);
        Coroutine::create(fn () => $consumer->start());
        return $consumer;
    }

    /**
     * Add a listener to the consumer.
     *
     * @param Closure(MessageView $message): MessageConsumeStatus $callback
     */
    public function addListener(callable $callback): self
    {
        $rmethods = new ReflectionMethod($callback, '__invoke');
        Assert::instance($rmethods->getReturnType(), ReflectionNamedType::class, 'The listener must specify the return type');
        Assert::fine($rmethods->getReturnType()?->getName() == MessageConsumeStatus::class, 'The listener must return a MessageConsumeStatus');
        $this->listeners[] = $callback;
        return $this;
    }

    private function check()
    {
        Assert::positive(count($this->topics), 'Topics must not be empty');
        Assert::notEmpty($this->consumerGroup, 'Consumer group must not be empty');
    }
}
