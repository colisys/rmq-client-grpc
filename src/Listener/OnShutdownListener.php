<?php

declare(strict_types=1);
/**
 * Third-party RocketMQ Client SDK for Hyperf
 *
 * @contact colisys@duck.com
 * @license MIT
 * @copyright 2025 Colisys
 */

namespace Colisys\Rocketmq\Listener;

use Colisys\Rocketmq\Contract\ClientContainer;
use Colisys\Rocketmq\Helper\Log;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\OnShutdown;

use function Colisys\Rocketmq\Helper\container;

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
