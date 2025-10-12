<?php

declare(strict_types=1);
/**
 * Third-party RocketMQ Client SDK for Hyperf
 *
 * @contact colisys@duck.com
 * @license MIT
 * @copyright 2025 Colisys
 */

namespace Colisys\Rocketmq\Remoting;

enum RemotingCommandType: int
{
    case REQUEST_COMMAND = 0;
    case RESPONSE_COMMAND = 1;
}
