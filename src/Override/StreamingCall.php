<?php

declare(strict_types=1);
/**
 * Third-party RocketMQ Client SDK for Hyperf
 *
 * @contact colisys@duck.com
 * @license MIT
 * @copyright 2025 Colisys
 */

namespace Colisys\RmqClient\Grpc\Override;

use Exception;
use Hyperf\Grpc\Parser;
use Hyperf\GrpcClient\StreamingCall as GrpcClientStreamingCall;

class StreamingCall extends GrpcClientStreamingCall
{
    /**
     * @see \Hyperf\GrpcClient\StreamingCall::recv()
     */
    public function recv(float $timeout = -1.0)
    {
        $streamId = $this->getStreamId();
        if ($streamId <= 0) {
            $recv = false;
        } else {
            $recv = $this->client->recv($streamId, $timeout);
            if (! $this->client->isStreamExist($streamId)) {
                // stream lost, we need re-push
                $streamId = -1;
            }
        }
        // disconnected or timed out
        if ($recv === false) {
            $sId = $this->streamId;
            $this->streamId = $streamId;
            throw new Exception("stream#{$sId} lost");
        }

        // server ended the stream
        if ($recv->pipeline === false) {
            $this->streamId = -1;
            return [null, 0, $recv];
        }

        return Parser::parseResponse($recv, $this->deserialize);
    }
}
