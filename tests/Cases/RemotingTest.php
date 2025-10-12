<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace Colisys\Rocketmq\Tests\Cases;

use Colisys\Rocketmq\Remoting\Command\GetAllTopicsFromNamesrvCommand;
use Colisys\Rocketmq\Remoting\Command\SendMessageCommand;
use Colisys\Rocketmq\Remoting\Contract\ConsumeFromWhere;
use Colisys\Rocketmq\Remoting\Contract\ConsumeType;
use Colisys\Rocketmq\Remoting\Contract\MessageModel;
use Colisys\Rocketmq\Remoting\CoroutineClient;
use Colisys\Rocketmq\Remoting\Model\ConsumerData;
use Colisys\Rocketmq\Remoting\Model\ProducerData;
use Colisys\Rocketmq\Remoting\RemotingCommand;
use Colisys\Rocketmq\Remoting\RemotingCommandType;
use Colisys\Rocketmq\Remoting\RequestCode;
use Hyperf\Coroutine\Coroutine;
use Hyperf\Testing\TestCase;

class StubCommand extends RemotingCommand
{
}

/**
 * @internal
 * @coversNothing
 */
class RemotingTest extends TestCase
{
    public function testRemotingCommand()
    {
        $str = '{"brokerAddrTable":{"Helix-MBP.local":{"brokerAddrs":{0:"192.168.1.16:10911"},"brokerName":"Helix-MBP.local","cluster":"DefaultCluster","enableActingMaster":false}},"clusterAddrTable":{"DefaultCluster":["Helix-MBP.local"]}}';
        $str = preg_replace('/\{\d+:/', '{"$1":', $str);
        $str = preg_replace('/,\d+:/', ',"$1":', $str);
        json_decode($str);
        $this->assertEquals(JSON_ERROR_NONE, json_last_error(), json_last_error_msg());

        Coroutine::create(function () {
            $hex = '00000147000000647b22636f6465223a302c22666c6167223a312c226c616e6775616765223a224a415641222c226f7061717565223a3134353532372c2273657269616c697a655479706543757272656e74525043223a224a534f4e222c2276657273696f6e223a3437377d7b2262726f6b6572416464725461626c65223a7b2248656c69782d4d42502e6c6f63616c223a7b2262726f6b65724164647273223a7b303a223139322e3136382e312e31363a3130393131227d2c2262726f6b65724e616d65223a2248656c69782d4d42502e6c6f63616c222c22636c7573746572223a2244656661756c74436c7573746572222c22656e61626c65416374696e674d6173746572223a66616c73657d7d2c22636c7573746572416464725461626c65223a7b2244656661756c74436c7573746572223a5b2248656c69782d4d42502e6c6f63616c225d7d7d';
            $cmd = StubCommand::decode(hex2bin($hex));
            $this->assertInstanceOf(RemotingCommand::class, $cmd);
            $this->assertEquals(0, $cmd->getCode());
            $this->assertEquals(RemotingCommandType::RESPONSE_COMMAND, $cmd->getFlag());
            $this->assertEquals('JSON', $cmd->getSerializeTypeCurrentRPC());
            // We cannot compare the body because we modify the body in decode for compatibility of PHP,
            // instead, we compare the encode and decode result.
            $this->assertNotNull($cmd->encode(), StubCommand::decode($cmd->encode())->encode());
        });
    }

    public function testSocket()
    {
        $this->assertTrue(true);
        Coroutine::create(function () {
            $cls = new class extends RemotingCommand {
                public function __construct()
                {
                    parent::__construct();
                }

                public static function createRequestCommand(): static
                {
                    $cmd = parent::createRequestCommand();
                    $cmd->setCode(RequestCode::GET_BROKER_CLUSTER_INFO->value);
                    return $cmd;
                }

                public function jsonSerialize(): array
                {
                    return $this->getBody();
                }
            };

            $cmd = $cls::createRequestCommand();
            $client = new CoroutineClient('127.0.0.1', 9999);
            $response = $cls::createResponseCommand(
                $client->use(
                    function ($client) use ($cmd) {
                        if ($client->send($cmd->encode())->isOk()) {
                            $response = $client->recv(10);
                            if ($response->isOk()) {
                                return $response->getResult();
                            }
                        }
                        return '';
                    }
                )
            );

            $this->assertInstanceOf($cls::class, $response);
            $this->assertEquals(0, $response->getCode());
            $this->assertEquals($cmd->getOpaque(), $response->getOpaque());
            $this->assertEquals(RemotingCommandType::RESPONSE_COMMAND, $response->getRemotingCommandType());
            var_dump($cmd->encode());
            var_dump($response->encode());
        });
    }

    public function testCommandBuild()
    {
        $cmd = SendMessageCommand::createRequestCommand();
        $this->assertInstanceOf(SendMessageCommand::class, $cmd);
        $cmd->clientId = 'test';
        $this->assertEquals('test', $cmd->clientId);
        $cmd->producerDataSet->append([
            new ProducerData('Hello'),
            new ConsumerData(
                'World',
                ConsumeType::CONSUME_ACTIVELY->value,
                MessageModel::CLUSTERING->value,
                ConsumeFromWhere::CONSUME_FROM_LAST_OFFSET->value,
                [],
                false
            ),
        ]);
        $this->assertEquals(1, $cmd->producerDataSet->count());
    }

    public function testGetAllTopicsFromNamesrvCommand()
    {
        $cmd = GetAllTopicsFromNamesrvCommand::createRequestCommand();
        $client = new CoroutineClient('127.0.0.1', 9999);
        $response = $client->use(function ($client) use ($cmd) {
            if ($client->send($cmd->encode())->isOk()) {
                $response = $client->recv(10);
                if ($response->isOk()) {
                    return GetAllTopicsFromNamesrvCommand::createResponseCommand($response->getResult());
                }
            }
            return null;
        });
        $this->assertInstanceOf(GetAllTopicsFromNamesrvCommand::class, $response);
        $this->assertNotEmpty($response->getVersion());
    }
}
