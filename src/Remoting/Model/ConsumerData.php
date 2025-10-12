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

use Colisys\Rocketmq\Helper\Set;
use Colisys\Rocketmq\Remoting\Contract\ConsumeFromWhere;
use Colisys\Rocketmq\Remoting\Contract\ConsumeType;
use Colisys\Rocketmq\Remoting\Contract\MessageModel;
use JsonSerializable;

class ConsumerData extends BaseModel implements JsonSerializable
{
    public ConsumeType $consumeType;

    public MessageModel $messageModel;

    public ConsumeFromWhere $consumeFromWhere;

    public Set $subscriptionDataSet;

    public function __construct(
        public string $groupName,
        int|string $consumeType,
        int|string $messageModel,
        int|string $consumeFromWhere,
        $subscriptionDataSet,
        public bool $unitMode,
    ) {
        $this->consumeType = ConsumeType::from($consumeType);
        $this->messageModel = MessageModel::from($messageModel);
        $this->consumeFromWhere = ConsumeFromWhere::from($consumeFromWhere);
        $this->subscriptionDataSet = (new Set($subscriptionDataSet));
    }

    public function jsonSerialize(): mixed
    {
        return [
            'groupName' => $this->groupName,
            'consumeType' => $this->consumeType->value,
            'messageModel' => $this->messageModel->value,
            'consumeFromWhere' => $this->consumeFromWhere->value,
            'subscriptionDataSet' => $this->subscriptionDataSet,
            'unitMode' => $this->unitMode,
        ];
    }
}
