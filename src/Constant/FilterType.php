<?php

declare(strict_types=1);
/**
 * Third-party RocketMQ Client SDK for Hyperf
 *
 * @contact colisys@duck.com
 * @license MIT
 * @copyright 2025 Colisys
 */

namespace Colisys\RmqClient\Grpc\Constant;

/**
 * Message Filter Type.
 *
 * @see https://rocketmq.apache.org/docs/featureBehavior/07messagefilter
 */
enum FilterType
{
    case TAG;
    case SQL92;
}
