<?php

declare(strict_types=1);
/**
 * Third-party RocketMQ Client SDK for Hyperf
 *
 * @contact colisys@duck.com
 * @license MIT
 * @copyright 2025 Colisys
 */

namespace Colisys\RmqClient\Grpc\View;

use Apache\Rocketmq\V2\Message;

class MessageView
{
    public string $id = '';

    public string $tag = '';

    public string $body = '';

    public string $group = '';

    public array $properties = [];

    public int $queueId = 0;

    public int $queueOffset = 0;

    public function __construct(
        public Message $rawMessage
    ) {
        $this->id = $this->rawMessage->getSystemProperties()->getMessageId();
        $this->tag = $this->rawMessage->getSystemProperties()->getTag();
        $this->body = $this->rawMessage->getBody();
        $this->group = $this->rawMessage->getSystemProperties()->getMessageGroup();
        $this->queueId = $this->rawMessage->getSystemProperties()->getQueueId();
        $this->queueOffset = $this->rawMessage->getSystemProperties()->getQueueOffset();
        $this->getProperties();
    }

    private function getProperties()
    {
        foreach ($this->rawMessage->getUserProperties() as $key => $value) {
            $this->properties[$key] = $value;
        }
    }
}
