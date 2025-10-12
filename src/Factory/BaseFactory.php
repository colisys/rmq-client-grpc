<?php

declare(strict_types=1);
/**
 * Third-party RocketMQ Client SDK for Hyperf
 *
 * @contact colisys@duck.com
 * @license MIT
 * @copyright 2025 Colisys
 */

namespace Colisys\Rocketmq\Factory;

use Colisys\Rocketmq\Contract\ConnectionOption;
use Psr\Container\ContainerInterface;

abstract class BaseFactory
{
    protected ?ConnectionPool $pool;

    protected ?ConnectionOption $options;

    public function __construct(
        protected ?ContainerInterface $container = null,
    ) {
        $this->options = new ConnectionOption();
    }
}
