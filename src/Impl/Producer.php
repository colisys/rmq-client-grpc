<?php

declare(strict_types=1);
/**
 * Third-party RocketMQ Client SDK for Hyperf
 *
 * @contact colisys@duck.com
 * @license MIT
 * @copyright 2025 Colisys
 */

namespace Colisys\RocketmqClient\Grpc\Impl;

use Apache\Rocketmq\V2\ClientType;
use Apache\Rocketmq\V2\EndTransactionRequest;
use Apache\Rocketmq\V2\Message;
use Apache\Rocketmq\V2\NotifyClientTerminationRequest;
use Apache\Rocketmq\V2\Publishing;
use Apache\Rocketmq\V2\Resource;
use Apache\Rocketmq\V2\SendMessageRequest;
use Apache\Rocketmq\V2\SendResultEntry;
use Apache\Rocketmq\V2\Settings;
use Apache\Rocketmq\V2\TelemetryCommand;
use Apache\Rocketmq\V2\TransactionResolution;
use Apache\Rocketmq\V2\TransactionSource;
use Closure;
use Colisys\RocketmqClient\Grpc\Builder\SimpleMessageBuilder;
use Colisys\RocketmqClient\Grpc\Builder\TransactionMessageBuilder;
use Colisys\RocketmqClient\Grpc\Client\ProducerClient;
use Colisys\RocketmqClient\Grpc\Constant\TransactionResult;
use Colisys\RocketmqClient\Grpc\Contract\ConnectionOption;
use Colisys\RocketmqClient\Grpc\Contract\MessageBuilder;
use Colisys\RocketmqClient\Grpc\Factory\ProducerFactory;
use Colisys\Rocketmq\Helper\Arr;
use Colisys\Rocketmq\Helper\Log;
use Colisys\Rocketmq\Helper\Set;
use Colisys\RocketmqClient\Grpc\View\MessageView;
use Hyperf\Context\ApplicationContext;
use Hyperf\Coordinator\Constants;
use Hyperf\Coordinator\CoordinatorManager;
use Hyperf\Engine\Channel;
use Swoole\Coroutine;

use function Hyperf\Support\make;

class Producer
{
    /**
     * @param null|callable $transactionChecker
     */
    public function __construct(
        protected ConnectionOption $options,
        protected ?Settings $settings = null,
        protected int $maxAttempts = 3,
        protected ?Set $topics = null,
        protected $transactionChecker = null,
    ) {
        $topics = $topics->toArray();
        if (! isset($settings)) {
            $this->settings = new Settings([
                'client_type' => ClientType::PRODUCER,
                'publishing' => new Publishing([
                    'topics' => [],
                ]),
            ]);
        }

        foreach ($topics as $topic) {
            $this->settings->getPublishing()->getTopics()->offsetSet(
                null,
                new Resource([
                    'name' => $topic,
                    'resource_namespace' => $options->namespace,
                ])
            );
        }
    }

    /**
     * leave it for future use.
     */
    public function start()
    {
        if (isset($this->transactionChecker)) {
            [$tx, $rx] = $this->useClient(fn ($client) => $client->Telemetry());

            Coroutine::create(function () use ($tx) {
                $tx->push(
                    new TelemetryCommand([
                        'settings' => $this->settings,
                    ])
                );
            });

            Coroutine::create(function () use ($rx) {
                while (true) {
                    $cmd = $rx->pop();
                    if ($cmd instanceof TelemetryCommand && $cmd->hasRecoverOrphanedTransactionCommand()) {
                        $view = new MessageView($cmd->getRecoverOrphanedTransactionCommand()->getMessage());
                        $result = call_user_func($this->transactionChecker, $view);
                        if ($result instanceof TransactionResult) {
                            $this->useClient(
                                fn ($client) => $client->EndTransaction(
                                    new EndTransactionRequest([
                                        'topic' => $view->rawMessage->getTopic(),
                                        'message_id' => $view->id,
                                        'transaction_id' => $cmd->getRecoverOrphanedTransactionCommand()->getTransactionId(),
                                        'resolution' => match ($result) {
                                            TransactionResult::COMMIT => TransactionResolution::COMMIT,
                                            TransactionResult::ROLLBACK => TransactionResolution::ROLLBACK,
                                            default => TransactionResolution::ROLLBACK,
                                        },
                                        'source' => TransactionSource::SOURCE_SERVER_CHECK,
                                    ])
                                )
                            );
                        }
                    }
                    CoordinatorManager::until(Constants::WORKER_EXIT)->yield(0.1);
                }
            });
        }
    }

    /**
     * leave it for future use.
     */
    public function shutdown()
    {
        Log::info('* RocketMQ info: Producer shutdown');
        $request = new NotifyClientTerminationRequest([]);
        $this->useClient(fn ($client) => $client->NotifyClientTermination($request));
    }

    public function sentAsync(Message|MessageBuilder $messages): void
    {
        Coroutine::create(function () use ($messages) {
            $this->send($messages);
        });
    }

    /**
     * Send message to broker in batch manner.
     *
     * This will filter transaction message and invoke transaction.
     *
     * Only return the result of non-transaction message.
     *
     * @see https://github.com/apache/rocketmq/discussions/9646 issue about batch message
     */
    public function send(Message|MessageBuilder ...$messages): Channel
    {
        $chan = new Channel();
        $request = new SendMessageRequest([
            'messages' => [],
        ]);

        Arr::fromArray($messages, TransactionMessageBuilder::class)->each(
            function (TransactionMessageBuilder $builder) use ($chan) {
                Coroutine::create(function (TransactionMessageBuilder $builder) use ($chan) {
                    $request = new SendMessageRequest(['messages' => []]);
                    $request->getMessages()->offsetSet(null, $builder->build()->getMessage());
                    $response = $this->useClient(fn ($client) => $client->SendMessage($request));
                    $chan->push($response);
                    $entry = Arr::fromRepeatField($response->getEntries(), SendResultEntry::class)->first();
                    $transactionResult = $builder->invokeTransaction();
                    $this->useClient(fn ($client) => $client->EndTransaction(
                        new EndTransactionRequest([
                            'topic' => $builder->getMessage()->getTopic(),
                            'message_id' => $entry->getMessageId(),
                            'transaction_id' => $entry->getTransactionId(),
                            'source' => TransactionSource::SOURCE_CLIENT,
                            'resolution' => match ($transactionResult) {
                                TransactionResult::COMMIT => TransactionResolution::COMMIT,
                                TransactionResult::ROLLBACK => TransactionResolution::ROLLBACK,
                                default => TransactionResolution::ROLLBACK,
                            },
                        ])
                    ));
                }, $builder);
            }
        );

        Arr::fromArray($messages, Message::class)
            ->append(
                Arr::fromArray($messages, SimpleMessageBuilder::class)
                    ->map(
                        fn (SimpleMessageBuilder $builder) => $builder->build()->getMessage()
                    )
            )->each(
                fn ($message) => $request->getMessages()->offsetSet(null, $message)
            );

        Coroutine::create(
            fn () => $chan->push($this->useClient(
                fn ($client) => $client->SendMessage($request)
            ))
        );

        return $chan;
    }

    /**
     * @template TReturn
     * @param Closure(ProducerClient $client): TReturn $callback
     * @return TReturn
     */
    private function useClient(callable $callback)
    {
        return make(ProducerFactory::class, [
            'container' => $this->container ?? ApplicationContext::getContainer(),
        ])->using($this->options, $callback);
    }
}
