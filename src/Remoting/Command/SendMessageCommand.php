<?php

declare(strict_types=1);
/**
 * Third-party RocketMQ Client SDK for Hyperf
 *
 * @contact colisys@duck.com
 * @license MIT
 * @copyright 2025 Colisys
 */

namespace Colisys\Rocketmq\Remoting\Command;

use Colisys\Rocketmq\Helper\Arr;
use Colisys\Rocketmq\Remoting\Model\ConsumerData;
use Colisys\Rocketmq\Remoting\Model\ProducerData;
use Colisys\Rocketmq\Remoting\RemotingCommand;
use Colisys\Rocketmq\Remoting\RemotingCommandType;
use Colisys\Rocketmq\Remoting\RequestCode;

/**
 * @property string $clientId
 * @property Arr<ProducerData> $producerDataSet
 * @property Arr<ConsumerData> $consumerDataSet
 * @property int $heartbeatFingerprint
 * @property bool $isWithoutSub
 */
class SendMessageCommand extends RemotingCommand
{
    public function __construct()
    {
        parent::__construct();
        $this->setCode(RequestCode::SEND_MESSAGE->value);
    }

    public static function createResponseCommand(string $rawString): static
    {
        $obj = new static();
        $obj->setRemotingCommandType(RemotingCommandType::RESPONSE_COMMAND);
        $obj->decode($rawString);
        return $obj;
    }

    public static function createRequestCommand(): static
    {
        $obj = new static();
        $obj->setRemotingCommandType(RemotingCommandType::REQUEST_COMMAND);
        $obj->setBody([
            'clientId' => '',
            'producerDataSet' => new Arr(className: ProducerData::class),
            'consumerDataSet' => new Arr(className: ConsumerData::class),
            'heartbeatFingerprint' => 0,
            'isWithoutSub' => false,
        ]);
        return $obj;
    }

    public static function decodeBody(string $body, string $serializeType)
    {
        if ($serializeType == 'JSON') {
            $body = json_decode($body, true);
            $body['producerDataSet'] = Arr::fromArray(ProducerData::parseFromArray($body['producerDataSet']), ProducerData::class);
            $body['consumerDataSet'] = Arr::fromArray(ConsumerData::parseFromArray($body['consumerDataSet']), ConsumerData::class);
        }
        return $body;
    }

    public static function decodeAfter(self $command)
    {
        $command->setCode(RequestCode::SEND_MESSAGE->value);
        return $command;
    }

    public function jsonSerialize(): array
    {
        return [
            'clientId' => $this->clientId,
            'producerDataSet' => $this->producerDataSet->toArray(),
            'consumerDataSet' => $this->consumerDataSet->toArray(),
            'heartbeatFingerprint' => $this->heartbeatFingerprint,
            'isWithoutSub' => $this->isWithoutSub,
        ];
    }
}
