<?php

declare(strict_types=1);
/**
 * Unofficial RocketMQ Client SDK for Hyperf
 *
 * @contact colisys@duck.com
 * @license Apache-2.0
 * @copyright 2025 Colisys
 */

namespace Colisys\RmqClient\Grpc\Listener;

use Colisys\RmqClient\Grpc\Contract\ClientContainer;
use Colisys\RmqClient\Shared\Helper\Log;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\OnShutdown;

use function Colisys\RmqClient\Shared\Helper\container;

class OnShutdownListener implements ListenerInterface
{
    public function listen(): array
    {
        return [
            OnShutdown::class,
        ];
    }

    public function process(object $event): void
    {
        Log::critical('! RocketMQ capture: shutdown');
        container()->get(ClientContainer::class)->shutdown();
    }
}
