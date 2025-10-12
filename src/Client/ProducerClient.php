<?php

declare(strict_types=1);
/**
 * Third-party RocketMQ Client SDK for Hyperf
 *
 * @contact colisys@duck.com
 * @license MIT
 * @copyright 2025 Colisys
 */

namespace Colisys\Rocketmq\Client;

use Apache\Rocketmq\V2\EndTransactionRequest;
use Apache\Rocketmq\V2\EndTransactionResponse;
use Apache\Rocketmq\V2\SendMessageRequest;
use Apache\Rocketmq\V2\SendMessageResponse;
use Colisys\Rocketmq\Contract\ClientWrapper;
use Colisys\Rocketmq\Contract\ConnectionOption;
use Grpc\ChannelCredentials;

/**
 * @see ClientWrapper
 * @method ?SendMessageResponse SendMessage(SendMessageRequest $request)
 * @method ?EndTransactionResponse EndTransaction(EndTransactionRequest $request)
 */
class ProducerClient extends ClientWrapper
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
     * To send messages to a topic.
     */
    public function SendMessage(SendMessageRequest $request): ?SendMessageResponse
    {
        [$response, $code, $raw] = $this->getResult(
            __FUNCTION__,
            $request,
            SendMessageResponse::class,
            [],
            []
        );

        return $response;
    }

    /**
     * End a transaction.
     */
    public function EndTransaction(EndTransactionRequest $request): ?EndTransactionResponse
    {
        [$response, $code, $raw] = $this->getResult(
            __FUNCTION__,
            $request,
            EndTransactionResponse::class,
            [],
            []
        );

        return $response;
    }
}
