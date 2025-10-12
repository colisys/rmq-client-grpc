<?php

declare(strict_types=1);
/**
 * Third-party RocketMQ Client SDK for Hyperf
 *
 * @contact colisys@duck.com
 * @license MIT
 * @copyright 2025 Colisys
 */

namespace Colisys\Rocketmq\Remoting;

use Colisys\Rocketmq\Helper\Arr;
use Colisys\Rocketmq\Helper\Set;
use Exception;
use Hyperf\Framework\Exception\NotImplementedException;
use JsonSerializable;

interface RemotingCommandInterface
{
    public static function createRequestCommand(): static;

    public static function createResponseCommand(string $rawStream): static;
}

/**
 * @method static string decodeHeader(string $header)
 * @method static array|string decodeBody(string $body)
 * @method static self decodeAfter(self $command)
 */
abstract class RemotingCommand implements JsonSerializable, RemotingCommandInterface
{
    private Set $extFields;

    private int $code = 0;

    private int $opaque = 0;

    private int $version = -1;

    private RemotingCommandType $flag = RemotingCommandType::REQUEST_COMMAND;

    private string $language = 'PHP';

    private SerializeType $serializeType = SerializeType::JSON;

    private string $serializeTypeCurrentRPC = 'JSON';

    private array|string $body = '';

    private string $remark = '';

    public function __construct()
    {
        $this->opaque = time();
        $this->extFields = new Set();
    }

    public function __get($name)
    {
        if (property_exists(static::class, $name)) {
            return $this->{$name};
        }
        if (key_exists("{$name}", $this->body)) {
            return $this->body[$name];
        }
        throw new Exception("Property {$name} does not exist");
    }

    public function __set($name, $value)
    {
        if (property_exists(static::class, $name)) {
            $this->{$name} = $value;
            return;
        }
        if (key_exists($name, $this->body)) {
            $this->body[$name] = $value;
            return;
        }
        throw new Exception("Property {$name} does not exist");
    }

    public static function createRequestCommand(): static
    {
        $obj = new static();
        $obj->setRemotingCommandType(RemotingCommandType::REQUEST_COMMAND);
        return $obj;
    }

    public static function createResponseCommand(string $rawStream): static
    {
        $obj = new static();
        $obj->setRemotingCommandType(RemotingCommandType::RESPONSE_COMMAND);
        $obj->decode($rawStream);
        return $obj;
    }

    public function setRemotingCommandType(RemotingCommandType $commandType)
    {
        $this->flag = $commandType;
        return $this;
    }

    public function getRemotingCommandType()
    {
        return $this->flag;
    }

    public static function make()
    {
        return new static();
    }

    public function encode()
    {
        $length = 4;
        $header = $this->buildHeader();
        $body = $this->body;
        if (is_array($body)) {
            if ($this->getSerializeTypeCurrentRPC() == 'JSON') {
                $body = json_encode($body);
            }
        }
        $length += strlen($header);
        $length += strlen($body);

        $buffer = pack('N', $length | ($this->serializeType->value << 24));
        $buffer .= pack('N', strlen($header));
        $buffer .= $header;
        $buffer .= $body;

        return $buffer;
    }

    public function setOpaque(int $opaque)
    {
        $this->opaque = $opaque;
        return $this;
    }

    public function getOpaque()
    {
        return $this->opaque;
    }

    public function setRemark(string $remark)
    {
        $this->remark = $remark;
        return $this;
    }

    public function getRemark()
    {
        return $this->remark;
    }

    public function setExtFields(Set $extFields)
    {
        $this->extFields = $extFields;
        return $this;
    }

    public function getExtFields()
    {
        return $this->extFields;
    }

    public function setBody(array|string $body)
    {
        $this->body = $body;
        return $this;
    }

    public function getBody()
    {
        return $this->body;
    }

    public function setFlag(int $flag)
    {
        $this->flag = RemotingCommandType::from($flag);
        return $this;
    }

    public function getFlag()
    {
        return $this->flag;
    }

    public function setLanguage(string $language)
    {
        $this->language = $language;
        return $this;
    }

    public function getLanguage()
    {
        return $this->language;
    }

    public function setCode(int $code)
    {
        $this->code = $code;
        return $this;
    }

    public function getCode()
    {
        return $this->code;
    }

    public function setVersion(int $version)
    {
        $this->version = $version;
        return $this;
    }

    public function getVersion()
    {
        return $this->version;
    }

    public function setSerializeType(int $serializeType)
    {
        $this->serializeType = SerializeType::from($serializeType);
        return $this;
    }

    public function getSerializeType()
    {
        return $this->serializeType;
    }

    public function setSerializeTypeCurrentRPC(string $serializeTypeCurrentRPC)
    {
        $this->serializeTypeCurrentRPC = $serializeTypeCurrentRPC;
        return $this;
    }

    public function getSerializeTypeCurrentRPC()
    {
        return $this->serializeTypeCurrentRPC;
    }

    /**
     * Net packging structure as follows:
     *
     * ```txt
     * 0        8        16       24       32       40       48       56       64
     * +--------+--------+--------+--------+--------+--------+--------+--------+--------+
     * |     Message Length       ^ Type Bit        |       Actual Header Length        |
     * |--------------------------------------------------------------------------------|
     * |                                Header ...                                      |
     * |--------------------------------------------------------------------------------|
     * |                                Body ...                                        |
     * |--------------------------------------------------------------------------------|
     * ```
     */
    public static function decode(string $buf): static
    {
        $obj = new static();
        $oriHeaderLength = Arr::fromArray(unpack('N', $buf), 'int')->first();

        match ($oriHeaderLength >> 0x24 & 0xFF) {
            0x00 => $obj->setSerializeType(0x00),
            default => throw new NotImplementedException()
        };

        $headerLength = Arr::fromArray(unpack('N', $buf, 4), 'int')->first();

        if ($headerLength > $oriHeaderLength - 4) {
            throw new Exception("Header length is not correct, headerLength={$headerLength}, oriHeaderLength={$oriHeaderLength}");
        }

        $bodyLength = $oriHeaderLength - $headerLength - 4;
        $header = substr($buf, 8, $headerLength);
        $body = substr($buf, $headerLength + 8, $bodyLength);

        // Java serialize problem which ArrayList converted to Map, fix here.
        $header = preg_replace('/\{(\d+):/s', '{"$1":', $header);
        $header = preg_replace('/,(\d+):/s', ',"$1":', $header);
        $body = preg_replace('/\{(\d+):/s', '{"$1":', $body);
        $body = preg_replace('/,(\d+):/s', ',"$1":', $body);

        $data = method_exists(static::class, 'decodeHeader') ? static::{'decodeHeader'}($header) : json_decode($header, true);

        $obj->setCode($data['code'] ?? 0);
        $obj->setFlag($data['flag'] ?? 0);
        $obj->setVersion($data['version'] ?? 0);
        $obj->setOpaque($data['opaque'] ?? 0);
        $obj->setLanguage($data['language'] ?? '');
        $obj->setRemark($data['remark'] ?? '');
        $obj->setSerializeTypeCurrentRPC($data['serializeTypeCurrentRPC'] ?? 'JSON');

        if (method_exists(static::class, 'decodeBody')) {
            $obj->setBody(static::{'decodeBody'}($body, $obj->getSerializeTypeCurrentRPC()));
        } else {
            $obj->setBody(
                match ($obj->getSerializeTypeCurrentRPC()) {
                    'JSON' => json_decode($body, true) ?? '',
                    default => $body,
                }
            );
        }

        if (method_exists(static::class, 'decodeAfter')) {
            $obj = static::{'decodeAfter'}($obj);
        }

        return $obj;
    }
    
    public function jsonSerialize(): mixed
    {
        return $this->body;
    }

    private function buildHeader()
    {
        $header = [
            'code' => $this->code,
            'opaque' => $this->opaque,
            'version' => $this->version,
            'flag' => $this->flag->value,
            'language' => $this->language,
            'serializeTypeCurrentRPC' => $this->serializeTypeCurrentRPC,
        ];
        if ($this->extFields->size()) {
            $header['extFields'] = $this->extFields->toArray();
        }
        ksort($header);
        return json_encode($header);
    }
}
