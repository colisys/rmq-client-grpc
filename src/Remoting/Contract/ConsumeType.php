<?php

declare(strict_types=1);
/**
 * Third-party RocketMQ Client SDK for Hyperf
 *
 * @contact colisys@duck.com
 * @license MIT
 * @copyright 2025 Colisys
 */

namespace Colisys\Rocketmq\Remoting\Contract;

enum ConsumeType: string
{
    case CONSUME_ACTIVELY = 'PULL';
    case CONSUME_PASSIVELY = 'PUSH';
    case CONSUME_POP = 'POP';
}
