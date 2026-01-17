<?php

declare(strict_types=1);
/**
 * Third-party RocketMQ Client SDK for Hyperf
 *
 * @contact colisys@duck.com
 * @license MIT
 * @copyright 2025 Colisys
 */

namespace Colisys\RmqClient\Grpc\Contract;

use Apache\Rocketmq\V2\ClientType;
use Colisys\RmqClient\Grpc\Constant\Protocol;
use Hyperf\GrpcClient\GrpcClient;

/**
 * @property string $host RocketMQ Proxy host, default is `localhost`
 * @property int $port RocketMQ Proxy port, default is `8081`
 * @property string $clientId Used to identify the client, default is `php-rocketmq`
 * @property string $namespace RocketMQ namespace, default is empty
 * @property int $clientType RocketMQ client type, default is `CLIENT_TYPE_UNSPECIFIED`
 * @property string $accessKey RocketMQ access key, i.e. "username", default is empty
 * @property string $secretKey RocketMQ secret key, i.e. "password", default is empty
 * @property string $sessionToken RocketMQ session token, default is empty
 * @property bool $enableTls Whether to enable TLS, default is `false`
 * @property string $tlsCert TLS certificate file path, default is empty
 * @property string $tlsKey TLS private key file path, default is empty
 * @property string $tlsCa TLS CA certificate file path, default is empty
 * @property float $timeout RPC timeout, default is `3.0s`
 * @property bool $sendYield Whether to yield when sending messages, default is `false`
 * @property float $sendTimeout Send timeout, default is `-1`, which means no timeout
 * @property float $heartbeatInterval Heartbeat interval, min is `10.0s`
 * @property float $pollTimeout Polling timeout, min is `10.0s`
 * @property float $invisibleTime Invisible time, min is `10.0s`
 * @property float $startupTimeout Startup timeout, min is `30.0s`
 */
class ConnectionOption
{
    // TODO This option is unimplemented yet
    public Protocol $protocol = Protocol::gRPC;

    public string $host = 'localhost';

    public int $port = 8081;

    public string $clientId = 'php-rocketmq';

    public string $namespace = '';

    public int $clientType = ClientType::CLIENT_TYPE_UNSPECIFIED;

    public string $accessKey = '';

    public string $secretKey = '';

    public string $sessionToken = '';

    public bool $enableTls = false;

    public string $tlsCert = '';

    public string $tlsKey = '';

    public string $tlsCa = '';

    public float $timeout = GrpcClient::GRPC_DEFAULT_TIMEOUT;

    public bool $sendYield = false;

    public float $sendTimeout = -1.0;

    public float $heartbeatInterval = 10.0;

    public float $pollTimeout = 10.0;

    public float $invisibleTime = 10.0;

    public float $startupTimeout = 30.0;

    // TODO This option is unimplemented yet
    public bool $enableSlaveRead = false;
}
