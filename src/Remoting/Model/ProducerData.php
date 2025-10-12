<?php

declare(strict_types=1);
/**
 * Third-party RocketMQ Client SDK for Hyperf
 *
 * @contact colisys@duck.com
 * @license MIT
 * @copyright 2025 Colisys
 */

namespace Colisys\Rocketmq\Remoting\Model;

use JsonSerializable;

class ProducerData extends BaseModel implements JsonSerializable
{
    public function __construct(
        public string $groupName
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'groupName' => $this->groupName,
        ];
    }
}
