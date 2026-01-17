<?php

declare(strict_types=1);
/**
 * Unofficial RocketMQ Client SDK for Hyperf
 *
 * @contact colisys@duck.com
 * @license Apache-2.0
 * @copyright 2025 Colisys
 */

namespace Colisys\RmqClient\Grpc\Contract;

use Apache\Rocketmq\V2\GetOffsetRequest;
use Apache\Rocketmq\V2\GetOffsetResponse;
use Apache\Rocketmq\V2\NotifyClientTerminationRequest;
use Apache\Rocketmq\V2\NotifyClientTerminationResponse;
use Apache\Rocketmq\V2\QueryAssignmentRequest;
use Apache\Rocketmq\V2\QueryAssignmentResponse;
use Apache\Rocketmq\V2\QueryOffsetRequest;
use Apache\Rocketmq\V2\QueryOffsetResponse;
use Apache\Rocketmq\V2\QueryRouteRequest;
use Apache\Rocketmq\V2\QueryRouteResponse;
use Apache\Rocketmq\V2\RecallMessageRequest;
use Apache\Rocketmq\V2\RecallMessageResponse;
use Apache\Rocketmq\V2\TelemetryCommand;
use Apache\Rocketmq\V2\UpdateOffsetRequest;
use Apache\Rocketmq\V2\UpdateOffsetResponse;
use Closure;
use Colisys\RmqClient\Grpc\Override\BaseClient;
use Colisys\RmqClient\Grpc\Override\ServerStreamCall;
use Colisys\RmqClient\Grpc\Override\StreamingCall;
use Colisys\Rocketmq\Helper\Log;
use Error;
use Exception;
use Hyperf\Coordinator\Constants;
use Hyperf\Coordinator\CoordinatorManager;
use Hyperf\Engine\Channel;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Swoole\Coroutine;
use Swoole\Http2\Response;
use Throwable;

/**
 * @method Channel Telemetry()
 * @method ?QueryRouteResponse QueryRoute(QueryRouteRequest $request)
 * @method ?QueryAssignmentResponse QueryAssignment(QueryAssignmentRequest $request)
 * @method ?UpdateOffsetResponse UpdateOffset(UpdateOffsetRequest $request)
 * @method ?GetOffsetResponse GetOffset(GetOffsetRequest $request)
 * @method ?QueryOffsetResponse QueryOffset(QueryOffsetRequest $request)
 * @method ?NotifyClientTerminationResponse NotifyClientTermination(NotifyClientTerminationRequest $request)
 * @method ?RecallMessageResponse RecallMessage(RecallMessageRequest $request)
 */
abstract class ClientWrapper extends BaseClient
{
    public ?LoggerInterface $logger;

    /**
     * @var array<int, Closure(callable, string, mixed, string, array, array): array{service: string, message: mixed, decoder: string, metadata: array, options: array}>
     */
    public array $beforeRequest = [];

    /**
     * @var array<int, Closure(callable, string, string, array, array): array{service: string, decoder: string, metadata: array, options: array}>
     */
    public array $beforeStream = [];

    protected string $package = '/apache.rocketmq.v2';

    protected string $service = 'MessagingService';

    public function getServiceByName(string $name)
    {
        return "{$this->package}.{$this->service}/{$name}";
    }

    /**
     * Once a client starts, it would immediately establishes bi-lateral stream
     * RPCs with brokers, reporting its settings as the initiative command.
     *
     * When servers have need of inspecting client status, they would issue
     * telemetry commands to clients. After executing received instructions,
     * clients shall report command execution results through client-side streams.
     *
     * @return array{0: Channel, 1: Channel}
     * @throws RuntimeException
     */
    public function Telemetry(): array
    {
        $tx = new Channel();
        $rx = new Channel();

        if (Coroutine::create(
            /**
             * Need reverse the order of the channel.
             *
             * tx -> rx
             * rx -> tx
             */
            function (Channel $rx, Channel $tx) {
                while ($rx->isAvailable()) {
                    try {
                        $stream = $this->getStream('Telemetry', TelemetryCommand::class);

                        $send = $rx->pop(0.5);

                        if ($send instanceof TelemetryCommand) {
                            $stream->send($send);

                            /**
                             * @var array{0: TelemetryCommand, 1: int, 2: Response} $data
                             */
                            $data = $stream->recv();
                            [$response, $code, $raw] = $data;
                            if ($response instanceof TelemetryCommand) {
                                $tx->push($response);
                            } else {
                                Log::critical('! RocketMQ error capture: Telemetry error, code=' . $raw->headers['grpc-status'] . ', message=' . $raw->headers['grpc-message']);
                            }
                            continue;
                        }

                        $recv = $stream->recv(0.5);
                        if ($recv instanceof TelemetryCommand) {
                            $tx->push($recv);
                        }
                    } catch (Throwable $th) {
                        // Log::debug('! RocketMQ Telemetry exception, message=' . $th->getMessage() . ', stream closed?');
                        // CoordinatorManager::until(Constants::WORKER_EXIT)->yield(-1);
                        Coroutine::sleep(1);
                    }
                }
            },
            $tx,
            $rx
        ) === false) {
            throw new RuntimeException('Failed to create coroutine');
        }
        return [$tx, $rx];
    }

    /**
     * Queries the route entries of the requested topic in the perspective of the
     * given endpoints. On success, servers should return a collection of
     * addressable message-queues. Note servers may return customized route
     * entries based on endpoints provided.
     *
     * If the requested topic doesn't exist, returns `NOT_FOUND`.
     *
     * If the specific endpoints is empty, returns `INVALID_ARGUMENT`.
     *
     * @see Apache\Rocketmq\V2\QueryRouteRequest
     * @see Apache\Rocketmq\V2\QueryRouteResponse
     */
    public function QueryRoute(QueryRouteRequest $request): ?QueryRouteResponse
    {
        /**
         * @var QueryRouteResponse $response
         * @var int $code
         * @var Response $raw
         */
        [$response, $code, $raw] = $this->getResult(
            __FUNCTION__,
            $request,
            QueryRouteResponse::class,
        );

        return $response;
    }

    /**
     * Producer or consumer sends HeartbeatRequest to servers periodically to
     * keep-alive. Additionally, it also reports client-side configuration,
     * including topic subscription, load-balancing group name, etc.
     *
     * Returns `OK` if success.
     *
     * If a client specifies a language that is not yet supported by servers,
     * returns `INVALID_ARGUMENT`
     *
     * @see Apache\Rocketmq\V2\QueryAssignmentRequest
     * @see Apache\Rocketmq\V2\QueryAssignmentResponse
     */
    public function QueryAssignment(QueryAssignmentRequest $request): ?QueryAssignmentResponse
    {
        /**
         * @var QueryAssignmentResponse $response
         * @var int $code
         * @var Response $raw
         */
        [$response, $code, $raw] = $this->getResult(
            __FUNCTION__,
            $request,
            QueryAssignmentResponse::class,
        );

        return $response;
    }

    /**
     * Update the consumption progress of the designated queue of the
     * consumer group to the remote.
     *
     * @see Apache\Rocketmq\V2\UpdateOffsetRequest
     * @see Apache\Rocketmq\V2\UpdateOffsetResponse
     */
    public function UpdateOffset(UpdateOffsetRequest $request): ?UpdateOffsetResponse
    {
        /**
         * @var UpdateOffsetResponse $response
         * @var int $code
         * @var Response $raw
         */
        [$response, $code, $raw] = $this->getResult(
            __FUNCTION__,
            $request,
            UpdateOffsetResponse::class,
        );

        return $response;
    }

    /**
     * Query the consumption progress of the designated queue of the
     * consumer group to the remote.
     *
     * @see Apache\Rocketmq\V2\GetOffsetRequest
     * @see Apache\Rocketmq\V2\GetOffsetResponse
     */
    public function GetOffset(GetOffsetRequest $request): ?GetOffsetResponse
    {
        /**
         * @var GetOffsetResponse $response
         * @var int $code
         * @var Response $raw
         */
        [$response, $code, $raw] = $this->getResult(
            __FUNCTION__,
            $request,
            GetOffsetResponse::class,
        );

        return $response;
    }

    /**
     * Query the offset of the designated queue by the query offset policy.
     *
     * @see Apache\Rocketmq\V2\QueryOffsetRequest
     * @see Apache\Rocketmq\V2\QueryOffsetResponse
     */
    public function QueryOffset(QueryOffsetRequest $request): ?QueryOffsetResponse
    {
        /**
         * @var QueryOffsetResponse $response
         * @var int $code
         * @var Response $raw
         */
        [$response, $code, $raw] = $this->getResult(
            __FUNCTION__,
            $request,
            QueryOffsetResponse::class,
        );

        return $response;
    }

    /**
     * Notify the server that the client is terminated.
     *
     * @see Apache\Rocketmq\V2\NotifyClientTerminationRequest
     * @see Apache\Rocketmq\V2\NotifyClientTerminationResponse
     */
    public function NotifyClientTermination(NotifyClientTerminationRequest $request): ?NotifyClientTerminationResponse
    {
        /**
         * @var NotifyClientTerminationResponse $response
         * @var int $code
         * @var Response $raw
         */
        [$response, $code, $raw] = $this->getResult(
            __FUNCTION__,
            $request,
            NotifyClientTerminationResponse::class,
        );

        return $response;
    }

    /**
     * Recall a message,.
     *
     * for delay message, should recall before delivery time, like the rollback operation of transaction message,
     *
     * for normal message, not supported for now.
     *
     * @see Apache\Rocketmq\V2\RecallMessageRequest
     * @see Apache\Rocketmq\V2\RecallMessageResponse
     */
    public function RecallMessage(RecallMessageRequest $request): ?RecallMessageResponse
    {
        /**
         * @var RecallMessageResponse $response
         * @var int $code
         * @var Response $raw
         */
        [$response, $code, $raw] = $this->getResult(
            __FUNCTION__,
            $request,
            RecallMessageResponse::class,
        );

        return $response;
    }

    /**
     * Get upside stream. Which is means that the client will send series of request to server,
     * then server will send single response back.
     *
     * @template TStream
     *
     * @param class-string<TStream> $decoder
     */
    protected function getUpStream(
        string $service,
        string $decoder,
        array $metadata = [],
        array $options = []
    ) {
        [
            $service,
            $decoder,
            $metadata,
            $options,
        ] = $this->doMiddleware(
            $this->beforeStream,
            $service,
            $decoder,
            $metadata,
            $options
        );

        return $this->_clientStreamRequest(
            $this->getServiceByName($service),
            [$decoder, 'decode'],
            $metadata,
            $options
        );
    }

    /**
     * Get downside stream. Which is means that the client will send request to server,
     * then server will send series of response.
     *
     * @template TStream
     *
     * @param class-string<TStream> $decoder
     */
    protected function getDownStream(
        string $service,
        string $decoder,
        array $metadata = [],
        array $options = []
    ) {
        [
            $service,
            $decoder,
            $metadata,
            $options,
        ] = $this->doMiddleware(
            $this->beforeStream,
            $service,
            $decoder,
            $metadata,
            $options
        );

        $call = new ServerStreamCall();
        $call->setClient($this->_getGrpcClient())
            ->setDeserialize([$decoder, 'decode'])
            ->setMetadata($metadata)
            ->setMethod($this->getServiceByName($service));
        return $call;
    }

    /**
     * Get bidirectional stream.
     *
     * @template TStream
     *
     * @param class-string<TStream> $decoder
     */
    protected function getStream(
        string $service,
        string $decoder,
        array $metadata = [],
        array $options = []
    ) {
        [
            $service,
            $decoder,
            $metadata,
            $options,
        ] = $this->doMiddleware(
            $this->beforeStream,
            $service,
            $decoder,
            $metadata,
            $options
        );

        $call = new StreamingCall();
        $call->setClient($this->_getGrpcClient())
            ->setMethod($this->getServiceByName($service))
            ->setDeserialize([$decoder, 'decode'])
            ->setMetadata($metadata);
        return $call;
    }

    /**
     * @template TResult
     *
     * @param mixed $message
     * @param class-string<TResult> $decoder
     * @return array{?TResult, int, Response}
     */
    protected function getResult(
        string $service,
        $message,
        string $decoder,
        array $metadata = [],
        array $options = []
    ) {
        [
            $service,
            $message,
            $decoder,
            $metadata,
            $options,
        ] = $this->doMiddleware(
            $this->beforeRequest,
            $service,
            $message,
            $decoder,
            $metadata,
            $options
        );

        $ch = new Channel();
        Coroutine::create(
            function () use (
                $service,
                $message,
                $decoder,
                $metadata,
                $options,
                $ch,
            ) {
                try {
                    $ch->push(
                        $this->_simpleRequest(
                            $this->getServiceByName($service),
                            $message,
                            [$decoder, 'decode'],
                            $metadata,
                            $options
                        )
                    );
                    $this->close();
                } catch (Throwable $e) {
                    $ch->push([$e->getMessage(), $e->getCode(), null]);
                }
            }
        );

        [$response, $code, $raw] = $ch->pop();
        if (is_scalar($response)) {
            throw new Error($response);
        }
        return [$response, $code, $raw];
    }

    private function doMiddleware(array $chain, ...$args)
    {
        if (empty($chain)) {
            return $args;
        }

        $middleware = array_shift($chain);
        return $middleware($next = function (...$args) use ($chain) {
            return $this->doMiddleware($chain, ...$args);
        }, ...$args);
    }
}
