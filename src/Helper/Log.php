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

use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\StdoutLoggerInterface;
use Psr\Log\LoggerInterface;

use function Hyperf\Support\make;

/**
 * @method static void error(string $msg)
 * @method static void info(string $msg)
 * @method static void debug(string $msg)
 * @method static void warning(string $msg)
 * @method static void notice(string $msg)
 * @method static void emergency(string $msg)
 * @method static void critical(string $msg)
 */
class Log
{
    private static ?LoggerInterface $logger = null;

    public function __construct()
    {
        Log::setLogger(ApplicationContext::getContainer()->get(StdoutLoggerInterface::class));
    }

    public static function __callStatic($name, $arguments)
    {
        make(static::class)::$logger?->{$name}(...$arguments);
    }

    public static function setLogger(LoggerInterface $logger)
    {
        self::$logger = $logger;
    }
}
