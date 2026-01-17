<?php

declare(strict_types=1);
/**
 * Unofficial RocketMQ Client SDK for Hyperf
 *
 * @contact colisys@duck.com
 * @license Apache-2.0
 * @copyright 2025 Colisys
 */

namespace Colisys\RmqClient\Grpc;

use Colisys\RmqClient\Grpc\Listener\OnShutdownListener;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [],
            'commands' => [],
            'listeners' => [
                OnShutdownListener::class,
            ],
            'annotations' => [
                'scan' => [
                    'paths' => [
                        __DIR__ . '/Annotation',
                        __DIR__ . '/Aspect',
                        __DIR__ . '/Signal',
                    ],
                ],
            ],
        ];
    }
}
