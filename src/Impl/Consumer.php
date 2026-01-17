<?php

declare(strict_types=1);
/**
 * Unofficial RocketMQ Client SDK for Hyperf
 *
 * @contact colisys@duck.com
 * @license Apache-2.0
 * @copyright 2025 Colisys
 */

namespace Colisys\RmqClient\Grpc\Impl;

use Apache\Rocketmq\V2\AckMessageEntry;
use Apache\Rocketmq\V2\AckMessageRequest;
use Apache\Rocketmq\V2\Address;
use Apache\Rocketmq\V2\AddressScheme;
use Apache\Rocketmq\v2\Assignment;
use Apache\Rocketmq\V2\ClientType;
use Apache\Rocketmq\V2\Endpoints;
use Apache\Rocketmq\V2\FilterExpression;
use Apache\Rocketmq\V2\ForwardMessageToDeadLetterQueueRequest;
use Apache\Rocketmq\V2\HeartbeatRequest;
use Apache\Rocketmq\V2\Language;
use Apache\Rocketmq\V2\Message;
use Apache\Rocketmq\V2\NotifyClientTerminationRequest;
use Apache\Rocketmq\V2\QueryAssignmentRequest;
use Apache\Rocketmq\V2\ReceiveMessageRequest;
use Apache\Rocketmq\V2\Settings;
use Apache\Rocketmq\V2\Subscription;
use Apache\Rocketmq\V2\SubscriptionEntry;
use Apache\Rocketmq\V2\TelemetryCommand;
use Apache\Rocketmq\V2\UA;
use Closure;
use Colisys\RmqClient\Grpc\Client\ConsumerClient;
use Colisys\RmqClient\Grpc\Constant\MessageConsumeStatus;
use Colisys\RmqClient\Grpc\Constant\SDK;
use Colisys\RmqClient\Grpc\Contract\ConnectionOption;
use Colisys\RmqClient\Grpc\Factory\ConsumerFactory;
use Colisys\RmqClient\Grpc\View\MessageView;
use Colisys\Rocketmq\Helper\Arr;
use Colisys\Rocketmq\Helper\Log;
use Exception;
use Hyperf\Context\ApplicationContext;
use Hyperf\Coordinator\CoordinatorManager;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Throwable;

use function Colisys\Rocketmq\Helper\duration;
use function Colisys\Rocketmq\Helper\resource;
use function Colisys\Rocketmq\Helper\timestamp;
use function Colisys\Rocketmq\Helper\timestamp_diff;
use function Hyperf\Support\make;

class Consumer
{
    /**
     * @var Arr<Arr<Assignment>>
     */
    private Arr $route;

    private Channel $penddingEntry;

    /**
     * @param ClientType $clientType
     * @param array<Closure(MessageView $message): MessageConsumeStatus> $listeners
     * @param array<string, FilterExpression> $topics
     */
    public function __construct(
        protected ConnectionOption $options,
        protected string $consumerGroup,
        protected int $clientType,
        protected array $topics,
        protected ?Settings $settings = null,
        protected array $listeners = [],
        protected int $batchSize = 32,
    ) {
        if (! isset($settings)) {
            $this->settings = new Settings([
                'client_type' => $clientType,
                'user_agent' => new UA([
                    'language' => Language::PHP,
                    'version' => SDK::SDK_VERSION,
                    'platform' => PHP_OS,
                    'hostname' => gethostname(),
                ]),
                'subscription' => new Subscription([
                    'group' => resource($consumerGroup, $options->namespace),
                    'receive_batch_size' => $batchSize,
                    'long_polling_timeout' => duration(30),
                ]),
            ]);
        }

        if ($clientType == ClientType::PUSH_CONSUMER) {
            $this->settings->setRequestTimeout(
                duration(10)
            );
        }
        $this->penddingEntry = new Channel(count($topics));
        $this->route = Arr::fromArray([]);
    }

    public function start()
    {
        foreach ($this->topics as $topic => $expression) {
            $this->settings
                ->getSubscription()
                ->getSubscriptions()
                ->offsetSet(
                    null,
                    $entry = new SubscriptionEntry([
                        'topic' => resource($topic, $this->options->namespace),
                        'expression' => $expression,
                    ])
                );
            $this->penddingEntry->push($entry);
        }
        [$tx, $rx] = $this->useClient(
            fn ($client) => $client->Telemetry()
        );
        $tx->push(
            new TelemetryCommand([
                'settings' => $this->settings,
            ])
        );
        // For Heartbeat
        Coroutine::create(function () use ($rx) {
            $response = $rx->pop($this->options->startupTimeout);
            if ($response !== false) {
                Log::info("* RocketMQ info: Consumer #{$this->options->clientId} started");
                CoordinatorManager::until("{$this->options->clientId}.started")->resume();
                while (true) {
                    try {
                        $this->queryAssignment();
                        $this->useClient(
                            fn ($client) => $client->Heartbeat(
                                new HeartbeatRequest([
                                    'group' => $this->settings->getSubscription()->getGroup(),
                                    'client_type' => $this->clientType,
                                ])
                            )
                        );
                    } catch (Throwable $th) {
                        Log::critical("* RocketMQ error: Consumer #{$this->options->clientId} heartbeat failed, reason=" . $th->getMessage());
                    }
                    Coroutine::sleep($this->options->heartbeatInterval);
                }
            }
            if ($response !== false) {
                Log::critical("* RocketMQ error: Consumer #{$this->options->clientId} start failed, reason=" . ($response->getStatus()?->getMessage() ?? 'Coroutine internal exception'));
            }
            throw new Exception('RocketMQ error: Consumer heartbeat coroutine broken');
        });
        // For ReceiveMessage
        Coroutine::create(function () {
            CoordinatorManager::until("{$this->options->clientId}.started")->yield($this->options->startupTimeout);
            while (true) {
                while ($entry = $this->penddingEntry->pop(0.5)) {
                    if ($entry === false) {
                        break;
                    }
                    Log::debug("* RocketMQ debug: Consumer #{$this->options->clientId} receiving message");
                    try {
                        $this->receiveMessage($entry);
                    } catch (Throwable $th) {
                        Log::critical("* RocketMQ error: Consumer #{$this->options->clientId} receive message failed, reason=" . $th->getMessage());
                    }
                }
                Log::debug("* RocketMQ debug: Consumer #{$this->options->clientId} receive message coroutine sleep 0.5s");
                Coroutine::sleep(0.5);
            }
            throw new Exception('RocketMQ error: Consumer receive coroutine broken');
        });
    }

    public function queryAssignment()
    {
        $subscriptions = Arr::fromRepeatField($this->settings->getSubscription()->getSubscriptions(), SubscriptionEntry::class);
        $subscriptions->each(function ($subscription) {
            Coroutine::create(function (SubscriptionEntry $subscription) {
                Log::info("* RocketMQ info: Consumer #{$this->options->clientId} query assignment for topic {$subscription->getTopic()->getName()}");
                $result = $this->useClient(
                    fn ($client) => $client->QueryAssignment(
                        new QueryAssignmentRequest([
                            'topic' => $subscription->getTopic(),
                            'group' => $this->settings->getSubscription()->getGroup(),
                            'endpoints' => new Endpoints([
                                'scheme' => AddressScheme::IPv4,
                                'addresses' => [
                                    new Address([
                                        'host' => $this->options->host,
                                        'port' => $this->options->port,
                                    ]),
                                ],
                            ]),
                        ])
                    )
                );

                $assignments = Arr::fromRepeatField($result->getAssignments(), Assignment::class);
                Log::info("* RocketMQ info: Consumer #{$this->options->clientId} query assignment for topic {$subscription->getTopic()->getName()} success, assignment counts=" . $assignments->count());
                $topicName = $subscription->getTopic()->getName();
                if (! $this->route->has($topicName)) {
                    $this->route->set($topicName, Arr::fromArray([]));
                }
                $this->route->get($topicName)->append($assignments);
                CoordinatorManager::until($topicName . '.assignment')->resume();
            }, $subscription);
        });
    }

    public function receiveMessage(SubscriptionEntry $subscription)
    {
        if (! $this->route->has($subscription->getTopic()->getName())) {
            CoordinatorManager::until($subscription->getTopic()->getName() . '.assignment')->yield(-1);
        }

        $this->useClient(
            function ($client) use ($subscription) {
                $size = $this->batchSize;
                while ($size == $this->batchSize) {
                    $beginTime = timestamp();
                    $ch = $client->ReceiveMessage(
                        new ReceiveMessageRequest([
                            'group' => resource($this->consumerGroup, $this->options->namespace),
                            'batch_size' => $this->batchSize,
                            'filter_expression' => $subscription->getExpression(),
                            'message_queue' => $this->route->get($subscription->getTopic()->getName())->random()->getMessageQueue(),
                            'invisible_duration' => duration($this->options->invisibleTime),
                        ])
                    );

                    $request = new AckMessageRequest([
                        'group' => resource($this->consumerGroup, $this->options->namespace),
                        'topic' => $subscription->getTopic(),
                        'entries' => [],
                    ]);

                    $size = 0;
                    while ($message = $ch->pop($this->options->pollTimeout * 2)) {
                        if ($message instanceof Message) {
                            ++$size;
                            $view = new MessageView($message);
                            foreach ($this->listeners as $listener) {
                                $result = $listener($view);

                                if ($result === MessageConsumeStatus::CONSUME_SUCCESS) {
                                    $request->getEntries()->offsetSet(null, new AckMessageEntry([
                                        'message_id' => $message->getSystemProperties()->getMessageId(),
                                        'receipt_handle' => $message->getSystemProperties()->getReceiptHandle(),
                                    ]));
                                }

                                if ($result === MessageConsumeStatus::FORWARD_DLQ) {
                                    $this->useClient(
                                        fn ($client) => $client->ForwardMessageToDeadLetterQueue(
                                            new ForwardMessageToDeadLetterQueueRequest([
                                                'group' => resource($this->consumerGroup, $this->options->namespace),
                                                'topic' => $subscription->getTopic(),
                                                'receipt_handle' => $message->getSystemProperties()->getReceiptHandle(),
                                                'message_id' => $message->getSystemProperties()->getMessageId(),
                                                'delivery_attempt' => $message->getSystemProperties()->getDeliveryAttempt() + 1,
                                            ])
                                        )
                                    );
                                    break;
                                }
                            }
                        }
                    }

                    if ($request->getEntries()->count() > 0) {
                        $this->useClient(fn ($client) => $client->AckMessage($request));
                    }
                    $endTime = timestamp();
                }
                $time = max(0.05, $this->options->pollTimeout - timestamp_diff($beginTime, $endTime));
                Log::debug("* RocketMQ debug: sleep for {$time}s");
                Coroutine::sleep($time);
                $this->penddingEntry->push($subscription);
            }
        );
    }

    public function shutdown()
    {
        Log::info("* RocketMQ info: Consumer #{$this->options->clientId} shutdown");
        $this->useClient(
            fn ($client) => $client->NotifyClientTermination(
                new NotifyClientTerminationRequest([
                    'group' => resource($this->consumerGroup, $this->options->namespace),
                ])
            )
        );
    }

    /**
     * @param Closure(ConsumerClient $client): mixed $callback
     */
    private function useClient(callable $callback)
    {
        return make(ConsumerFactory::class, [
            'container' => $this->container ?? ApplicationContext::getContainer(),
        ])->using($this->options, $callback);
    }
}
