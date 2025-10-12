# RocketMQ SDK

这是一个用于发送和接收消息的 RocketMQ 第三方 SDK，仅支持 Hyperf 框架，支持 gRPC 和 Remoting 双协议。

## 安装

```bash
composer require colisys/rocketmq-client-php
```

## 快速开始

**所有功能都需要启用 `Coroutine`（协程）。**

> 目前适配的框架为 `Hyperf`，该 SDK 目前正在快速迭代中，API 均是**不稳定**的。

### 生产者（Producer）

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use Apache\Rocketmq\V2\SendResultEntry;
use Colisys\Rocketmq\Annotation\Producer;
use Colisys\Rocketmq\Builder\ProducerBuilder;
use Colisys\Rocketmq\Builder\SimpleMessageBuilder;
use Colisys\Rocketmq\Contract\ConnectionOption;
use Colisys\Rocketmq\Contract\MessageBuilder;
use Colisys\Rocketmq\Helper\Arr;
use Colisys\Rocketmq\Helper\Log;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\GetMapping;

use function Colisys\Rocketmq\Helper	imestamp;
use function Colisys\Rocketmq\Helper	imestamp_diff;

#[Controller()]
class IndexController extends AbstractController
{
    public function index()
    {
        $beginTime = timestamp();
        // 手动发送消息，支持批量发送
        $producer = ProducerBuilder::make(new ConnectionOption())->build();
        Arr::fromRepeatField(
            $producer->send(
                SimpleMessageBuilder::make()
                    ->normal()
                    ->addKey('key1')
                    ->setTopic('TopicTest')
                    ->setTag('TagA')
                    ->setBody('HelloA')
                    ->build(),
                SimpleMessageBuilder::make()
                    ->normal()
                    ->addKey('key2')
                    ->setTopic('TopicTest')
                    ->setTag('TagB')
                    ->setBody('HelloB')
                    ->build(),
                SimpleMessageBuilder::make()
                    ->normal()
                    ->addKey('key3')
                    ->setTopic('TopicTest')
                    ->setTag('TagC')
                    ->setBody('HelloC')
                    ->build()
            )->pop()
                ->getEntries(),
            SendResultEntry::class
        )->each(fn ($v) => Log::debug("* RocketMQ debug: Producer 发送了消息#{$v->getMessageId()}"));
        return [
            'time' => timestamp_diff($beginTime, timestamp()),
        ];
    }

    #[GetMapping()]
    public function annotation()
    {
        $beginTime = timestamp();
        // 从 AOP 角度来看，发送方法将被自动调用
        // 适合发送单条消息
        $this->sendAnnotation();
        return [
            'time' => timestamp_diff($beginTime, timestamp()),
        ];
    }

    #[Producer()]
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
```

### 消费者（Consumer）

```php
<?php

declare(strict_types=1);

namespace App\Listener;

use Colisys\Rocketmq\Builder\ConsumerBuilder;
use Colisys\Rocketmq\Constant\MessageConsumeStatus;
use Colisys\Rocketmq\Contract\ConnectionOption;
use Colisys\Rocketmq\Helper\Log;
use Colisys\Rocketmq\View\MessageView;
use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\MainWorkerStart;
use Psr\Container\ContainerInterface;

#[Listener]
class ServerStartListener implements ListenerInterface
{
    public function __construct(protected ContainerInterface $container)
    {
    }

    public function listen(): array
    {
        return [
            MainWorkerStart::class,
        ];
    }

    public function process(object $event): void
    {
        $options = new ConnectionOption();
        ConsumerBuilder::make($options)
            ->setConsumerGroup('consumerGroup')
            ->addTopicWithTag('TopicTest', '*')
            ->addListener(function (MessageView $view): MessageConsumeStatus {
                Log::debug("* RocketMQ debug: Consumer 接收到消息#{$view->id}, tag={$view->tag}, body={$view->body}");
                return MessageConsumeStatus::CONSUME_SUCCESS;
            })
            ->build();
    }
}
```

## 配置

所有配置都是动态的，`Colisys\Rocketmq\Builder\ConsumerFactory` 和 `Colisys\Rocketmq\Builder\ProducerFactory` 的构造函数接受一个 `Colisys\Rocketmq\Contract\ConnectionOption` 的实例，您可以手动初始化它或使用默认配置。

|      配置名称       |          默认值           | 描述                             |
| :-----------------: | :-----------------------: | :------------------------------- |
|        host         |        `localhost`        | RocketMQ Proxy 主机              |
|        port         |          `8081`           | RocketMQ Proxy 端口              |
|      clientId       |      `php-rocketmq`       | 用于标识客户端                   |
|      namespace      |            空             | RocketMQ 命名空间                |
|     clientType      | `CLIENT_TYPE_UNSPECIFIED` | RocketMQ 客户端类型              |
|      accessKey      |            空             | RocketMQ 访问密钥，即 "username" |
|      secretKey      |            空             | RocketMQ 密钥，即 "password"     |
|    sessionToken     |            空             | RocketMQ 会话令牌                |
|      enableTls      |          `false`          | 是否启用 TLS                     |
|       tlsCert       |            空             | TLS 证书文件路径                 |
|       tlsKey        |            空             | TLS 私钥文件路径                 |
|        tlsCa        |            空             | TLS CA 证书文件路径              |
|       timeout       |         `3.0`(秒)         | RPC 超时时间                     |
|      sendYield      |          `false`          | 发送消息时是否让出               |
|     sendTimeout     |           `-1`            | 发送超时，-1 表示无超时          |
|  heartbeatInterval  |        `10.0`(秒)         | 心跳间隔，最小为 10.0 秒         |
|     pollTimeout     |        `10.0`(秒)         | 轮询超时，最小为 10.0 秒         |
|    invisibleTime    |        `10.0`(秒)         | 不可见时间，最小为 10.0 秒       |
|   startupTimeout    |        `30.0`(秒)         | 启动超时，最小为 30.0 秒         |
|    ~~protocol~~     |        ~~`gRPC`~~         | ~~协议类型，默认为 gRPC~~        |
| ~~enableSlaveRead~~ |        ~~`false`~~        | ~~是否启用从读取~~               |

* `enableSlaveRead` 和 `protocol` 尚未实现，将继续开发。

## 规范 / TODO

此 SDK 面向最新版本的 RocketMQ，即 `^5.3.0`，**同时支持 gRPC 和 Remoting 协议**。

> 该 SDK 仍在开发中，**不推荐用于生产环境**。
>
> Remoting 协议支持仍处于实验阶段，推荐使用 gRPC 协议。
>
> 未来将为原生 PHP 用户添加独立的 Remoting 协议支持。

### gRPC 协议

gRPC 协议仅适用于 RocketMQ 5.0.0 或更高版本。

|              方法               | 状态  | 说明                                    |
| :-----------------------------: | :---: | :-------------------------------------- |
|           QueryRoute            |   ✅   |                                         |
|         QueryAssignment         |   ✅   |                                         |
|           SendMessage           |   ✅   |                                         |
|         EndTransaction          |   ✅   |                                         |
|            Heartbeat            |   ✅   |                                         |
|         ReceiveMessage          |   ✅   |                                         |
|           AckMessage            |   ✅   |                                         |
| ForwardMessageToDeadLetterQueue |   ✅   |                                         |
|            Telemetry            |   🚧   | 发送和接收一次后，意外断开连接          |
|           PullMessage           |   🚧   | 服务器未在 gRPC 协议上实现              |
|          UpdateOffset           |   🚧   | 服务器未在 gRPC 协议上实现              |
|            GetOffset            |   🚧   | 服务器未在 gRPC 协议上实现              |
|           QueryOffset           |   🚧   | 服务器未在 gRPC 协议上实现              |
|          RecallMessage          |   🔍   | 支持，但未测试                          |
|     ChangeInvisibleDuration     |   🔍   | 支持，但未测试，等待 `PullMessage` 实现 |
|     NotifyClientTermination     |   🔍   | 支持，但未测试，信号监听器不工作        |

✅: 已支持\
🔍: 已完成开发，但尚未测试\
🚧: 开发中

### Remoting 协议

Remoting 协议适用于 RocketMQ 的 `~4` 和 `~5` 版本。

|   方法    | 状态  | 说明             |
| :-------: | :---: | :--------------- |
| Heartbeat |   🚧   | 将完成遥测和心跳 |
|           |

✅: 已支持\
🔍: 已完成开发，但尚未测试\
🚧: 开发中
