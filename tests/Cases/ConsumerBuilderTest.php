<?php

declare(strict_types=1);
/**
 * Third-party RocketMQ Client SDK for Hyperf
 *
 * @contact colisys@duck.com
 * @license MIT
 * @copyright 2025 Colisys
 */

namespace Colisys\Rocketmq\Tests\Cases;

use Apache\Rocketmq\V2\ReceiveMessageResponse;
use Colisys\Rocketmq\Builder\ConsumerBuilder;
use Colisys\Rocketmq\Constant\MessageConsumeStatus;
use Colisys\Rocketmq\Contract\ConnectionOption;
use Colisys\Rocketmq\Helper\Log;
use Colisys\Rocketmq\Impl\Consumer;
use Colisys\Rocketmq\Override\ServerStreamCall;
use Colisys\Rocketmq\View\MessageView;
use Hyperf\Grpc\Parser;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class ConsumerBuilderTest extends TestCase
{
    public function testConsumerBuilder()
    {
        $options = new ConnectionOption();
        $consumer = ConsumerBuilder::make($options)
            ->setConsumerGroup('consumerGroup')
            ->addTopicWithTag('TopicTest', '*')
            ->addListener(function (MessageView $view) {
                $this->assertNotEmpty($view->id);
                Log::debug("* RocketMQ debug: Consumer received message#{$view->id}: {$view->body}");
                return MessageConsumeStatus::CONSUME_SUCCESS;
            })
            ->build();
        $this->assertInstanceOf(Consumer::class, $consumer);
        $results = ServerStreamCall::frame_split(hex2bin(file_get_contents('/tmp/recv.log')));
        foreach ($results as $result) {
            var_dump(bin2hex($result));
            Parser::deserializeMessage([ReceiveMessageResponse::class, 'decode'], $result);
        }
    }
}
