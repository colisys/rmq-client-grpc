<?php

declare(strict_types=1);
/**
 * Third-party RocketMQ Client SDK for Hyperf
 *
 * @contact colisys@duck.com
 * @license MIT
 * @copyright 2025 Colisys
 */

namespace Colisys\RocketmqClient\Grpc\Signal;

use Colisys\RocketmqClient\Grpc\Contract\ClientContainer;
use Hyperf\Signal\Annotation\Signal;
use Hyperf\Signal\SignalHandlerInterface;

use function Colisys\Rocketmq\Helper\container;

#[Signal]
class ProcessExitHandler implements SignalHandlerInterface
{
    public function listen(): array
    {
        return [
            [SignalHandlerInterface::WORKER, SIGINT],
            [SignalHandlerInterface::WORKER, SIGTERM],
        ];
    }

    public function handle(int $signal): void
    {
        // TODO Should shutdown clients gracefully.
        container()->get(ClientContainer::class)->shutdown();
    }
}
