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

use Apache\Rocketmq\V2\MessageType;
use Colisys\RocketmqClient\Grpc\Contract\MessageBuilder;
use Google\Protobuf\Timestamp;

/**
 * This is a builder for creating a **non transactional** message.
 *
 * Noted: Transactional message should use `TrxBuilder`.
 */
class SimpleMessageBuilder extends MessageBuilder
{
    /**
     * Set message delay time, and set type to `DELAY`.
     *
     * @param float|int|Timestamp $seconds
     */
    public function delay($seconds): static
    {
        if (is_numeric($seconds)) {
            $seconds = new Timestamp([
                'seconds' => time() + $seconds,
                'nanos' => ($seconds - (int) $seconds) * 1000000000,
            ]);
        }

        if ($seconds instanceof Timestamp) {
            $this->normal();
            $this->message->getSystemProperties()->setMessageType(MessageType::DELAY);
            $this->message->getSystemProperties()->setDeliveryTimestamp($seconds);
        }

        return $this;
    }

    /**
     * Set message type to normal.
     */
    public function normal(): static
    {
        $this->message->getSystemProperties()->setMessageType(MessageType::NORMAL);
        $this->message->getSystemProperties()->setDeliveryTimestamp(null);
        $this->message->getSystemProperties()->setMessageGroup(null);
        return $this;
    }

    /**
     * Set message type to FIFO, and set message group.
     */
    public function fifo(string $group): static
    {
        $this->normal();
        $this->message->getSystemProperties()->setMessageType(MessageType::FIFO);
        $this->message->getSystemProperties()->setMessageGroup($group);
        return $this;
    }
}
