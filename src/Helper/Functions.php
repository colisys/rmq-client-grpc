<?php

declare(strict_types=1);
/**
 * Third-party RocketMQ Client SDK for Hyperf
 *
 * @contact colisys@duck.com
 * @license MIT
 * @copyright 2025 Colisys
 */

namespace Colisys\Rocketmq\Helper;

use Apache\Rocketmq\V2\Resource;
use Google\Protobuf\Duration;
use Google\Protobuf\Timestamp;
use Hyperf\Context\ApplicationContext;
use Hyperf\Engine\Channel;
use Swoole\Coroutine;

if (! function_exists('container')) {
    function container()
    {
        return ApplicationContext::getContainer();
    }
}

if (! function_exists('resource')) {
    function resource($resource, $namespace = '')
    {
        return new Resource([
            'name' => $resource,
            'resource_namespace' => $namespace,
        ]);
    }
}

if (! function_exists('duration')) {
    function duration(float $duration)
    {
        return new Duration(['seconds' => $duration]);
    }
}

if (! function_exists('timestamp')) {
    function timestamp(?float $timestamp = null)
    {
        if (! isset($timestamp)) {
            $timestamp = microtime(true);
        }
        return new Timestamp(['seconds' => $timestamp, 'nanos' => ($timestamp - intval($timestamp)) * 1000000000]);
    }
}

if (! function_exists('timestamp_diff')) {
    function timestamp_diff(Timestamp $start, Timestamp $end): float
    {
        return round(($end->getSeconds() + $end->getNanos() / 1000000000) - ($start->getSeconds() + $start->getNanos() / 1000000000), 3);
    }
}

if (! function_exists('channel_zip')) {
    function channel_zip(Channel ...$channel)
    {
        $dest = new Channel(count($channel));
        foreach ($channel as $ch) {
            Coroutine::create(fn ($ch) => $dest->push($ch->pop()), $ch);
        }
        return $dest;
    }
}
