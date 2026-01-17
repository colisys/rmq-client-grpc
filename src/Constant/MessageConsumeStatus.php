<?php

declare(strict_types=1);
/**
 * Unofficial RocketMQ Client SDK for Hyperf
 *
 * @contact colisys@duck.com
 * @license Apache-2.0
 * @copyright 2025 Colisys
 */

namespace Colisys\RmqClient\Grpc\Constant;

enum MessageConsumeStatus
{
    case CONSUME_SUCCESS;
    case FORWARD_DLQ;
    case CONSUME_FAILED;
}
