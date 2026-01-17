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

use Colisys\Rocketmq\Helper\Set;

class TagBuilder
{
    private Set $tags;

    public function __construct()
    {
        $this->tags = new Set();
    }

    public function multiple(array $topics): self
    {
        $this->tags->clear();
        $this->tags->addAll($topics);
        return $this;
    }

    public function all(): self
    {
        $this->tags->clear();
        $this->tags->add('*');
        return $this;
    }

    public function exact(string $tag): self
    {
        $this->tags->clear();
        $this->tags->add($tag);
        return $this;
    }

    public function build(): string
    {
        return implode('||', $this->tags->toArray());
    }
}
