<?php

declare(strict_types=1);
/**
 * Third-party RocketMQ Client SDK for Hyperf
 *
 * @contact colisys@duck.com
 * @license MIT
 * @copyright 2025 Colisys
 */

namespace Colisys\RocketmqClient\Grpc\Client;

use Apache\Rocketmq\V2\AckMessageRequest;
use Apache\Rocketmq\V2\AckMessageResponse;
use Apache\Rocketmq\V2\ChangeInvisibleDurationRequest;
use Apache\Rocketmq\V2\ChangeInvisibleDurationResponse;
use Apache\Rocketmq\V2\ForwardMessageToDeadLetterQueueRequest;
use Apache\Rocketmq\V2\ForwardMessageToDeadLetterQueueResponse;
use Apache\Rocketmq\V2\HeartbeatRequest;
use Apache\Rocketmq\V2\HeartbeatResponse;
use Apache\Rocketmq\V2\PullMessageRequest;
use Apache\Rocketmq\V2\PullMessageResponse;
use Apache\Rocketmq\V2\ReceiveMessageRequest;
use Apache\Rocketmq\V2\ReceiveMessageResponse;
use Colisys\RocketmqClient\Grpc\Contract\ClientWrapper;
use Colisys\RocketmqClient\Grpc\Contract\ConnectionOption;
use Colisys\Rocketmq\Helper\Assert;
use Colisys\Rocketmq\Helper\Log;
use Grpc\ChannelCredentials;
use Hyperf\Engine\Channel;
use RuntimeException;
use Swoole\Coroutine;
use Swoole\Http2\Response;
use Throwable;

use function Hyperf\Support\retry;

/**
 * @see ClientWrapper
 * @method ?HeartbeatResponse Heartbeat(HeartbeatRequest $request)
 * @method Channel ReceiveMessage(ReceiveMessageRequest $request)
 * @method ?AckMessageResponse AckMessage(AckMessageRequest $request)
 * @method ?PullMessageResponse PullMessage(PullMessageRequest $request)
 * @method ?ForwardMessageToDeadLetterQueueResponse ForwardMessageToDeadLetterQueue(ForwardMessageToDeadLetterQueueRequest $request)
 * @method ?ChangeInvisibleDurationResponse ChangeInvisibleDuration(ChangeInvisibleDurationRequest $request)
 */
class ConsumerClient extends ClientWrapper
{
    public function __construct(
        protected ConnectionOption $options,
    ) {
        $hostname = "{$options->host}:{$options->port}";

        $opt = [
            'timeout' => $options->timeout,
            'send_yield' => $options->sendYield,
            'tls' => $options->enableTls,
            'credentials' => ChannelCredentials::createInsecure(),
        ];

        if ($opt['tls']) {
            $opt['credentials'] = ChannelCredentials::createSsl(
                $options->tlsCert,
                $options->tlsKey,
                $options->tlsCa
            );
        }

        parent::__construct($hostname, $opt);
    }

    /**
     * Heartbeat request.
     */
    public function Heartbeat(HeartbeatRequest $request): ?HeartbeatResponse
    {
        [$response, $code, $raw] = $this->getResult(
            __FUNCTION__,
            $request,
            HeartbeatResponse::class
        );

        return $response;
    }

    /**
     * @deprecated
     *
     * PullMessage and ReceiveMessage RPCs serve a similar purpose,
     * which is to attempt to get messages from the server, but with different semantics.
     *
     * **This method is not implemented yet, use `ReceiveMessage` instead**
     */
    public function PullMessage(PullMessageRequest $request): ?PullMessageResponse
    {
        return null;
    }

    /**
     * Receive message from server.
     *
     * RocketMQ use streaming to receive message, so this method will return a channel.
     *
     * @throws RuntimeException
     */
    public function ReceiveMessage(ReceiveMessageRequest $request): ?Channel
    {
        $chan = new Channel($request->getBatchSize());

        $stream = $this->getDownStream(
            'ReceiveMessage',
            ReceiveMessageResponse::class,
            [
                'grpc-timeout' => '10S',
                'grpc-deadline' => '10S',
            ]
        );
        $stream->send($request);
        $stream->end();

        retry(3, fn () => Assert::positive($stream->getStreamId()), 100);

        Coroutine::create(function () use ($stream, $chan) {
            Coroutine::sleep(0.5);
            try {
                /**
                 * @var array{0: ReceiveMessageResponse, 1: int, 2: Response}
                 */
                $recv = $stream->recv();
                [$responses, $status, $raw] = $recv;
                if (! is_array($responses)) {
                    $responses = [$responses];
                }

                foreach ($responses as $response) {
                    if ($response instanceof ReceiveMessageResponse) {
                        if (! $response->hasMessage()) {
                            continue;
                        }
                        $chan->push($response->getMessage());
                    }
                }
                $chan->close();
            } catch (Throwable $th) {
                var_dump($th->getTraceAsString());
                Log::critical('! RocketMQ ReceiveMessage error: ' . $th->getMessage());
            }
        });

        return $chan;
    }

    /**
     * Acknowledges the message associated with the `receipt_handle` or `offset`
     * in the `AckMessageRequest`, it means the message has been successfully
     * processed. Returns `OK` if the message server remove the relevant message
     * successfully.
     *
     * If the given receipt_handle is illegal or out of date, returns
     * `INVALID_ARGUMENT`.
     */
    public function AckMessage(AckMessageRequest $request): ?AckMessageResponse
    {
        [$response, $code, $raw] = $this->getResult(
            __FUNCTION__,
            $request,
            AckMessageResponse::class
        );

        return $response;
    }

    /**
     * Forwards one message to dead letter queue if the max delivery attempts is
     * exceeded by this message at client-side, return `OK` if success.
     */
    public function ForwardMessageToDeadLetterQueue(ForwardMessageToDeadLetterQueueRequest $request): ?ForwardMessageToDeadLetterQueueResponse
    {
        [$response, $code, $raw] = $this->getResult(
            __FUNCTION__,
            $request,
            ForwardMessageToDeadLetterQueueResponse::class
        );

        return $response;
    }

    /**
     * Once a message is retrieved from consume queue on behalf of the group, it
     * will be kept invisible to other clients of the same group for a period of
     * time. The message is supposed to be processed within the invisible
     * duration. If the client, which is in charge of the invisible message, is
     * not capable of processing the message timely, it may use
     * ChangeInvisibleDuration to lengthen invisible duration.
     */
    public function ChangeInvisibleDuration(ChangeInvisibleDurationRequest $request): ?ChangeInvisibleDurationResponse
    {
        [$response, $code, $raw] = $this->getResult(
            __FUNCTION__,
            $request,
            ChangeInvisibleDurationResponse::class
        );

        return $response;
    }
}
