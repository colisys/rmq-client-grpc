<?php

declare(strict_types=1);
/**
 * Third-party RocketMQ Client SDK for Hyperf
 *
 * @contact colisys@duck.com
 * @license MIT
 * @copyright 2025 Colisys
 */

namespace Colisys\RmqClient\Grpc\Contract;

use Apache\Rocketmq\V2\Digest;
use Apache\Rocketmq\V2\DigestType;
use Apache\Rocketmq\V2\Message;
use Apache\Rocketmq\V2\Resource;
use Apache\Rocketmq\V2\SendMessageResponse;
use Apache\Rocketmq\V2\SystemProperties;
use Colisys\Rocketmq\Helper\Arr;
use Colisys\Rocketmq\Helper\Assert;
use Colisys\RmqClient\Grpc\View\SendResultView;

abstract class MessageBuilder
{
    protected ?Message $message;

    protected $body;

    protected array $properties = [];

    private bool $isAop = false;

    private ?SendMessageResponse $response = null;

    public function __construct()
    {
        $this->cleanup();
    }

    public static function make(): static
    {
        return new static();
    }

    /**
     * Set message Topic.
     */
    public function setTopic(string $topic, string $namespace = ''): static
    {
        $resource = $this->message->getTopic();
        if (! isset($resource)) {
            $resource = new Resource();
        }

        $resource->setName($topic);
        $resource->setResourceNamespace($namespace);

        $this->message->setTopic($resource);
        return $this;
    }

    /**
     * Get message Topic.
     */
    public function getTopic(): Resource
    {
        return $this->message->getTopic();
    }

    /**
     * Set message body.
     *
     * @param mixed $body **MUST** be stringable
     */
    public function setBody($body): static
    {
        $this->body = $body;
        return $this;
    }

    /**
     * Get message body.
     */
    public function getBody(): mixed
    {
        return $this->body;
    }

    /**
     * Set message digest.
     *
     * @param DigestType $type
     */
    public function withDigest(int $type = DigestType::MD5): static
    {
        $digest = new Digest();
        $digest->setType($type);
        $digest->setChecksum(match ($type) {
            DigestType::MD5 => md5($this->body),
            DigestType::SHA1 => sha1($this->body),
            DigestType::CRC32 => strval(crc32($this->body)),
            default => '',
        });
        $this->message->getSystemProperties()->setBodyDigest($digest);
        return $this;
    }

    /**
     * Get message properties.
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * Set message properties.
     *
     * A message has a set of key-value pairs, which can be used to
     * store additional information, also it can be filtered by SQL92 syntax.
     */
    public function setProperty(string $key, int|string $value): static
    {
        $this->properties[$key] = $value;
        return $this;
    }

    /**
     * Delete message property.
     */
    public function delProperty(string $key): static
    {
        unset($this->properties[$key]);
        return $this;
    }

    /**
     * Set message tag.
     */
    public function setTag(string $tag): static
    {
        $this->message->getSystemProperties()->setTag($tag);
        return $this;
    }

    /**
     * Get message tag.
     */
    public function getTag(): string
    {
        return $this->message->getSystemProperties()->getTag();
    }

    /**
     * Add message key.
     */
    public function addKey(string ...$keys): static
    {
        foreach ($keys as $key) {
            $this->message->getSystemProperties()->getKeys()->offsetSet(null, $key);
        }
        return $this;
    }

    /**
     * Get message key.
     */
    public function getKey()
    {
        return Arr::fromRepeatField($this->message->getSystemProperties()->getKeys(), string::class);
    }

    /**
     * Build message.
     */
    public function build(): static
    {
        $body = $this->body;
        if (! is_scalar($body)) {
            $body = json_encode($body);
        }
        $this->message->setBody($body);
        return $this;
    }

    /**
     * Get actual message.
     */
    public function getMessage(): Message
    {
        return $this->message;
    }

    public function aop(): static
    {
        $this->isAop = true;
        return $this;
    }

    /**
     * **This api should be called AOP Only.**.
     *
     * Get result after sending message.
     */
    public function getResult(): SendResultView
    {
        Assert::true($this->isAop, 'getResult() should be called AOP Only.');
        Assert::notNull($this->response, 'setResult() should be called after send()');
        return SendResultView::make($this->response);
    }

    public function setResult(SendMessageResponse $response): static
    {
        Assert::true($this->isAop, 'setResult() should be called AOP Only.');
        $this->response = $response;
        return $this;
    }

    /**
     * Reset the message builder.
     */
    public function cleanup(): static
    {
        $this->message = new Message([
            'topic' => new Resource(),
            'system_properties' => new SystemProperties([
                'message_id' => sha1(uniqid() . microtime(true)),
                'tag' => '',
                'keys' => [],
            ]),
            'body' => '',
        ]);
        $this->body = null;
        $this->properties = [];
        return $this;
    }
}
