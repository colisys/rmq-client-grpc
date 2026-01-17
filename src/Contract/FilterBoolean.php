<?php

declare(strict_types=1);
/**
 * Third-party RocketMQ Client SDK for Hyperf
 *
 * @contact colisys@duck.com
 * @license MIT
 * @copyright 2025 Colisys
 */

namespace Colisys\RocketmqClient\Grpc\Contract;

/**
 * SQL92 Filter Boolean.
 *
 * @see https://rocketmq.apache.org/docs/featureBehavior/07messagefilter/#attribute-based-sql-filtering
 */
enum FilterBoolean: string
{
    case AND = 'AND';
    case OR = 'OR';
}
