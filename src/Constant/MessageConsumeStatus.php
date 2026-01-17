<?php

declare(strict_types=1);
/**
 * Third-party RocketMQ Client SDK for Hyperf
 *
 * @contact colisys@duck.com
 * @license MIT
 * @copyright 2025 Colisys
 */

namespace Colisys\RocketmqClient\Grpc\Constant;

enum MessageConsumeStatus
{
    case CONSUME_SUCCESS;
    case FORWARD_DLQ;
    case CONSUME_FAILED;
}
