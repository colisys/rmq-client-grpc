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
