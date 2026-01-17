<?php

declare(strict_types=1);
/**
 * Third-party RocketMQ Client SDK for Hyperf
 *
 * @contact colisys@duck.com
 * @license MIT
 * @copyright 2025 Colisys
 */

namespace Colisys\RmqClient\Shared\Tests\Cases;

use Colisys\RmqClient\Shared\Annotation\Producer as AnnotationProducer;
use Colisys\RmqClient\Shared\Builder\ProducerBuilder;
use Colisys\RmqClient\Shared\Builder\SimpleMessageBuilder;
use Colisys\RmqClient\Shared\Builder\TransactionMessageBuilder;
use Colisys\RmqClient\Shared\Constant\TransactionResult;
use Colisys\RmqClient\Shared\Contract\ConnectionOption;
use Colisys\RmqClient\Shared\Contract\MessageBuilder;
use Colisys\RmqClient\Shared\Impl\Producer;
use PHPUnit\Framework\TestCase;
use Swoole\Coroutine;

use function Colisys\RmqClient\Shared\Helper\timestamp;
use function Colisys\RmqClient\Shared\Helper\timestamp_diff;

/**
 * @internal
 * @coversNothing
 */
class ProducerBuilderTest extends TestCase
{
    public function testBuild()
    {
        $options = new ConnectionOption();
        $options->accessKey = '123123';
        $options->secretKey = '123123';
        $producer = ProducerBuilder::make($options)
            ->setTopics([
                'TopicTest',
            ])->build();
        $this->assertInstanceOf(Producer::class, $producer);
        Coroutine::create(function () use ($producer) {
            $beginTime = timestamp();
            $times = 1;
            while ($times-- > 0) {
                $producer->send(
                    SimpleMessageBuilder::make()
                        ->setTopic('TopicTest')
                        ->setTag('Tag1')
                        ->setBody(strval(date('Y-m-d H:i:s')))
                        ->withDigest()
                        ->build()
                );
            }
            var_dump(timestamp_diff(timestamp(), $beginTime));
        });
    }

    public function testAnnotation()
    {
        $result = $this->sendAnnotation();
        var_dump($result->getResult());
        $this->assertInstanceOf(MessageBuilder::class, $result);
    }

    public function testBuildTrx()
    {
        $options = new ConnectionOption();
        $options->accessKey = '123123';
        $options->secretKey = '123123';
        $producer = ProducerBuilder::make($options)
            ->setTopics([
                'TopicTestT',
                'TopicTest',
            ])->build();
        $this->assertInstanceOf(Producer::class, $producer);
        Coroutine::create(function () use ($producer) {
            $beginTime = timestamp();
            $times = 1;
            while ($times-- > 0) {
                $producer->send(
                    TransactionMessageBuilder::make()
                        ->setTopic('TopicTestT')
                        ->setTag('Tag1')
                        ->setBody(strval(date('Y-m-d H:i:s')))
                        ->setTransaction(
                            function (): TransactionResult {
                                return TransactionResult::COMMIT;
                            }
                        )
                        ->withDigest(),
                    SimpleMessageBuilder::make()
                        ->setTopic('TopicTest')
                        ->setTag('Tag1')
                        ->setBody(strval(date('Y-m-d H:i:s')))
                        ->withDigest()
                );
            }
            var_dump(timestamp_diff(timestamp(), $beginTime));
        });
    }

    #[AnnotationProducer()]
    private function sendAnnotation(): MessageBuilder
    {
        return SimpleMessageBuilder::make()
            ->setTopic('TopicTest')
            ->setTag('Tag1')
            ->setBody(strval(date('Y-m-d H:i:s')))
            ->withDigest()
            ->build();
    }
}
