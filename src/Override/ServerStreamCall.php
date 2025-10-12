<?php

declare(strict_types=1);
/**
 * Third-party RocketMQ Client SDK for Hyperf
 *
 * @contact colisys@duck.com
 * @license MIT
 * @copyright 2025 Colisys
 */

namespace Colisys\Rocketmq\Override;

use Hyperf\Grpc\Parser;
use Hyperf\Grpc\StatusCode;
use Hyperf\GrpcClient\Exception\GrpcClientException;
use Swoole\Http2\Response;

class ServerStreamCall extends StreamingCall
{
    private bool $isStreamEnd = false;

    public function push($message): void
    {
        throw new GrpcClientException('ServerStreamingCall can not push data from client', StatusCode::INTERNAL);
    }

    public function end(): void
    {
        if (! $this->isStreamEnd) {
            parent::end();
            $this->isStreamEnd = true;
        }
    }

    public function recv(float $timeout = -1.0)
    {
        if (! $this->isStreamEnd) {
            $this->end();
        }

        $streamId = $this->getStreamId();
        $buffer = '';
        $firstResponse = null;

        do {
            /**
             * @var ?Response
             */
            $recv = $this->client->recv($streamId, $timeout);
            if (! $recv) {
                $firstResponse = $recv;
            }
            $buffer .= $recv?->data ?? '';
        } while ($recv !== false);

        return [
            array_map(
                fn ($v) => Parser::deserializeMessage($this->deserialize, $v),
                self::frame_split($buffer)
            ),
            $firstResponse->headers['grpc-status'] ?? StatusCode::OK,
            $firstResponse,
        ];
    }

    public static function frame_split(string $buffer): array
    {
        $result = [];
        $length = 0;
        while ($length < strlen($buffer)) {
            $frameType = unpack('S', $buffer, $length);
            $dataLength = unpack('N', $buffer, $length + 1);
            $result[] = substr($buffer, $length, $dataLength[1] + 5);
            $length += $dataLength[1] + 5;
        }
        return $result;
    }
}
