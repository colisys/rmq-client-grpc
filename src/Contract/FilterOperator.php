<?php

declare(strict_types=1);
/**
 * Third-party RocketMQ Client SDK for Hyperf
 *
 * @contact colisys@duck.com
 * @license MIT
 * @copyright 2025 Colisys
 */

namespace Colisys\RmqClient\Grpc\Contract;

/**
 * SQL92 Filter Operators.
 *
 * @see https://rocketmq.apache.org/docs/featureBehavior/07messagefilter/#attribute-based-sql-filtering
 */
enum FilterOperator: string
{
    case EQ = '%s = %s';
    case LT = '%s < %s';
    case LE = '%s <= %s';
    case GT = '%s > %s';
    case GE = '%s >= %s';
    case NE = '%s <> %s';
    case IN = '%s IN (%s)';
    case NOTIN = '%s NOT IN (%s)';
    case ISNULL = '%s IS NULL';
    case ISNOTNULL = '%s IS NOT NULL';
    case LAMBDA = '(%s)';
    case RAW = '%s';
    case BETWEEN = '%s BETWEEN %s AND %s';
    case NOTBETWEEN = '%s NOT BETWEEN %s AND %s';
}
