<?php

declare(strict_types=1);
/**
 * Third-party RocketMQ Client SDK for Hyperf
 *
 * @contact colisys@duck.com
 * @license MIT
 * @copyright 2025 Colisys
 */

namespace Colisys\Rocketmq\View;

use Apache\Rocketmq\V2\Code;
use Apache\Rocketmq\V2\SendMessageResponse;
use Apache\Rocketmq\V2\SendResultEntry;
use Throwable;

class SendResultView
{
    public function __construct(
        private SendMessageResponse $response
    ) {
    }

    public static function make(SendMessageResponse $response): self
    {
        return new self($response);
    }

    /**
     * @see Code
     * @see https://github.com/apache/rocketmq-apis/blob/main/apache/rocketmq/v2/definition.proto#L302
     * @see \Apache\Rocketmq\V2\Status::getCode()
     */
    public function getCode(): int
    {
        return $this->response->getStatus()->getCode();
    }

    /**
     * @see \Apache\Rocketmq\V2\Status::getMessage()
     */
    public function getStatus(): string
    {
        return $this->response->getStatus()->getMessage();
    }

    /**
     * Get message id by index.
     *
     * @see \Apache\Rocketmq\V2\SendResultEntry::getMessageId()
     */
    public function getMessageId(int $index = 0): ?string
    {
        return $this->getEntry($index)?->getMessageId();
    }

    /**
     * Get transcation id by index, only works when message is sent with transaction.
     *
     * @see \Apache\Rocketmq\V2\SendResultEntry::getTransactionId()
     */
    public function getTransactionId(int $index = 0): ?string
    {
        return $this->getEntry($index)?->getTransactionId();
    }

    /**
     * Get messsage queue offset by index.
     *
     * @see \Apache\Rocketmq\V2\SendResultEntry::getOffset()
     */
    public function getMessageOffset(int $index = 0): ?int
    {
        return $this->getEntry($index)?->getOffset();
    }

    /**
     * Get message recall id, only works for delay message now.
     *
     * @see \Apache\Rocketmq\V2\SendResultEntry::getRecallHandle()
     */
    public function getRecallId(int $index = 0): ?string
    {
        return $this->getEntry($index)?->getRecallHandle();
    }

    /**
     * @see \Apache\Rocketmq\V2\SendMessageResponse::getEntries()
     * @see SendResultEntry
     */
    private function getEntry(int $index = 0): ?SendResultEntry
    {
        try {
            return $this->response->getEntries()->offsetGet($index);
        } catch (Throwable $th) {
            return null;
        }
    }
}
