<?php

declare(strict_types=1);
/**
 * Third-party RocketMQ Client SDK for Hyperf
 *
 * @contact colisys@duck.com
 * @license MIT
 * @copyright 2025 Colisys
 */

namespace Colisys\Rocketmq\Builder;

use Apache\Rocketmq\V2\MessageType;
use Closure;
use Colisys\Rocketmq\Constant\TransactionResult;
use Colisys\Rocketmq\Contract\MessageBuilder;
use Colisys\Rocketmq\Helper\Assert;
use ReflectionMethod;

class TransactionMessageBuilder extends MessageBuilder
{
    private $transactionCallback;

    /**
     * Set local transaction operation.
     *
     * @param \Closure(...$args): TransactionResult $callback
     */
    public function setTransaction(Closure $callback)
    {
        $rmethod = new ReflectionMethod($callback, '__invoke');
        Assert::fine($rmethod->getReturnType()?->getName() == TransactionResult::class, 'Transaction callback must return a TransactionResult instance');
        $this->transactionCallback = $callback;
        return $this;
    }

    public function build(): static
    {
        Assert::notNull($this->transactionCallback, 'Transaction callback is required');
        return parent::build();
    }

    /**
     * @internal
     */
    public function invokeTransaction(...$args): TransactionResult
    {
        return $this->transactionCallback->__invoke(...$args);
    }

    public function cleanup(): static
    {
        parent::cleanup();
        $this->transactionCallback = null;
        $this->message->getSystemProperties()->setMessageType(MessageType::TRANSACTION);
        return $this;
    }
}
